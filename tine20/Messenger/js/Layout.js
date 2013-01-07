
Ext.ns('Tine.Messenger');

Tine.Messenger.ClientDialog = function(args){
    var app = Tine.Tinebase.appMgr.get('Messenger');
    var ClientLayout = {
            id:'ClientDialog',
            title: app.i18n._('Expresso Messenger'),
            iconCls: 'messenger-icon-off',
            connected: false,
            status: '',
            width: 220,
            minWidth: 420,
            height: 420,
            minHeight: 270,
            closeAction: 'hide', //'close' - destroy the component
            collapsible: true,
            plain: true,
            autoScroll: true,
            border: false,
            layout: 'border', // 'fit'
            listeners: {
                move: function(_box){
                    Tine.Messenger.Window._onMoveWindowAction(_box);
                }
            },
            tbar: {
                    cls: 'messenger-client-tbar',
                    items:[
                        {
                                id: 'messenger-contact-add',
                                xtype: 'button',
                                icon: '/images/messenger/user_add.png',
                                tooltip: app.i18n._('Add Contact'),
                                disabled: true,
                                handler: function(){
                                    Tine.Messenger.Window.AddBuddyWindow();
                                }
                        },
                        {
                                id: 'messenger-group-mngt-add',
                                xtype: 'button',
                                tooltip: app.i18n._('Add Group'),
                                icon: '/images/messenger/group_add.png',
                                disabled: true,
                                handler: function() {
                                    new Tine.Messenger.WindowConfig(Tine.Messenger.WindowLayout.Groups).show();
                                }
                         },
                         {
                             id: 'messenger-show-offline-contacts',
                             xtype: 'button',
                             // _('Show offline contacts')
                             tooltip: app.i18n._('Hide offline contacts'),
                             icon: 'images/messenger/hidden_icon_unavailable.png',
                             showOffline: true,
                             disabled: true,
                             handler: function() {
                                 Tine.Messenger.IM.changeOfflineContactsDisplay();
                                 this.showOffline = !this.showOffline;
                             }
                         },
                         {
                             id: 'messenger-change-priority',
                             xtype: 'button',
                             tooltip: app.i18n._('Priority setting'),
                             icon: 'images/messenger/warning.png',
                             showOffline: true,
                             disabled: true,
                             handler: function() {
                                 var priorityWindow;
                                 
                                 if (Ext.getCmp('messenger-priority-window')) {
                                     priorityWindow = Ext.getCmp('messenger-priority-window');
                                 } else {
                                     priorityWindow = new Tine.Messenger.Priority({});
                                 }
                                 
                                 priorityWindow.show();
                             }
                         },
                         {
                             id: 'messenger-logout',
                             xtype: 'button',
                             tooltip: app.i18n._('Login'),
                             icon: 'images/messenger/startup.png',
                             systemOn: false,
                             handler: function() {
                                 if (this.systemOn) {
                                     Ext.getCmp('connectloading').hide();
                                     Tine.Messenger.ChatHandler.disconnect();
                                     Tine.Messenger.IM.changeSystemLogonButton(['startup', 'Login']);
                                 } else {
                                     Tine.Messenger.ChatHandler.connect();
                                     Tine.Messenger.IM.changeSystemLogonButton(['shutdown', 'Logout']);
                                 }
                                 this.systemOn = !this.systemOn;
                             }
                         },
                         {
                             id: 'connectloading',
                             xtype: 'panel',
                             border: false,
                             html: '<img src="/images/messenger/loading_animation_liferay.gif" />',
                             hidden: true
                         }
                    ]
            },
            items:[{
			region:'center',
			border:false,
                        bodyStyle:'padding:6px 3px 0 3px;',
			layout:'fit',
                        items: Tine.Messenger._Roster
            }],
            bbar:{
                id: 'status-container',
                height: 27,
                cls: 'messenger-client-tbar',
                layout: 'hbox',
                items: [{   
                        id: 'messenger-change-status-button',
                        icon: '/images/messenger/user_offline.png',
                        listeners: {
                            click: function (field, ev) {
                                statusMenu(this);
                            }
                        },
                        flex: 1
                    },
                    {xtype: 'tbspacer', width: 5},
                    {
                        xtype:'textfield',
//                        store: Tine.Messenger.factory.statusStore,
                        displayField:'text',
                        valueField:'value',
                        typeAhead: true,
                        name:'message',
                        mode: 'local',
                        triggerAction: 'all',
                        id:'messenger-status-box',
                        emptyText:app.i18n._('Type your Status') + '...' 
                                                            + '(' + app.i18n._('press ENTER after') + ')',
                        selectOnFocus:true,
                        listeners: {
                            specialkey: function (field, ev) {
                                if (ev.getKey() == ev.ENTER) {
                                    changeStateText(this);
                                }
                            }
                        },
                        flex: 9
                    }
                ]
            }
    };
    
    var changeStateText = function(_box){
        Tine.Messenger.RosterHandler.changeStatus(Ext.getCmp('ClientDialog').status, 
                                                    _box.getValue());
    }
    
    var changeStateHandler = function(_e){
        Tine.Messenger.RosterHandler.changeStatus(_e.value, 
                                                    Ext.get('messenger-status-box').getValue());
    }
    
    var statusMenu = function(_box){
            var items = Array(),
                statusItems = Tine.Messenger.factory.statusStore.data.items;
            /**
             * Traduções dos status
             * _('Online')
             * _('Away')
             * _('Do Not Disturb')
             * _('Unavailable')
             */
            for(var i=0; i < statusItems.length; i++){
                var text = app.i18n._(statusItems[i].data.text),
                    value = statusItems[i].data.value;
                items.push({text: app.i18n._(text),
                            value: value,
                            icon: '/images/messenger/user_'+value+'.png',
                            handler: changeStateHandler
                      });
            }
            
            var menu = new Ext.menu.Menu({
                            items: items
                    });
            menu.show(_box.getPositionEl());
    }

    Tine.Messenger.WindowConfig.superclass.constructor.call(this,
        Ext.apply(ClientLayout, args || {})
    );
};

Ext.extend(Tine.Messenger.ClientDialog, Ext.Window);

Tine.Messenger._Roster = 
    new Ext.tree.TreePanel({
                                    id:           'messenger-roster',
                                    loader:       new Ext.tree.TreeLoader(),
                                    border:       false,
                                    cls:          'messenger-treeview',
                                    autoScroll:   true,
                                    rootVisible:  false,
                                    root: new Ext.tree.AsyncTreeNode({
                                        expanded: true,
                                        leaf:     false
                                    })
                                });
                                
Tine.Messenger._ChatRoster = 
    new Ext.tree.TreePanel({
                                    loader:       new Ext.tree.TreeLoader(),
                                    border:       false,
                                    cls:          'messenger-groupchat-roster',
                                    rootVisible:  false,
                                    root: new Ext.tree.AsyncTreeNode({
                                        expanded: true,
                                        leaf:     false,
                                        cls:      'messenger-groupchat-roster-tree'
                                    })
                                });
                                
Tine.Messenger.SimpleDialog = function(_config){
    var extDialog=null;
    var config=_config;
    var opener=Ext.getBody();
    var createDialog=function(){
        extDialog=new Ext.Window(config);
        console.log(opener);
        extDialog.show(opener);
        extDialog.doLayout();
        return extDialog;
    }
    return{
        init:function(){
            if(!extDialog){
                return createDialog();
            }
        }
    }
}

var configWindow = null;
    
Tine.Messenger.AddItems = function(_box) {
    if(!_box.items){
        var items = Array();
        var colW = 1,
            bodyroster = {},
            styleCls = '';
        if(_box.type == 'groupchat' && !_box.privy){
            colW = .75;
            styleCls += 'border-right-width: 2px;';
            bodyroster = ({
                            itemId: 'messenger-chat-body-roster',
                            columnWidth: .2,
                            border: false,
                            items: new Ext.tree.TreePanel({
                                    loader:       new Ext.tree.TreeLoader(),
                                    border:       false,
                                    cls:          'messenger-groupchat-roster',
                                    rootVisible:  false,
                                    
                                    root: new Ext.tree.AsyncTreeNode({
                                        expanded: true,
                                        leaf:     false,
                                        cls:      'messenger-groupchat-roster-tree'
                                    })
                                })
                        });
        }
        
        items.push(
                {
                    itemId: 'messenger-chat-table',
                    layout: 'column',
                    region: 'center',
                    minWidth: 210,
                    border: false,
                    autoScroll: true,
                    items: [
                        bodyroster,
                        {
                            itemId: 'messenger-chat-body',
                            xtype: 'panel',
                            border: styleCls ? true : false,
                            autoScroll: true,
                            cls: 'messenger-chat-body',
                            style: styleCls,
                            columnWidth: colW
                        }
                    ]
                }
            );
        items.push(
                {
                    xtype: 'panel',
                    region: 'south',
                    height: 80,
                    listeners: {
//                        afterrender: function (panel) {
//                            new Ext.Resizable(panel.id, {
//                                handles: 'n',
//                                minHeight: 80,
//                                pinned: false,
//                                listeners: {
//                                    resize: function (resizer, width, height) {
//                                        panel.setSize(width, height);
//                                        panel.getComponent(0).setSize(width, height);
//                                        panel.ownerCt.doLayout();
//                                    }
//                                }
//                            });
//                            panel.getComponent(0).setSize(panel.getWidth(), panel.getHeight());
//                        },
                        resize: function (panel) {
                            panel.getComponent(0).setSize(panel.getWidth(), panel.getHeight());
                        }
                    },
                    items: [
                        {
                            xtype: 'imhtmleditor',
                            name: 'textfield-chat-message',
                            cls:   'text-sender messenger-chat-field'
                        }
                    ]
                }
            );
//        return items;
         _box.add(items);
    }

};

Tine.Messenger.WindowLayout = {
    Buddy   : 'AddBuddyLayout',
    Groups  : 'AddGroupLayout',
    Chat    : 'JoinChatLogin'
};

Tine.Messenger.WindowConfig = function(window, args) {
    var app = Tine.Tinebase.appMgr.get('Messenger');
    var AddBuddyLayout = {
        id: 'messenger-contact-add-client',
        closeAction: 'close',
        layout: 'fit',
        plain: true,
        modal: true,
        title: app.i18n._('Add Contact'),
        listeners: {
            render: function(e){
                Ext.getCmp('messenger-contact-add-group')
                   .store
                   .loadData(
                        Tine.Messenger.RosterTree().getGroupsFromTree()
                   );
            }
        },
        items: [
            {
                xtype: 'form',
                border: false,
                items: [
                    {
                        xtype: 'textfield',
                        id: 'messenger-contact-add-jid',
                        fieldLabel: app.i18n._('JID'),
                        value: '',
                        disabled: false
                    },
                    {
                        xtype: 'textfield',
                        id: 'messenger-contact-add-name',
                        fieldLabel: app.i18n._('Name')
                    },
                    {
                        xtype: 'combo',
                        id: 'messenger-contact-add-group',
                        fieldLabel: app.i18n._('Group'),
                        store: new Ext.data.SimpleStore({
//                                        data: Tine.Messenger.RosterTree().getGroupsFromTree(),
                                        id: 0,
                                        fields: ['text']
                                }),
                        emptyText: app.i18n._('Select a group') + '...',
                        valueField: 'text',
                        displayField: 'text',
                        triggerAction: 'all',
                        editable: false,
                        mode : 'local'
                    },
                    {
                        xtype: 'button',
                        id: 'messenger-contact-add-button',
                        text: app.i18n._('Add'),
                        listeners: {
                            click: function () {
                               Tine.Messenger.Window.AddBuddyHandler(
                                                        Ext.getCmp('messenger-contact-add-client')
                                                    );
                            }
                        }
                    }
                ],
                keys: [
                    {
                        key: [Ext.EventObject.ENTER],
                        handler: function () {
                            Tine.Messenger.Window.AddBuddyHandler(
                                                    Ext.getCmp('messenger-contact-add-client')
                                                );
                        }
                    }
                ]
            }
        ]
    };
    
    var AddGroupLayout = {
        id: 'messenger-group-add-client',
        closeAction: 'close',
        layout: 'fit',
        plain: true,
        modal: true,
        title: app.i18n._('Add Group'),
        items: [{
                xtype: 'form',
                border: false,
                items: [
                    {
                        xtype: 'textfield',
                        id: 'messenger-group-mngt-name',
                        fieldLabel: app.i18n._('Name')
                    },
                    {
                        xtype: 'button',
                        id: 'messenger-group-mngt-button',
                        text: app.i18n._('Add'),
                        listeners: {
                            click: function () {
                                Tine.Messenger.Window.AddGroupHandler(
                                                        Ext.getCmp('messenger-group-add-client')
                                                    );
                            }
                        }
                    }
                ]
            }
        ],
        keys: [
            {
                key: [Ext.EventObject.ENTER],
                handler: function () {
                    Tine.Messenger.Window.AddGroupHandler(
                                            Ext.getCmp('messenger-group-add-client')
                                        );
                }
            }
        ]
        
    };
    
    var JoinChatLogin = {
        id: 'messenger-groupchat',
        layout: 'anchor',
        closeAction: 'close',
        plain: true,
        width: 300,
        height: 150,
        minHeight: 150,
        title: app.i18n._('Join Groupchat'),
        modal: true,
        items: {
            xtype: 'form',
            border: false,
            items: [
                {
                    xtype: 'textfield',
                    id: 'messenger-groupchat-identity',
                    fieldLabel: app.i18n._('Identity'),
                    disabled: true
                },
                {
                    xtype: 'textfield',
                    id: 'messenger-groupchat-host',
                    fieldLabel: app.i18n._('Host')
                },
                {
                    xtype: 'textfield',
                    id: 'messenger-groupchat-room',
                    fieldLabel: app.i18n._('Room')
                },
                {
                    xtype: 'textfield',
                    id: 'messenger-groupchat-nick',
                    fieldLabel: app.i18n._('Nickname')
                },
                {
                    xtype: 'textfield',
                    inputType: 'password',
                    id: 'messenger-groupchat-pwd',
                    fieldLabel: app.i18n._('Password')
                },
                {
                    xtype: 'button',
                    text: app.i18n._('Join'),
                    listeners: {
                        click: function (ev, data) {
                            Tine.Messenger.Groupie.MUCLogin(Ext.getCmp('messenger-groupchat'));
                        }
                    }
                }
            ],

            keys: [
                {
                    key: [Ext.EventObject.ENTER],
                    handler: function () {
                        Tine.Messenger.Groupie.MUCLogin(Ext.getCmp('messenger-groupchat'));
                    }
                }
            ]
        }
    };
    
    var config = {
        autoScroll: true
    };
    
    switch(window){
        case Tine.Messenger.WindowLayout.Buddy:
            config = AddBuddyLayout;
            break;
        case Tine.Messenger.WindowLayout.Groups:
            config = AddGroupLayout;
            break;
        case Tine.Messenger.WindowLayout.Chat:
            config = JoinChatLogin;
            break;
    }
    
    Tine.Messenger.WindowConfig.superclass.constructor.call(this,
        Ext.apply(config, args || {})
    );
};

Ext.extend(Tine.Messenger.WindowConfig, Ext.Window);
