Ext.ns('Tine.Messenger');

Tine.Messenger.HistoryWindow = function (config) {
    Tine.Messenger.HistoryWindow.superclass.constructor.call(this,
        Ext.applyIf(config || {}, {
            id: 'messenger-history-window',
            title: Tine.Tinebase.appMgr.get('Messenger').i18n._('History Chat with') + ' ' + Tine.Messenger.RosterHandler.getContactElement(config.contact).text,
            layout: 'border',
            labelSeparator: '',
            width: 320,
            height: 240,
            closeAction: 'close',
            buttons: [
                {
                    id: 'messenger-history-download',
                    text: Tine.Tinebase.appMgr.get('Messenger').i18n._('Download'),
                    hidden: true,
                    handler: function (button) {
                        Tine.Messenger.HistoryDownload(config.jid, config.contact, button.date);
                    }
                }
            ],
            items: [
                {
                    xtype: 'panel',
                    id: 'messenger-history-dates',
                    autoScroll: true,
                    width: 100,
                    region: 'west',
                    border: false,
                    frame: false,
                    bodyStyle: 'background: transparent',
                    columns: [ { header: 'Chat Date' } ],
                    minHeight: 240,
                    items: [
                        {
                            xtype: 'panel',
                            id: 'history-loading',
                            border: false,
                            frame: false,
                            bodyStyle: 'background: transparent',
                            html: '<img src="/images/messenger/loading_animation_liferay.gif" />'
                        }
                    ],
                    listeners: {
                        render: function (panel) {
                            Ext.Ajax.request({
                                params: {
                                    method: 'Messenger.listHistory',
                                    jid: config.jid,
                                    contact: config.contact
                                },
                                success: function (res) {
                                    var result = JSON.parse(res.responseText),
                                        dates = result.content;

                                    Tine.Messenger.HistoryList(dates, panel, config.jid, config.contact);
                                },
                                failure: function (err, details) {
                                    console.log(err);
                                    console.log(details);
                                }
                            });
                        }
                    }
                },
                {
                    xtype: 'panel',
                    id: 'messenger-history-chat',
                    autoScroll: true,
                    region: 'center'
                }
            ]
        })
    );
};
Ext.extend(Tine.Messenger.HistoryWindow, Ext.Window);

Tine.Messenger.HistoryList = function (dates, panel, jid, contact) {
    Ext.getCmp('history-loading').hide();
                                    
    for (var i = 0; i < dates.length; i++) {
        var date_panel = new Ext.Panel({
            xtype: 'panel',
            id: dates[i],
            border: false,
            frame: false,
            bodyStyle: 'background: transparent',
            cls: 'history-list-button',
            html: dates[i].replace(/(\d{4})-(\d{2})-(\d{2})/, '$3/$2/$1'),
            listeners: {
                render: function (p) {
                    p.body.on('click', function () {
                        // Show download button
                        var downloadBT = Ext.getCmp('messenger-history-download');
                        downloadBT.date = p.id;
                        downloadBT.show();
                        
                        // Stylize the button clicked
                        var buttons = Ext.query('.history-list-button');
                        for (var i = 0; i < buttons.length; i++) {
                            Ext.getCmp(buttons[i].id).removeClass('history-list-button-chosen');
                        }
                        p.addClass('history-list-button-chosen');
                        
                        // Clear chat history panel and show loading image
                        Tine.Messenger.HistoryClearAndLoad();
                        
                        // Request the history file
                        Ext.Ajax.request({
                            params: {
                                method: 'Messenger.getHistory',
                                jid: jid,
                                contact: contact,
                                date: p.id
                            },
                            success: function (res) {
                                var result = JSON.parse(res.responseText),
                                    history = result.content;

                                Tine.Messenger.HistoryDisplay(history, Ext.getCmp('messenger-history-chat'), jid, contact);
                            },
                            failure: function (err, details) {
                                console.log(err);
                                console.log(details);
                            }
                        });
                    });
                }
            }
        });

        panel.add(date_panel);
    }
    panel.doLayout();
};

Tine.Messenger.HistoryClearAndLoad = function () {
    var history_chat = Ext.getCmp('messenger-history-chat');
    history_chat.removeAll();
    history_chat.add({
        xtype: 'panel',
        border: false,
        html: '<img style="margin: 20px;" src="/images/messenger/loading_animation_liferay.gif" />'
    });
    history_chat.doLayout();
}

Tine.Messenger.HistoryDisplay = function (history, panel, jid, contact) {
    panel.removeAll();
    for (var i = 0; i < history.length; i++) {
        if (history[i]) {
            var chat_line = JSON.parse(history[i]),
                message;

            var color = chat_line.dir == 'to' ? '#8fb1e8' : '#98d96c';

            message = '<div style="font-weight: bold; float: left; margin-right: 10px; text-align: center; width: 15%; color: ' + color + ';">';
            message += chat_line.dir == 'to' ? 
                Tine.Messenger.RosterHandler.getContactElement(contact).text :
                Tine.Tinebase.appMgr.get('Messenger').i18n._('ME');
            message += '<div style="font-weight: normal; color: grey; text-align: center;">(';
            message += chat_line.time + ')</div>';
            message += '</div>';
            message += '<div style="float: left; width: 75%;">';
            message += Tine.Messenger.ChatHandler.replaceEmotions(chat_line.msg);
            message += '</div>';

            panel.add({
                xtype: 'panel',
                html: message,
                cls: 'messenger-history-display',
                border: false
            });
        }
    }
    panel.doLayout();
};

Tine.Messenger.HistoryDownload = function (jid, contact, date) {
    var downloader = new Ext.ux.file.Download({
        params: {
            method: 'Messenger.downloadHistory',
            jid: jid,
            contact: contact,
            date: date
        }
    });
    downloader.start();
};