Ext.ns('Tine.Messenger');

Tine.Messenger.Priority = function (config) {
    Tine.Messenger.Priority.superclass.constructor.call(this,
        Ext.applyIf(config || {}, {
            id: 'messenger-priority-window',
            title: Tine.Tinebase.appMgr.get('Messenger').i18n._('Priority settings'),
            layout: 'form',
            labelSeparator: '',
            width: 400,
            height: 250,
            items: [
                {
                    xtype: 'label',
                    id: 'messenger-display-priority',
                    text: Tine.Tinebase.appMgr.get('Messenger').i18n._('Priority') + ': ' + 10
                },
                new Ext.Slider({
                    id: 'messenger-priority-value',
                    width: 350,
                    minValue: -1,
                    maxValue: 100,
                    value: 10,
                    listeners: {
                        change: function (slider, value) {
                            var labelCmp = Ext.getCmp('messenger-display-priority'),
                                label = labelCmp.text,
                                text;
                            if (label.match(/:/))
                                label = label.substring(0, label.indexOf(':'));
                            
                            text = label + ': ' + value;
                            switch (value) {
                                case -1:
                                    text += ' (' + Tine.Tinebase.appMgr.get('Messenger').i18n._('Negative values do not receive messages') + ')';
                                    break;
                                case 1:
                                    text += ' (' + Tine.Tinebase.appMgr.get('Messenger').i18n._('Pidgin priority') + ')';
                                    break;
                                case 5:
                                    text += ' (' + Tine.Tinebase.appMgr.get('Messenger').i18n._('PSI priority') + ')';
                                    break;
                                case 10:
                                    text += ' (' + Tine.Tinebase.appMgr.get('Messenger').i18n._('Gaijm priority') + ')';
                                    break;
                            }
                            
                            labelCmp.setText(text);
                        }
                    }
                }),
                {
                    xtype: 'label',
                    text: Tine.Tinebase.appMgr.get('Messenger').i18n._('The highest the priority more likely this client to receive the message') + '.'
                },
                {
                    xtype: 'label',
                    text: ' ' + Tine.Tinebase.appMgr.get('Messenger').i18n._('Examples: PSI priority = 5. Pidgin priority = 1')
                }
            ],
            fbar: [
                {
                    id: 'messenger-priority-button',
                    text: Tine.Tinebase.appMgr.get('Messenger').i18n._('Change'),
                    handler: function (button) {
                        var priority = Ext.getCmp('messenger-priority-value').getValue(),
                            status = Ext.getCmp("ClientDialog").status,
                            statusText = Ext.getCmp("ClientDialog").statusText;

                        button.ownerCt.ownerCt.hide();
                        Tine.Messenger.RosterHandler.setStatus(status, statusText, priority.toString());
                    }
                },
                {
                    id: 'messenger-priority-cancel',
                    text: Tine.Tinebase.appMgr.get('Messenger').i18n._('Cancel'),
                    handler: function (button) {
                        button.ownerCt.ownerCt.hide();
                    }
                }
            ]
        }));
};

Ext.extend(Tine.Messenger.Priority, Ext.Window);