/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.Tinebase');

/**
 * Tine 2.0 jsclient main menu
 * 
 * @namespace   Tine.Tinebase
 * @class       Tine.Tinebase.MainMenu
 * @extends     Ext.Toolbar
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */
Tine.Tinebase.MainMenu = Ext.extend(Ext.Toolbar, {
    /**
     * @cfg {Boolean} showMainMenu
     */
    showMainMenu: false,    
    style: {'padding': '0px 2px'},
    cls: 'tbar-mainmenu',
    
    /**
     * @type Array
     * @property mainActions
     */
    mainActions: null,
    
    initComponent: function() {
        this.initActions();
        this.onlineStatus = new Ext.ux.ConnectionStatus({
            showIcon: false
        });
        
        this.items = this.getItems();
        
        var buttonTpl = new Ext.Template(
            '<table id="{4}" cellspacing="0" class="x-btn {3}"><tbody class="{1}">',
            '<tr><td class="x-btn-ml"><i>&#160;</i></td><td class="x-btn-mc"><em class="{2}" unselectable="on"><button type="{0}"></button></em></td><td class="x-btn-mr"><i>&#160;</i></td></tr>',
            '</tbody></table>'
        ).compile();
        
        Ext.each(this.items, function(item) {
            item.template = buttonTpl;
        }, this);
        
        this.supr().initComponent.call(this);
    },
    
    getItems: function() {
        return [{
            text: Tine.title,
            hidden: !this.showMainMenu,
            menu: {
                id: 'Tinebase_System_Menu', 
                items: this.getMainActions()
        }},
        '->', {
            text: String.format(_('User: {0}'), Tine.Tinebase.registry.get('currentAccount').accountDisplayName),
            menu: this.getUserActions(),
            menuAlign: 'tr-br'
        },
        this.onlineStatus,
        this.action_logout];
    },
    
    /**
     * returns all main actions
     * 
     * @return {Array}
     */
    getMainActions: function() {
        if (! this.mainActions) {
            this.mainActions = [
                this.action_aboutTine,
                '-',
                this.getUserActions(),
                '-',
                this.action_logout
            ];
            
            if (Tine.Tinebase.registry.get("version").buildType.match(/(DEVELOPMENT|DEBUG)/)) {
                this.mainActions.splice(1, 0, '-', this.action_showDebugConsole);
            }
        }
        
        return this.mainActions;
    },
    
    getUserActions: function() {
        if (! this.userActions) {
            this.userActions = [
                this.action_editProfile,
                this.action_showPreferencesDialog,
                this.action_changePassword,
                this.action_notificationPermissions,
                this.action_installChromeWebApp
            ];
        }
        return this.userActions;
    },
    
    /**
     * initialize actions
     * @private
     */
    initActions: function() {
        this.action_aboutTine = new Ext.Action({
            text: String.format(_('About {0}'), Tine.title),
            handler: this.onAboutTine20,
            iconCls: 'action_about'
        });
        
        this.action_showDebugConsole = new Ext.Action({
            text: _('Debug Console (Ctrl + F11)'),
            handler: Tine.Tinebase.common.showDebugConsole,
            iconCls: 'tinebase-action-debug-console'
        });
        
        this.action_showPreferencesDialog = new Ext.Action({
            text: _('Preferences'),
            disabled: false,
            handler: this.onEditPreferences,
            iconCls: 'action_adminMode'
        });

        this.action_editProfile = new Ext.Action({
            text: _('Edit Profile'),
            disabled: false,
            handler: this.onEditProfile,
            iconCls: 'tinebase-accounttype-user'
        });
        
        this.action_changePassword = new Ext.Action({
            text: _('Change password'),
            handler: this.onChangePassword,
            disabled: (Tine.Tinebase.registry.get('changepw') == '0'),
            iconCls: 'action_password'
        });
        
        this.action_logout = new Ext.Action({
            text: _('Logout'),
            tooltip:  String.format(_('Logout from {0}'), Tine.title),
            iconCls: 'action_logOut',
            handler: this.onLogout,
            scope: this
        });
        
        this.action_notificationPermissions = new Ext.Action({
            text: _('Allow desktop notifications'),
            tooltip:  _('Request permissions for webkit desktop notifications.'),
            iconCls: 'action_edit',
            disabled: ! (window.webkitNotifications && window.webkitNotifications.checkPermission() != 0),
            handler: function() {
                window.webkitNotifications.requestPermission();
            },
            scope: this
        });
        
        this.action_installChromeWebApp = new Ext.Action({
            text: _('Install web app'),
            tooltip:  _('Install Tine 2.0 as web app in your browser.'),
            iconCls: 'action_edit',
            disabled: ! (Ext.isChrome && chrome.app && !chrome.app.isInstalled),
            handler: function() {
                chrome.app.install();
            },
            scope: this
        });
    },
    
    /**
     * @private
     */
    onAboutTine20: function() {
        var aboutDialog = new Tine.Tinebase.AboutDialog();
        aboutDialog.show();
    },
    
    /**
     * @private
     */
    onChangePassword: function() {
        var passwordDialog = new Tine.Tinebase.PasswordChangeDialog();
        passwordDialog.show();
    },
    
    /**
     * @private
     */
    onEditPreferences: function() {
        Tine.widgets.dialog.Preferences.openWindow({});
    },

    /**
     * @private
     */
    onEditProfile: function() {
        Tine.widgets.dialog.Preferences.openWindow({
            initialCardName: 'Tinebase.UserProfile'
        });
    },
    
    /**
     * the logout button handler function
     * @private
     */
    onLogout: function() {
        if (Tine.Tinebase.registry.get('confirmLogout') != '0') {
            Ext.MessageBox.confirm(_('Confirm'), _('Are you sure you want to logout?'), function(btn, text) {
                if (btn == 'yes') {
                    this._doLogout();
                }
            }, this);
        } else {
            this._doLogout();
        }
    },
    
    /**
     * logout user & redirect
     */
    _doLogout: function() {
        Ext.MessageBox.wait(_('Logging you out...'), _('Please wait!'));
        Ext.Ajax.request( {
            params : {
                method : 'Tinebase.logout'
            },
            callback : function(options, Success, response) {
                // remove the event handler
                // the reload() trigers the unload event
                var redirect = (Tine.Tinebase.registry.get('redirectUrl'));
                if (redirect && redirect != '') {
                    window.location = Tine.Tinebase.registry.get('redirectUrl');
                } else {
                    window.location.reload();
                }
            }
        });        
    }
});

//Tine.Tinebase.MainMenu = function(config) {
//    this.isPrism = 'platform' in window;
//    Ext.apply(this, config);
//    // NOTE: Prism has no top menu yet ;-(
//    //       Only the 'icon menu' is implemented (right mouse button in dock(mac)/tray(other)
//    /*if (this.isPrism) {
//        window.platform.showNotification('title', 'text', 'images/clear-left.png');
//        this.menu = window.platform.icon().title = 'supertine';
//        //window.platform.menuBar.addMenu(“myMenu”);
//        
//        this.menu = window.platform.icon().menu;
//        window.platform.icon().menu.addMenuItem("myItem", "My Item", function(e) { window.alert("My Item!"); });
//        
//        var sub = this.menu.addSubmenu('mytest', 'mytest');
//        sub.addMenuItem('test', 'test', function() {alert('test');});
//        
//    } else { */
//        this.menu = new Ext.Toolbar({
//            id: this.id, 
//            height: this.height,
//            items: this.items
//        });
//        
//        return this.menu;
//    //}
//};


