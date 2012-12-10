Ext.ns('Tine.Messenger');

Tine.Messenger.Chat = Ext.extend(Ext.Window, {
    
    constructor: function () {
        var app = Tine.Tinebase.appMgr.get('Messenger');
        Ext.apply(this, {
        iconCls:     'messenger-icon',
        cls:         'messenger-chat-window',
        width:       460,
        minWidth:    400,
        height:      360,
        minHeight:   280,
        closeAction: 'hide', //'close' - destroy the component
        collapsible: true,
        plain:       true,
        layout:      'border',
        tbar: {
            items:[
                {
                    xtype: 'button',
                    plugins: [new Ext.ux.file.BrowsePlugin({
                        //multiple: true
                    })],
                    itemId: 'messenger-chat-send',
                    icon: '/images/messenger/page_go.png',
                    tooltip: app.i18n._('Send file'),
                    handler: function(filebrowser) { // has second argument: EventObject (optional)
                        var window_chat = filebrowser.component.ownerCt.ownerCt,
                            id = window_chat.id.substr(MESSENGER_CHAT_ID_PREFIX.length),
                            jid = Tine.Messenger.Util.idToJid(id);

                        Tine.Messenger.FileTransfer.sendRequest(jid, filebrowser);
                    }
                },
                {
                    xtype: 'button',
                    itemId: 'messenger-chat-video',
                    icon: '/images/messenger/webcam.png',
                    tooltip: app.i18n._('Start video chat'),
                    disabled: true,
                    handler: function() {

                    }
                 },
                 {
                    xtype: 'button',
                    itemId: 'messenger-chat-emoticons',
                    icon: '/images/messenger/emoticons/smile.png',
                    tooltip: app.i18n._('Choose a Emoticon'),
                    listeners: {
                        scope: this,
                        click: function() {
                            var mainChatWindow = this;
                            
                            // Show emoticon loading image
                            //Ext.getCmp('emoticon-connectloading').show();
                            
                            Ext.Ajax.request({
                                params: {
                                    method: 'Messenger.getEmoticons',
                                    chatID: mainChatWindow.id
                                },

                                failure: function (err, details) {
                                    // Hide emoticon loading image
                                    //Ext.getCmp('emoticon-connectloading').hide();
                                    
                                    Ext.Msg.show({
                                        title: app.i18n._('Emoticons'),
                                        msg: app.i18n._("Can't get Emoticons") + '!',
                                        buttons: Ext.Msg.OK,
                                        icon: Ext.MessageBox.ERROR,
                                        width: 300
                                    });
                                },

                                success: function (result, request) {
                                    var response = JSON.parse(result.responseText);
                                    
                                    // Hide emoticon loading image
                                    //Ext.getCmp('emoticon-connectloading').hide();

                                    var emoticonWindow = new Ext.Window({
                                        layout: {
                                            type: 'table',
                                            columns: 10
                                        },
                                        margins: {
                                            top: 5,
                                            left: 5
                                        },
                                        height: 175,
                                        width: 290,
                                        title: app.i18n._('Choose a Emoticon')
                                    });

                                    for (var i = 0; i < response.emoticons.length; i++) {
                                        var name = response.emoticons[i].name.replace('_', ' ').toUpperCase(),
                                            file = response.emoticons[i].file,
                                            text = response.emoticons[i].text;

                                        emoticonWindow.add({
                                            xtype: 'button',
                                            icon: file,
                                            cls: 'emoticon-button',
                                            tooltip: name,
                                            emoticon: text,
                                            handler: function () {
                                                var textfield = mainChatWindow.find('name', 'textfield-chat-message')[0];
                                                Tine.Messenger.Util.insertAtCursor(textfield, this.emoticon)
                                                emoticonWindow.close();
                                            }
                                        });
                                    }

                                    emoticonWindow.show();
                                }
                            });
                        }
                    }
                 }
            ]
        },
        listeners: {
            beforerender: function(_box){
                Tine.Messenger.AddItems(_box);
            },
            resize: function(_box, _width, _height){
                Tine.Messenger.ChatHandler.adjustChatAreaHeight(_box.id, _width, _height);
            },
            show: function () {
                this.setTextfieldFocus();
            },
            activate: function () {
                this.setTextfieldFocus();
            },
            expand: function () {
                this.setTextfieldFocus();
            },
            move: function(_box){
                Tine.Messenger.Window._onMoveWindowAction(_box);
            }
        }
  });
        Tine.Messenger.Chat.superclass.constructor.apply(this, arguments);
    },
    
    setTextfieldFocus: function () {
        this.getComponent(2).focus(false, 200); // foco no textfield
    }
    
});