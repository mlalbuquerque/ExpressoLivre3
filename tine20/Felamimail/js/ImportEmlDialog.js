/*
 * Tine 2.0
 * 
 * @package     Felamimail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */
 
Ext.namespace('Tine.Felamimail');

/**
 * @namespace   Tine.Felamimail
 * @class       Tine.Felamimail.ImportEmlDialog
 * @extends     Tine.widgets.dialog.EditDialog
 * 
 * <p>Message Compose Dialog</p>
 * <p>This dialog is for composing emails with recipients, body and attachments. 
 * you can choose from which account you want to send the mail.</p>
 * <p>
 * TODO         make email note editable
 * </p>
 * 
 * @author      Antonio Carlos da Silva  <antonio-carlos.silva@serpro.gov.br>
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * 
 * @param       {Object} config
 * @constructor
 * Create a new ImportEmlDialog
 */
 Tine.Felamimail.ImportEmlDialog = Ext.extend(Tine.widgets.dialog.EditDialog, {
    /**
     * @cfg {Array/String} bcc
     * initial config for bcc
     */
    bcc: null,
    
    /**
     * @cfg {String} body
     */
    msgBody: '',
    
    /**
     * @cfg {Array/String} cc
     * initial config for cc
     */
    cc: null,
    
    /**
     * @cfg {Array} of Tine.Felamimail.Model.Message (optionally encoded)
     * messages to forward
     */
    forwardMsgs: null,
    
    /**
     * @cfg {String} accountId
     * the accout id this message is sent from
     */
    accountId: null,
    
    /**
     * @cfg {Tine.Felamimail.Model.Message} (optionally encoded)
     * message to reply to
     */
    replyTo: null,

    /**
     * @cfg {Tine.Felamimail.Model.Message} (optionally encoded)
     * message to use as draft/template
     */
    draftOrTemplate: null,
    
    /**
     * @cfg {Boolean} (defaults to false)
     */
    replyToAll: false,
    
    /**
     * @cfg {String} subject
     */
    subject: '',
    
    /**
     * @cfg {Array/String} to
     * initial config for to
     */
    to: null,
    
    /**
     * if sending
     * @type Boolean
     */
    sending: false,
    
    /**
     * validation error message
     * @type String
     */
     validationErrorMessage: '',
    
    /**
     * @private
     */
    windowNamePrefix: 'ImportEmlWindow_',
    appName: 'Felamimail',
    recordClass: Tine.Felamimail.Model.Message,
    recordProxy: Tine.Felamimail.messageBackend,
    loadRecord: false,
    evalGrants: false,
    
    bodyStyle:'padding:0px',
    
    /**
     * overwrite update toolbars function (we don't have record grants)
     * @private
     */
    updateToolbars: Ext.emptyFn,
    
    //private
    initComponent: function() {
         Tine.Felamimail.ImportEmlDialog.superclass.initComponent.call(this);
         
         this.on('save', this.onSave, this);
    },
    
    /**
     * init buttons
     */
    initButtons: function() {
        this.fbar = [];
        
        this.action_cancel = new Ext.Action({
            text: this.app.i18n._('Cancel'),
            handler: this.onCancel,
            iconCls: 'action_cancel',
            disabled: false,
            scope: this
        });

       // this.action_searchContacts = new Ext.Action({
       //     text: this.app.i18n._('Search Recipients'),
       //     handler: this.onSearchContacts,
       //     iconCls: 'AddressbookIconCls',
       //     disabled: false,
       //     scope: this
       // });
        
       // this.action_saveAsDraft = new Ext.Action({
       //     text: this.app.i18n._('Save As Draft'),
       //     handler: this.onSaveInFolder.createDelegate(this, ['drafts_folder']),
       //     iconCls: 'action_saveAsDraft',
       //     disabled: false,
       //     scope: this
       // });

       // this.action_saveAsTemplate = new Ext.Action({
       //     text: this.app.i18n._('Save As Template'),
       //     handler: this.onSaveInFolder.createDelegate(this, ['templates_folder']),
       //     iconCls: 'action_saveAsTemplate',
       //     disabled: false,
       //     scope: this
       // });

        this.action_selectFile = new Ext.Action({
            text: this.app.i18n._('File to Import'),
            iconCls: 'action_import',
            scope: this,
            plugins: [{
                ptype: 'ux.browseplugin',
                multiple: true,
                dropElSelector: null
            }],
            handler: function (fileSelector, e) {
        var uploader = new Ext.ux.file.Upload({
            maxFileSize: 67108864, // 64MB
            fileSelector: fileSelector
        });
               
        uploader.on('uploadcomplete',function(x,fileRecord){
            Ext.Ajax.request({
            params: {
                method: 'Felamimail.importMessage',
                accountId:this.account,
                folderId: this.folderId,
                file: fileRecord.get('tempFile').path
            },
            scope: this,
            success: function(_result, _request){
                Ext.MessageBox.hide();
                this.onCancel();
            },
            failure: function(response, options) {
                Ext.MessageBox.hide();
                var responseText = Ext.util.JSON.decode(response.responseText);
                if (responseText.data.code == 505) {
                    Ext.Msg.show({
                       title:   _('Error'),
                       msg:     _('Error import message eml!'),
                       icon:    Ext.MessageBox.ERROR,
                       buttons: Ext.Msg.OK
                    });

                } else {
                    // call default exception handler
                    var exception = responseText.data ? responseText.data : responseText;
                    Tine.Tinebase.ExceptionHandler.handleRequestException(exception);
                }
            }
        });
       }, this);

        Ext.MessageBox.wait(_('Please wait'),_('Loading'));
        var files = fileSelector.getFileList();
        Ext.each(files, function (file) {
            var fileRecord = uploader.upload(file);
         }, this);
        }
             });

        // TODO think about changing icon onToggle
        //this.action_saveEmailNote = new Ext.Action({
        //    text: this.app.i18n._('Save Email Note'),
        //    handler: this.onToggleSaveNote,
        //    iconCls: 'notes_noteIcon',
        //    disabled: false,
        //    scope: this,
        //    enableToggle: true
        //});
        
        //this.button_saveEmailNote = Ext.apply(new Ext.Button(this.action_saveEmailNote), {
        //    tooltip: this.app.i18n._('Activate this toggle button to save the email text as a note attached to the recipient(s) contact(s).')
        //});

        this.tbar = new Ext.Toolbar({
            defaults: {height: 50},
            items: [{
                xtype: 'buttongroup',
                columns: 3,
                items: [
                    Ext.apply(new Ext.Button(this.action_cancel), {
                        scale: 'medium',
                        rowspan: 2,
                        iconAlign: 'top'
                    }),
                    Ext.apply(new Ext.Button(this.action_selectFile), {
                        scale: 'medium',
                        rowspan: 2,
                        iconAlign: 'top'
                    })
                ]
            }]
        });
        
    },
    
    /**
     * @private
     */
    initRecord: function() {
        this.decodeMsgs();
        
        if (! this.record) {
            this.record = new this.recordClass(Tine.Felamimail.Model.Message.getDefaultData(), 0);
        }
        
        this.initFrom();
        this.initRecipients();
        this.initSubject();
        this.initContent();
        
        // legacy handling:...
        // TODO add this information to attachment(s) + flags and remove this
        if (this.replyTo) {
            this.record.set('flags', '\\Answered');
            this.record.set('original_id', this.replyTo.id);
        } else if (this.forwardMsgs) {
            this.record.set('flags', 'Passed');
            this.record.set('original_id', this.forwardMsgs[0].id);
        } else if (this.draftOrTemplate) {
            this.record.set('original_id', this.draftOrTemplate.id);
        }
    },
    
    /**
     * init attachments when forwarding message
     * 
     * @param {Tine.Felamimail.Model.Message} message
     */
    initAttachements: function(message) {
        if (message.get('attachments').length > 0) {
             Attachments = [];
            for(i in message.get('attachments')){
               
                if(message.get('attachments')[i].size && !message.get('attachments')[i].cid){
                    
                    Attachments.push(
                        {
                        name: message.get('attachments')[i]['filename'],
                        type: message.get('attachments')[i]['content-type'],
                        size: message.get('attachments')[i]['size'],
                        partId:  message.get('attachments')[i]['partId'],
                        id: message.id
                     });
                }
            }
            this.record.set('attachments',Attachments);
        }
    },
    
    /**
     * inits body and attachments from reply/forward/template
     */
    initContent: function() {
        if (! this.record.get('body')) {
            if (! this.msgBody) {
                var message = this.getMessageFromConfig();
                          
                if (message) {
                    if (! message.bodyIsFetched()) {
                        // self callback when body needs to be fetched
                        return this.recordProxy.fetchBody(message, this.initContent.createDelegate(this));
                    }
                    
                    this.msgBody = message.get('body');
                    
                    var account = Tine.Tinebase.appMgr.get('Felamimail').getAccountStore().getById(this.record.get('account_id'));
            
                    if (account.get('display_format') == 'plain' || (account.get('display_format') == 'content_type' && message.get('body_content_type') == 'text/plain')) {
                        this.msgBody = Ext.util.Format.nl2br(this.msgBody);
                    }
                    
                    if (this.replyTo) {
                        var date = (this.replyTo.get('received')) ? this.replyTo.get('received') : new Date();
                        this.msgBody = String.format(this.app.i18n._('On {0}, {1} wrote'), 
                            Tine.Tinebase.common.dateTimeRenderer(date), 
                            Ext.util.Format.htmlEncode(this.replyTo.get('from_name'))
                        ) + ':<br/>'
                          + '<blockquote class="felamimail-body-blockquote">' + this.msgBody + '</blockquote><br/>';
                    } else if (this.forwardMsgs && this.forwardMsgs.length === 1) {
                        this.msgBody = '<br/>-----' + this.app.i18n._('Original message') + '-----<br/>'
                            + Tine.Felamimail.GridPanel.prototype.formatHeaders(this.forwardMsgs[0].get('headers'), false, true) + '<br/><br/>'
                            + this.msgBody + '<br/>';
                        this.initAttachements(message);
                    } else if (this.draftOrTemplate) {
                        this.initAttachements(message);
                    }
                }
            }
            
            if (! this.draftOrTemplate) {
                this.msgBody += Tine.Felamimail.getSignature(this.record.get('account_id'))
            }
        
            this.record.set('body', this.msgBody);
        }
        
        delete this.msgBody;
        this.onRecordLoad();
    },
    
    /**
     * inits / sets sender of message
     */
    initFrom: function() {
        if (! this.record.get('account_id')) {
            if (! this.accountId) {
                var message = this.getMessageFromConfig(),
                    folderId = message ? message.get('folder_id') : null, 
                    folder = folderId ? Tine.Tinebase.appMgr.get('Felamimail').getFolderStore().getById(folderId) : null,
                    accountId = folder ? folder.get('account_id') : null;
                    
                if (! accountId) {
                    var activeAccount = Tine.Tinebase.appMgr.get('Felamimail').getActiveAccount();
                    accountId = (activeAccount) ? activeAccount.id : null;
                }
                
                this.accountId = accountId;
            }
            
            this.record.set('account_id', this.accountId);
        }
        delete this.accountId;
    },
    
    /**
     * after render
     */
    afterRender: function() {
        Tine.Felamimail.ImportEmlDialog.superclass.afterRender.apply(this, arguments);
        
        this.getEl().on(Ext.EventManager.useKeydown ? 'keydown' : 'keypress', this.onKeyPress, this);
        this.recipientGrid.on('specialkey', function(field, e) {
            this.onKeyPress(e);
        }, this);
        /*
        this.recipientGrid.on('blur', function(editor) {
            // do not let the blur event reach the editor grid if we want the subjectField to have focus
            if (this.subjectField.hasFocus) {
                return false;
            }
        }, this);
        
       
        this.htmlEditor.on('keydown', function(e) {
            if (e.getKey() == e.ENTER && e.ctrlKey) {
                this.onSaveAndClose();
            } else if (e.getKey() == e.TAB && e.shiftKey) {
                this.subjectField.focus.defer(50, this.subjectField);
            }
        }, this);
        */
    },
    
    /**
     * message is sending
     */
    onSave: function() {
        this.sending = true;
    },
    
    /**
     * on key press
     * @param {} e
     * @param {} t
     * @param {} o
     */
    /*
    onKeyPress: function(e, t, o) {
        if ((e.getKey() == e.TAB || e.getKey() == e.ENTER) && ! e.shiftKey) {
            if (e.getTarget('input[name=subject]')) {
                this.htmlEditor.focus.defer(50, this.htmlEditor);
            } else if (e.getTarget('input[type=text]')) {
                this.subjectField.focus.defer(50, this.subjectField);
            }
        }
    },
    */
   
    /**
     * returns message passed with config
     * 
     * @return {Tine.Felamimail.Model.Message}
     */
    getMessageFromConfig: function() {
        return this.replyTo ? this.replyTo : 
               this.forwardMsgs && this.forwardMsgs.length === 1 ? this.forwardMsgs[0] :
               this.draftOrTemplate ? this.draftOrTemplate : null;
    },
    
    /**
     * inits to/cc/bcc
     */
    initRecipients: function() {
        if (this.replyTo) {
            this.initReplyRecipients();
        }
        
        Ext.each(['to', 'cc', 'bcc'], function(field) {
            if (this.draftOrTemplate) {
                this[field] = this.draftOrTemplate.get(field);
            }
            
            if (! this.record.get(field)) {
                this[field] = Ext.isArray(this[field]) ? this[field] : Ext.isString(this[field]) ? [this[field]] : [];
                this.record.set(field, Ext.unique(this[field]));
            }
            delete this[field];
            
            this.resolveRecipientFilter(field);
            
        }, this);
    },
    
    /**
     * init recipients from reply/replyToAll information
     */
    initReplyRecipients: function() {
        var replyTo = this.replyTo.get('headers')['reply-to'];
        
        if (replyTo) {
            this.to = replyTo;
        } else {
            var toemail = '<' + this.replyTo.get('from_email') + '>';
            if (this.replyTo.get('from_name') && this.replyTo.get('from_name') != this.replyTo.get('from_email')) {
                this.to = this.replyTo.get('from_name') + ' ' + toemail;
            } else {
                this.to = toemail;
            }
        }
        
        if (this.replyToAll) {
            if (! Ext.isArray(this.to)) {
                this.to = [this.to];
            }
            this.to = this.to.concat(this.replyTo.get('to'));
            this.cc = this.replyTo.get('cc');
            
            // remove own email and all non-email strings/objects from to/cc
            var account = Tine.Tinebase.appMgr.get('Felamimail').getAccountStore().getById(this.record.get('account_id')),
                ownEmailRegexp = new RegExp(account.get('email'));
            Ext.each(['to', 'cc'], function(field) {
                for (var i=0; i < this[field].length; i++) {
                    if (! Ext.isString(this[field][i]) || ! this[field][i].match(/@/) || ownEmailRegexp.test(this[field][i])) {
                        this[field].splice(i, 1);
                    }
                }
            }, this);
        }
    },
    
    /**
     * resolve recipient filter / queries addressbook
     * 
     * @param {String} field to/cc/bcc
     */
    resolveRecipientFilter: function(field) {
        if (! Ext.isEmpty(this.record.get(field)) && Ext.isObject(this.record.get(field)[0]) &&  this.record.get(field)[0].operator) {
            // found a filter
            var filter = this.record.get(field);
            this.record.set(field, []);
            
            this['AddressLoadMask'] = new Ext.LoadMask(Ext.getBody(), {msg: this.app.i18n._('Loading Mail Addresses')});
            this['AddressLoadMask'].show();
            
            Tine.Addressbook.searchContacts(filter, null, function(response) {
                var mailAddresses = Tine.Felamimail.AddressbookGridPanelHook.prototype.getMailAddresses(response.results);
                
                this.record.set(field, mailAddresses);
                this.recipientGrid.syncRecipientsToStore([field], this.record, true, false);
                this['AddressLoadMask'].hide();
                
            }.createDelegate(this));
        }
    },
    
    /**
     * sets / inits subject
     */
    initSubject: function() {
        if (! this.record.get('subject')) {
            if (! this.subject) {
                if (this.replyTo) {
                    // check if there is already a 'Re:' prefix
                    var replyPrefix = this.app.i18n._('Re:'),
                        replyPrefixRegexp = new RegExp('^' + replyPrefix, 'i'),
                        replySubject = (this.replyTo.get('subject')) ? this.replyTo.get('subject') : '';
                        
                    if (! replySubject.match(replyPrefixRegexp)) {
                        this.subject = replyPrefix + ' ' +  replySubject;
                    } else {
                        this.subject = replySubject;
                    }
                } else if (this.forwardMsgs) {
                    this.subject =  this.app.i18n._('Fwd:') + ' ';
                    this.subject += this.forwardMsgs.length === 1 ?
                        this.forwardMsgs[0].get('subject') :
                        String.format(this.app.i18n._('{0} Message', '{0} Messages', this.forwardMsgs.length));
                } else if (this.draftOrTemplate) {
                    this.subject = this.draftOrTemplate.get('subject');
                }
            }
            this.record.set('subject', this.subject);
        }
        
        delete this.subject;
    },
    
    /**
     * decode this.replyTo / this.forwardMsgs from interwindow json transport
     */
    decodeMsgs: function() {
        if (Ext.isString(this.draftOrTemplate)) {
            this.draftOrTemplate = new this.recordClass(Ext.decode(this.draftOrTemplate));
        }
        
        if (Ext.isString(this.replyTo)) {
            this.replyTo = new this.recordClass(Ext.decode(this.replyTo));
        }
        
        if (Ext.isString(this.forwardMsgs)) {
            var msgs = [];
            Ext.each(Ext.decode(this.forwardMsgs), function(msg) {
                msgs.push(new this.recordClass(msg));
            }, this);
            
            this.forwardMsgs = msgs;
        }
    },
    
    /**
     * fix input fields layout
     */
    fixLayout: function() {
        return;
        /*
        if (! this.subjectField.rendered || ! this.accountCombo.rendered || ! this.recipientGrid.rendered) {
            return;
        }
        
        var scrollWidth = this.recipientGrid.getView().getScrollOffset();
        this.subjectField.setWidth(this.subjectField.getWidth() - scrollWidth + 1);
        this.accountCombo.setWidth(this.accountCombo.getWidth() - scrollWidth + 1);
        */
    },
    
    /**
     * save message in folder
     * 
     * @param {String} folderField
     */
    onSaveInFolder: function (folderField) {
        /*
        this.onRecordUpdate();
        
        var account = Tine.Tinebase.appMgr.get('Felamimail').getAccountStore().getById(this.record.get('account_id')),
            folderName = account.get(folderField);
                    
        if (! folderName || folderName == '') {
            Ext.MessageBox.alert(
                this.app.i18n._('Failed'), 
                String.format(this.app.i18n._('{0} account setting empty.'), folderField)
            );
        } else if (this.isValid()) {
            this.loadMask.show();
            this.recordProxy.saveInFolder(this.record, folderName, {
                scope: this,
                success: function(record) {
                    this.fireEvent('update', Ext.util.JSON.encode(this.record.data));
                    this.purgeListeners();
                    this.window.close();
                },
                failure: this.onRequestFailed,
                timeout: 150000 // 3 minutes
            });
        } else {
            Ext.MessageBox.alert(_('Errors'), _('Please fix the errors noted.'));
        }
        */
    },
    
    /**
     * toggle save note
     * 
     * @param {} button
     * @param {} e
     */
    //onToggleSaveNote: function (button, e) {
    //    this.record.set('note', (! this.record.get('note')));
    //},
    
    /**
     * search for contacts as recipients
     */
    //onSearchContacts: function() {
    //    Tine.Felamimail.RecipientPickerDialog.openWindow({
    //        record: new this.recordClass(Ext.copyTo({}, this.record.data, ['subject', 'to', 'cc', 'bcc']), Ext.id()),
    //        listeners: {
    //            scope: this,
    //            'update': function(record) {
    //                var messageWithRecipients = Ext.isString(record) ? new this.recordClass(Ext.decode(record)) : record;
    //                this.recipientGrid.syncRecipientsToStore(['to', 'cc', 'bcc'], messageWithRecipients, true, true);
    //            }
    //        }
    //    });
    //},
    
    /**
     * executed after record got updated from proxy
     * 
     * @private
     */
    onRecordLoad: function() {
        // interrupt process flow till dialog is rendered
        if (! this.rendered) {
            this.onRecordLoad.defer(250, this);
            return;
        }
        
        var title = this.app.i18n._('Importando arquivo para a pasta: ' + this.textName );
        //if (this.record.get('subject')) {
        //    title = title + ' ' + this.record.get('subject');
        //}
        this.window.setTitle(title);
        
        this.getForm().loadRecord(this.record);
        this.attachmentGrid.loadRecord(this.record);
        
        if (this.record.get('note') && this.record.get('note') == '1') {
            this.button_saveEmailNote.toggle();
        }
        
        this.loadMask.hide();
    },
        
    /**
     * executed when record gets updated from form
     * - add attachments to record here
     * - add alias / from
     * 
     * @private
     */
    onRecordUpdate: function() {

        this.record.data.attachments = [];
        var attachmentData = null;
        
        this.attachmentGrid.store.each(function(attachment) {
            this.record.data.attachments.push(Ext.ux.file.Upload.file.getFileData(attachment));
        }, this);
        
        var accountId = this.accountCombo.getValue();
            account = this.accountCombo.getStore().getById(accountId),
            emailFrom = account.get('email');
        this.record.set('from_email', emailFrom);
        
        Tine.Felamimail.ImportEmlDialog.superclass.onRecordUpdate.call(this);

        this.record.set('account_id', account.get('original_id'));
        
        // need to sync once again to make sure we have the correct recipients
        this.recipientGrid.syncRecipientsToRecord();
        
        /*
        if (this.record.data.note) {
            // show message box with note editing textfield
            //console.log(this.record.data.note);
            Ext.Msg.prompt(
                this.app.i18n._('Add Note'),
                this.app.i18n._('Edit Email Note Text:'), 
                function(btn, text) {
                    if (btn == 'ok'){
                        record.data.note = text;
                    }
                }, 
                this,
                100, // height of input area
                this.record.data.body 
            );
        }
        */
    },
    
    /**
     * show error if request fails
     * 
     * @param {} response
     * @param {} request
     * @private
     * 
     * TODO mark field(s) invalid if for example email is incorrect
     * TODO add exception dialog on critical errors?
     */
    onRequestFailed: function(response, request) {
        Ext.MessageBox.alert(
            this.app.i18n._('Failed'), 
            String.format(this.app.i18n._('Could not send {0}.'), this.i18nRecordName) 
                + ' ( ' + this.app.i18n._('Error:') + ' ' + response.message + ')'
        );
        this.sending = false;
        this.loadMask.hide();
    },

    /**
     * init attachment grid + add button to toolbar
     */
    
    initAttachmentGrid: function() {
        
        if (! this.attachmentGrid) {
        
            this.attachmentGrid = new Tine.widgets.grid.FileUploadGrid({
                fieldLabel: this.app.i18n._('Attachments'),
                hideLabel: true,
                filesProperty: 'attachments',
                // TODO     think about that -> when we deactivate the top toolbar, we lose the dropzone for files!
                //showTopToolbar: false,
                anchor: '100% 95%'
            });
         /*   
            // add file upload button to toolbar
            //this.action_addAttachment = this.attachmentGrid.getAddAction();
            //this.action_addAttachment = {
            //text: _('Selecionar Arquivo'),
            //iconCls: 'action_import',
            //scope: this,
            //plugins: [{
            //    ptype: 'ux.browseplugin',
            //    multiple: true,
            //    dropElSelector: null
            //}],
            //handler: this.onFilesSelect
            // };
            //this.action_addAttachment.plugins[0].dropElSelector = null;
            //this.action_addAttachment.text = _('Selecionar Arquivo');
            //this.action_addAttachment.plugins[0].onBrowseButtonClick = function() {
            //    this.southPanel.expand();
            //}.createDelegate(this);
            
            //this.tbar.get(0).insert(1, Ext.apply(new Ext.Button(this.action_addAttachment), {
            //    scale: 'medium',
            //    rowspan: 2,
            //    iconAlign: 'top'
            //}));
            */
        }
        
    },
    
    /**
     * init account (from) combobox
     * 
     * - need to create a new store with an account record for each alias
     */
    initAccountCombo: function() {
        var accountStore = Tine.Tinebase.appMgr.get('Felamimail').getAccountStore(),
            accountComboStore = new Ext.data.ArrayStore({
                fields   : Tine.Felamimail.Model.Account
            });
        
        var aliasAccount = null,
            aliases = null,
            id = null
            
        accountStore.each(function(account) {
            
            aliases = [ account.get('email') ];

            if (account.get('type') == 'system') {
                // add identities / aliases to store (for systemaccounts)
                var user = Tine.Tinebase.registry.get('currentAccount');
                if (user.emailUser && user.emailUser.emailAliases && user.emailUser.emailAliases.length > 0) {
                    aliases = aliases.concat(user.emailUser.emailAliases);
                }
            }
            
            for (var i = 0; i < aliases.length; i++) {
                id = (i == 0) ? account.id : Ext.id();
                aliasAccount = account.copy(id);
                if (i > 0) {
                    aliasAccount.data.id = id;
                    aliasAccount.set('email', aliases[i]);
                }
                aliasAccount.set('name', aliasAccount.get('name') + ' (' + aliases[i] +')');
                aliasAccount.set('original_id', account.id);
                accountComboStore.add(aliasAccount);
            }
        }, this);
        
        //this.accountCombo = new Ext.form.ComboBox({
        //    name: 'account_id',
        //    ref: '../../accountCombo',
        //    plugins: [ Ext.ux.FieldLabeler ],
        //    fieldLabel: this.app.i18n._('From'),
        //    displayField: 'name',
        //    valueField: 'id',
        //    editable: false,
        //    triggerAction: 'all',
        //    store: accountComboStore,
        //    mode: 'local',
        //    listeners: {
        //        scope: this,
        //        select: this.onFromSelect
        //    }
        //});
    },
    
    /**
     * if 'account_id' is changed we need to update the signature
     * 
     * @param {} combo
     * @param {} newValue
     * @param {} oldValue
     */
     //onFromSelect: function(combo, record, index) {
     //   
     //   // get new signature
     //   var accountId = record.get('original_id');
     //   var newSignature = Tine.Felamimail.getSignature(accountId);
     //   var signatureRegexp = new RegExp('<br><br><span id="felamimail\-body\-signature">\-\-<br>.*</span>');
     //   
     //   // update signature
     //   var bodyContent = this.htmlEditor.getValue();
     //   bodyContent = bodyContent.replace(signatureRegexp, newSignature);
     //   
     //   this.htmlEditor.setValue(bodyContent);
    //},
    
    /**
     * returns dialog
     * 
     * NOTE: when this method gets called, all initialisation is done.
     * 
     * @return {Object}
     * @private
     */
    getFormItems: function() {
        this.initAttachmentGrid();
        this.initAccountCombo();
        
        this.recipientGrid = new Tine.Felamimail.RecipientGrid({
            record: this.record,
            i18n: this.app.i18n,
            hideLabel: true,
            composeDlg: this,
            autoStartEditing: !this.AddressLoadMask
        });
        
        this.southPanel = new Ext.Panel({
            region: 'south',
            layout: 'form',
            height: 1,  // 150
            split: true,
            collapseMode: 'mini',
            header: false,
            collapsible: true,
            collapsed: (this.record.bodyIsFetched() && (! this.record.get('attachments') || this.record.get('attachments').length == 0))
            //items: [this.attachmentGrid]
        });

        this.htmlEditor = new Tine.Felamimail.ComposeEditor({
            fieldLabel: this.app.i18n._('Body'),
            flex: 1  // Take up all *remaining* vertical space
        });

        return {
            border: false,
            frame: true,
            layout: 'border',
            items: [
                {
                region: 'center',
                layout: {
                    align: 'stretch',  // Child items are stretched to full width
                    type: 'vbox'
                },
                listeners: {
                    'afterlayout': this.fixLayout,
                    scope: this
                }
                 /*
                items: [
                    
                    //this.accountCombo, 
                    //this.recipientGrid, 
                {
                    xtype:'textfield',
                    plugins: [ Ext.ux.FieldLabeler ],
                    fieldLabel: this.app.i18n._('Subject'),
                    name: 'subject',
                    ref: '../../subjectField',
                    enableKeyEvents: true
                   
                    listeners: {
                        scope: this,
                        // update title on keyup event
                        'keyup': function(field, e) {
                            if (! e.isSpecialKey()) {
                                this.window.setTitle(
                                    this.app.i18n._('Compose email:') + ' ' 
                                    + field.getValue()
                                );
                            }
                        }
                    }
                    
                }, //this.htmlEditor
                     
                ]
                   */      
            }, 
            this.southPanel]
        };
    },

    /**
     * is form valid (checks if attachments are still uploading / recipients set)
     * 
     * @return {Boolean}
     */
    isValid: function() {
        this.validationErrorMessage = Tine.Felamimail.ImportEmlDialog.superclass.getValidationErrorMessage.call(this);
        
        var result = true;
        
        if (this.attachmentGrid.isUploading()) {
            result = false;
            this.validationErrorMessage = this.app.i18n._('Files are still uploading.');
        }
        
        if (result) {
            result = this.validateRecipients();
        }
        
        
        return (result && Tine.Felamimail.ImportEmlDialog.superclass.isValid.call(this));
    },
    
    /**
     * checks recipients
     * 
     * @return {Boolean}
     */
    validateRecipients: function() {
        var result = true;
        
        if (this.record.get('to').length == 0 && this.record.get('cc').length == 0 && this.record.get('bcc').length == 0) {
            this.validationErrorMessage = this.app.i18n._('No recipients set.');
            result = false;
        }
        
        return result;
    },
    
    /**
     * get validation error message
     * 
     * @return {String}
     */
    getValidationErrorMessage: function() {
        return this.validationErrorMessage;
    }    
});

/**
 * Felamimail Edit Popup
 * 
 * @param   {Object} config
 * @return  {Ext.ux.Window}
 */
Tine.Felamimail.ImportEmlDialog.openWindow = function (config) {
    var window = Tine.WindowFactory.getWindow({
        width: 240,
        height: 65,
        resizable: false,
        name: Tine.Felamimail.ImportEmlDialog.prototype.windowNamePrefix + Ext.id(),
        contentPanelConstructor: 'Tine.Felamimail.ImportEmlDialog',
        contentPanelConstructorConfig: config,
        modal: true
    });
    return window;
};
