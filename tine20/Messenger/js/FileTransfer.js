Ext.ns('Tine.Messenger');

Tine.Messenger.FileTransfer = {
    
    resource: null,
    
    sendRequest: function (item, filebrowser) {
        var app = Tine.Tinebase.appMgr.get('Messenger');
        var to = typeof item == 'string' ? item : item.node.attributes.jid;
        var from = Tine.Tinebase.appMgr.get('Messenger').getConnection().jid;

        Tine.Messenger.FileTransfer.chooseResourceAndSend(to, function () {
            if (Tine.Messenger.FileTransfer.resource == null) {
                Ext.Msg.show({
                    title: app.i18n._('File Transfer'),
                    msg: app.i18n._('You must choose a resource') + '!',
                    buttons: Ext.Msg.OK,
                    icon: Ext.MessageBox.INFO,
                    width: '300px'
                });
            } else {
                var fileName = filebrowser.files[0].name,
                    fileSize = filebrowser.files[0].size,
                    htmlText = '<p class="file_transfer_progress">' +
                               app.i18n._('Sending file') + '...' +
                               '<span>' + fileName + ' (' + fileSize + ' bytes)</span>' +
                               '</p>';
                if (fileSize > 209715200) {
                    var error = Tine.Tinebase.appMgr.get('Messenger').i18n._('Maximum file size: 200MB') + '! ' +
                                fileName + ': ' + (fileSize/1024) + 'MB';
                    Ext.Msg.show({
                        title: Tine.Tinebase.appMgr.get('Messenger').i18n._('Error'),
                        msg: error,
                        buttons: Ext.Msg.OK,
                        icon: Ext.MessageBox.ERROR
                    });
                } else {
                    var progress = new Ext.Window({
                        title: app.i18n._('File Transfer'),
                        items: [
                            {
                                html: htmlText,
                                border: false,
                                frame: false,
                                bodyStyle: 'background: transparent'
                            },
                            {
                                xtype: 'progress',
                                id: 'file-transfer-progress'
                            }
                        ]
                    });

                    var sending = app.i18n._('Sending file') + '!';
                    Tine.Messenger.FileTransfer.logFileTransfer(
                        sending,
                        filebrowser.files[0],
                        to + '/' + Tine.Messenger.FileTransfer.resource,
                        from
                    );
                        
//                    var upload = new Ext.ux.file.Upload({
//                        file: filebrowser.files[0],
//                        fileSelector: filebrowser
//                    });
                    var upload = new Tine.Messenger.Upload({
                        file: filebrowser.files[0],
                        fileSelector: filebrowser
                    });

                    upload.on('uploadcomplete', function (upload, file) {
                        progress.close();

                        var temps = new Array();
                        Ext.each(upload.tempFiles, function (item) {
                            temps.push(item.path);
                        });

                        Ext.Ajax.request({
                            params: {
                                method: 'Messenger.removeTempFiles',
                                files: temps
                            },
                            failure:  function (err, details) {
                                console.log(err);
                                console.log(details);
                                Tine.Messenger.Log.error(app.i18n._('Temporary files not deleted'));
                            },
                            success: function (result) {
                                var response = JSON.parse(result.responseText);
                                if (response.status)
                                    Tine.Messenger.Log.info(app.i18n._('Temporary files deleted'));
                                else
                                    Tine.Messenger.Log.error(app.i18n._('Temporary files not deleted'));
                            }
                        });

                        var info = $msg({'to': to + '/' + Tine.Messenger.FileTransfer.resource});
                        if (Tine.Messenger.FileTransfer.resource == Tine.Tinebase.registry.get('messenger').messenger.resource) {
                            info.attrs({'type': 'filetransfer'})
                                .c("file", {
                                    'name': file.data.name,
                                    'path': file.data.tempFile.path,
                                    'size': file.data.size
                                });
                        } else {
                            info.attrs({'type': 'chat'})
                                .c("html", {"xmlns": "http://jabber.org/protocol/xhtml-im"})
                                .c("body", {"xmlns": "http://www.w3.org/1999/xhtml"})
                                .c("strong")
                                .t(app.i18n._('File sent') + ' :  ')
                                .up()
                                .c("a", {"href": Tine.Messenger.FileTransfer.downloadURL(file.data.name, file.data.tempFile.path)})
                                .t(file.data.name);
                        }
                        Tine.Messenger.Application.connection.send(info);

                        var sent = app.i18n._('File sent') + '!';
                        Tine.Messenger.FileTransfer.logFileTransfer(
                            sent, 
                            file.data, 
                            to + '/' + Tine.Messenger.FileTransfer.resource, 
                            from
                        );

                        Ext.Msg.show({
                            title: app.i18n._('File Transfer'),
                            msg: app.i18n._('File sent') +
                                 '!<h6 style="padding: 5px 0; width: 300px;">' +
                                 file.data.name +
                                 ' (' + file.data.size + ' bytes)</h6>',
                            buttons: Ext.Msg.OK,
                            icon: Ext.MessageBox.INFO,
                            width: '300px'
                        });
                    });

                    upload.on('uploadfailure', function (upload, file) {
                        progress.close();

                        var error = app.i18n._('Error sending file') + '!';
                        Tine.Messenger.FileTransfer.logFileTransfer(
                            error, 
                            file.data, 
                            to + '/' + Tine.Messenger.FileTransfer.resource, 
                            from
                        );

                        console.log(upload);
                        console.log(file);
                        Ext.Msg.show({
                            title: app.i18n._('File Transfer Error'),
                            msg: app.i18n._('Error uploading file') + '!',
                            buttons: Ext.Msg.OK,
                            icon: Ext.MessageBox.ERROR,
                            width: 300
                        });
                    });

                    progress.getComponent('file-transfer-progress').wait();
                    progress.show();
                    var uploadKey = Tine.Tinebase.uploadManager.queueUpload(upload);
                    Tine.Tinebase.uploadManager.upload(uploadKey); // returns uploaded file
                }
            }
        });
    },
    
    onRequest: function (msg) {
        var app = Tine.Tinebase.appMgr.get('Messenger');
        var from = $(msg).attr('from'),
            to = $(msg).attr('to'), // ME
            jid = Strophe.getBareJidFromJid(from),
            file = $(msg).find('file'),
            fileName = file.attr('name'),
            fileSize = file.attr('size'),
            filePath = file.attr('path'),
            ext = Tine.Messenger.FileTransfer.getExtension(fileName),
            contact = Tine.Messenger.RosterHandler.getContactElement(jid);

        var confirm = new Ext.Window({
            title: app.i18n._('File Transfer'),
            border: false,
            iconCls: 'filetransfer-icon-title',
            html: contact.text + ' ' +
                  app.i18n._('wants to send you a file:') +
                  '<img style="display: block; width: 64px; margin: 0 auto;"' +
                  ' src="/images/files/' + ext + '-small.png"/>' +
                  '<h6 style="padding: 5px 0; width: 300px; text-align: center;">' + fileName + 
                  ' (' + fileSize + ' bytes)</h6>' +
                  '<div>' + app.i18n._('Do you allow') + '?</div>',
            closeAction: 'close',
            buttons: [
                {
                    text: app.i18n._('Yes'),
                    handler: function() {
                        var received = app.i18n._('File received') + '!',
                            fileObj = {
                                name: fileName,
                                size: fileSize
                            };
                        Tine.Messenger.FileTransfer.logFileTransfer(received, fileObj, to, from);
                        Tine.Messenger.FileTransfer.downloadHandler(fileName, filePath, 'yes', confirm)
                    }
                },
                {
                    text: app.i18n._('No'),
                    handler: function() {
                        var refused = app.i18n._('File refused') + '!',
                            fileObj = {
                                name: fileName,
                                size: fileSize
                            };
                        Tine.Messenger.FileTransfer.logFileTransfer(refused, fileObj, to, from);
                        Tine.Messenger.FileTransfer.downloadHandler(fileName, filePath, 'no', confirm)
                    }
                }
            ],
            width: 300
        });
        confirm.show();
        
        return true;
    },
    
    chooseResourceAndSend: function (jid, callbackSend) {
        var app = Tine.Tinebase.appMgr.get('Messenger');
        var contact = Tine.Messenger.RosterHandler.getContactElement(jid);

        if (contact.attributes.resources.length > 1) {
            var resourceValues = [];
            for (var i = 0; i < contact.attributes.resources.length; i++) {
                resourceValues.push({
                    xtype: 'button',
                    text: contact.attributes.resources[i],
                    style: {
                        margin: '3px'
                    },
                    handler: function (button) {
                        Ext.getCmp('filetransfer-resources').close();
                        Tine.Messenger.FileTransfer.resource = button.getText();
                        callbackSend();
                    }
                });
            }

            var resources = new Ext.Window({
                id: 'filetransfer-resources',
                title: app.i18n._('File Transfer'),
                border: false,
                iconCls: 'filetransfer-icon-title',
                closeAction: 'close',
                width: 300,
                items: resourceValues,
                html: '<h4 style="margin: 5px;">' +
                      contact.attributes.text +
                      app.i18n._(' has more than one resource. Choose one!') +
                      '</h4>',
                layout: 'column'
            });
            resources.show();
        } else {
            Tine.Messenger.FileTransfer.resource = contact.attributes.resources[0];
            callbackSend();
        }
    },
    
    downloadURL: function (fileName, filePath) {
        var protocol = window.location.protocol,
            host = window.location.hostname,
            port = window.location.port != 80 ? ':' + window.location.port : '',
            script = '/index.php',
            params = '?method=Messenger.getFile'
                   + '&name=' + fileName
                   + '&tmpfile=' + filePath
                   + '&downloadOption=yes';
            
        return protocol + '//' + host + port + script + params;
    },

    downloadHandler: function(fileName, filePath, download, window) {
        window.close();
        
        var downloader = new Ext.ux.file.Download({
            params: {
                method: 'Messenger.getFile',
                name: fileName,
                tmpfile: filePath,
                downloadOption: download
            }
        });
        downloader.start();
    },
    
    logFileTransfer: function (message, file, to, from) {
        Ext.Ajax.request({
            params: {
                method: 'Messenger.logFileTransfer',
                text: message + ' ' + file.name + ' (' + file.size + ' bytes)! ' + from + ' ==> ' + to
            },
            success: function (result) {
                var response = JSON.parse(result.responseText);
                // TODO: Controller method to log message
                // TODO: Change the branch to new branch
                console.log('File Transfer logged! ' + response.log);
            },
            failure: function (err, details) {
                console.log(err);
                console.log(details);
                console.log('File Transfer not logged!');
            }
        });
    },
    
    getExtension: function(fileName) {
        var ext = fileName.substring(fileName.lastIndexOf('.') + 1).toLowerCase();
        
        switch(ext) {
            case 'asp':
            case 'as':
            case 'avi':
            case 'bat':
            case 'bin':
            case 'bmp':
            case 'bz2':
            case 'cab':
            case 'cal':
            case 'cat':
            case 'css':
            case 'dat':
            case 'deb':
            case 'div':
            case 'divx':
            case 'dll':
            case 'doc':
            case 'docx':
            case 'dvd':
            case 'exe':
            case 'fla':
            case 'flv':
            case 'gif':
            case 'gz':
            case 'htm':
            case 'html':
            case 'ico':
            case 'ini':
            case 'iso':
            case 'jar':
            case 'java':
            case 'javac':
            case 'jpeg':
            case 'jpg':
            case 'js':
            case 'log':
            case 'm4a':
            case 'mid':
            case 'mov':
            case 'mp3':
            case 'mp4':
            case 'mpeg':
            case 'mpg':
            case 'odg':
            case 'odp':
            case 'ods':
            case 'odt':
            case 'pdf':
            case 'php':
            case 'png':
            case 'pps':
            case 'ppt':
            case 'pptx':
            case 'py':
            case 'rar':
            case 'rb':
            case 'rtf':
            case 'swf':
            case 'tgz':
            case 'tiff':
            case 'torrent':
            case 'txt':
            case 'vob':
            case 'wav':
            case 'wma':
            case 'wmv':
            case 'xls':
            case 'xlsx':
            case 'xml':
            case 'xsl':
            case 'zip':
                return ext;
            default:
                return 'generic';
        }
    }
    
};