/*
 * This files extends the Ext.ux.file.Upload from Tinebase.
 * The purpose is to set a new temp file path
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Marcio Albuquerque <m.jatho@metaways.de>
 */

Ext.ns('Tine.Messenger');

Tine.Messenger.Upload = Ext.extend(Ext.ux.file.Upload, {
    html4upload: function() {
                
        var form = this.createForm();
        var input = this.getInput();
        form.appendChild(input);
        
        this.fileRecord = new Ext.ux.file.Upload.file({
            name: this.fileSelector.getFileName(),
            size: 0,
            type: this.fileSelector.getFileCls(),
            input: input,
            form: form,
            status: 'uploading',
            progress: 0
        });
        
        this.fireEvent('update', 'uploadprogress', this, this.fileRecord);
        
        if(this.maxFileUploadSize/1 < this.file.size/1) {
            this.fileRecord.html4upload = true;
            this.onUploadFail(null, null, this.fileRecord);
            return this.fileRecord;
        }
        
        Ext.Ajax.request({
            fileRecord: this.fileRecord,
            isUpload: true,
            method:'post',
            form: form,
            success: this.onUploadSuccess.createDelegate(this, [this.fileRecord], true),
            failure: this.onUploadFail.createDelegate(this, [this.fileRecord], true),
            params: {
                method: 'Messenger.uploadTempFile',
                requestType: 'HTTP'
            }
        });
        
        return this.fileRecord;
    },
    
    /**
     * 2010-01-26 Current Browsers implemetation state of:
     *  http://www.w3.org/TR/FileAPI
     *  
     *  Interface: File | Blob | FileReader | FileReaderSync | FileError
     *  FF       : yes  | no   | no         | no             | no       
     *  safari   : yes  | no   | no         | no             | no       
     *  chrome   : yes  | no   | no         | no             | no       
     *  
     *  => no json rpc style upload possible
     *  => no chunked uploads posible
     *  
     *  But all of them implement XMLHttpRequest Level 2:
     *   http://www.w3.org/TR/XMLHttpRequest2/
     *  => the only way of uploading is using the XMLHttpRequest Level 2.
     */
    html5upload: function() {
                 
    	// TODO: move to upload method / checks max post size
        if(this.maxPostSize/1 < this.file.size/1 && !this.isHtml5ChunkedUpload()) {
            this.fileRecord.html5upload = true;
            this.onUploadFail(null, null, this.fileRecord);
            return this.fileRecord;
        }
        
        var defaultHeaders = {
            "Content-Type"          : "application/x-www-form-urlencoded",
            "X-Tine20-Request-Type" : "HTTP",
            "X-Requested-With"      : "XMLHttpRequest"
        };
        
        var xmlData = this.file;
               
        if(this.isHtml5ChunkedUpload()) {
            xmlData = this.currentChunk;
        }

        var conn = new Ext.data.Connection({
            disableCaching: true,
            method: 'POST',
            url: this.url + '?method=Messenger.uploadTempFile',
            timeout: 300000, // 5 mins
            defaultHeaders: defaultHeaders
        });
                
        var transaction = conn.request({
            headers: {
                "X-File-Name"           : this.fileRecord.get('name'),
                "X-File-Type"           : this.fileRecord.get('type'),
                "X-File-Size"           : this.fileRecord.get('size')
            },
            xmlData: xmlData,
            success: this.onUploadSuccess.createDelegate(this, null, true),
            failure: this.onUploadFail.createDelegate(this, null, true) 
        });       

        return this.fileRecord;
    },
    
    /**
     * executed if a chunk or file got uploaded successfully
     */
    onUploadSuccess: function(response, options, fileRecord) {
        
        var responseObj = Ext.util.JSON.decode(response.responseText);

        this.retryCount = 0;

        if(responseObj.status && responseObj.status !== 'success') {
        	this.onUploadFail(responseObj, options, fileRecord);
        }

        this.fileRecord.beginEdit();
        this.fileRecord.set('tempFile', responseObj.tempFile);
        this.fileRecord.set('size', 0);
        this.fileRecord.commit(false);

        this.fireEvent('update', 'uploadprogress', this, this.fileRecord);
        
        if(! this.isHtml5ChunkedUpload()) {            

        	this.finishUploadRecord(response);           
        }       
        else {

        	this.addTempfile(this.fileRecord.get('tempFile'));
        	var percent = parseInt(this.currentChunkPosition * 100 / this.fileSize/1);                
        	
        	if(this.lastChunk) {
        		percent = 98;
        	}

        	this.fileRecord.beginEdit();
        	this.fileRecord.set('progress', percent);
        	this.fileRecord.commit(false);

        	if(this.lastChunk) {

        		window.setTimeout((function() {
        			Ext.Ajax.request({
        				timeout: 10*60*1000, // Overriding Ajax timeout - important!
        				params: {
        				method: 'Messenger.joinTempFiles',
        				tempFilesData: this.tempFiles
        			},
        			success: this.finishUploadRecord.createDelegate(this), 
        			failure: this.finishUploadRecord.createDelegate(this)
        			});
        		}).createDelegate(this), this.CHUNK_TIMEOUT_MILLIS);

        	}
        	else {
        		window.setTimeout((function() {
        			if(!this.isPaused()) {
        				this.prepareChunk();
        				this.html5upload();
        			}
        		}).createDelegate(this), this.CHUNK_TIMEOUT_MILLIS);
        	}                                               

        }  

    }
    
});