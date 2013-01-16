/*
 * Tine 2.0
 * 
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <alex@stintzing.net>
 * @copyright   Copyright (c) 2009-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

Ext.ns('Tine.Calendar');

/**
 * @namespace   Tine.Calendar
 * @class       Tine.Calendar.AddToEventPanel
 * @extends     Ext.FormPanel
 * @author      Alexander Stintzing <alex@stintzing.net>
 */
Tine.Calendar.AddToEventPanel = Ext.extend(Ext.FormPanel, {
    appName : 'Calendar',

    layout : 'fit',
    border : false,
    cls : 'tw-editdialog',    

    labelAlign : 'top',

    anchor : '100% 100%',
    deferredRender : false,
    buttonAlign : null,
    bufferResize : 500,

    /**
     * init component
     */
    initComponent: function() {

        if (!this.app) {
            this.app = Tine.Tinebase.appMgr.get(this.appName);
        }

        Tine.log.debug('initComponent: appName: ', this.appName);
        Tine.log.debug('initComponent: app: ', this.app);

        // init actions
        this.initActions();
        // init buttons and tbar
        this.initButtons();

        // get items for this dialog
        this.items = this.getFormItems();

        Tine.Calendar.AddToEventPanel.superclass.initComponent.call(this);
    },

    /**
     * init actions
     */
    initActions: function() {
        this.action_cancel = new Ext.Action({
            text : this.app.i18n._('Cancel'),
            minWidth : 70,
            scope : this,
            handler : this.onCancel,
            iconCls : 'action_cancel'
        });

        this.action_update = new Ext.Action({
            text : this.app.i18n._('OK'),
            minWidth : 70,
            scope : this,
            handler : this.onUpdate,
            iconCls : 'action_saveAndClose'
        });
    },

    /**
     * init buttons
     */
    initButtons : function() {
        this.fbar = [ '->', this.action_cancel, this.action_update ];
    },  

    /**
     * is called when the component is rendered
     * @param {} ct
     * @param {} position
     */
    onRender : function(ct, position) {
        Tine.Calendar.AddToEventPanel.superclass.onRender.call(this, ct, position);

        // generalized keybord map for edit dlgs
        var map = new Ext.KeyMap(this.el, [ {
            key : [ 10, 13 ], // ctrl + return
            ctrl : true,
            fn : this.onSend,
            scope : this
        } ]);

    },

    /**
     * closes the window
     */
    onCancel: function() {
        this.fireEvent('cancel');
        this.purgeListeners();
        this.window.close();
    },

    /**
     * checks validity and marks invalid fields
     * returns true on valid
     * @return boolean
     */
    isValid: function() {

        var valid = true;

        if(this.searchBox.getValue() == '') {
            this.searchBox.markInvalid(this.app.i18n._('Please choose the Event to add the contacts to'));
            valid = false;
        }

        return valid;
    },

    /**
     * save record and close window
     */
    onUpdate : function() {
        if (this.isValid()) {
            var recordId = this.searchBox.getValue(), 
                record = this.searchBox.store.getById(recordId), 
                ms = this.app.getMainScreen(), 
                cp = ms.getCenterPanel(), 
                role = this.chooseRoleBox.getValue(), 
                status = this.chooseStatusBox.getValue();

            for (var index = 0; index < this.attendee.length; index++) {
                this.attendee[index].role = role;
                this.attendee[index].status = status;
            }
            // existing attendee
            var attendee = record.data.attendee;

            if (this.attendee.length > 0) {
                Ext.each(this.attendee, function(attender) {
                    var ret = true;
                    Ext.each(attendee, function(already) {
                        if (already.user_id.id == attender.id) {
                            ret = false;
                            return false;
                        }
                    }, this);

                    if (ret) {
                        var att = new Tine.Calendar.Model.Attender(Tine.Calendar.Model.Attender.getDefaultData(), 'new-' + Ext.id());
                        att.set('user_id', attender);
                        if (!attender.account_id) {
                            att.set('status', attender.status);
                            att.set('status_authkey', 1);
                        }
                        att.set('role', attender.role);
                        attendee.push(att.data);
                    }
                }, this);
                record.set('attendee', attendee);
            }

            cp.onEditInNewWindow.call(cp, 'edit', null, record);
            // close this window
            this.onCancel();
        }
    },

    /**
     * create and return form items
     * @return Object
     */
    getFormItems: function() {

        this.searchBox = Tine.widgets.form.RecordPickerManager.get('Calendar', 'Event');    

        return {
            border: false,
            frame:  false,
            layout: 'border',

            items: [{
                region: 'center',
                border: false,
                frame:  false,
                layout : {
                    align: 'stretch',
                    type:  'vbox'
                    },
                items: [{
                    layout:  'form',
                    margins: '10px 10px',
                    border:  false,
                    frame:   false,
                    items: [ 
                        this.searchBox,
                        {
                            fieldLabel: this.app.i18n._('Role'),
                            emptyText: this.app.i18n._('Select Role'),
                            xtype: 'widget-keyfieldcombo',
                            app:   'Calendar',
                            value: 'REQ',
                            anchor : '100% 100%',
                            margins: '10px 10px',
                            keyFieldName: 'attendeeRoles',
                            ref: '../../../chooseRoleBox'
                        },{
                            fieldLabel: this.app.i18n._('Status'),
                            emptyText: this.app.i18n._('Select Status'),
                            xtype: 'widget-keyfieldcombo',
                            app:   'Calendar',
                            value: 'NEEDS-ACTION',
                            anchor : '100% 100%',
                            margins: '10px 10px',
                            keyFieldName: 'attendeeStatus',
                            ref: '../../../chooseStatusBox'
                        }
                         ] 
                    }]

            }]
        };
    } 
});

Tine.Calendar.AddToEventPanel.openWindow = function(config) {
    var window = Tine.WindowFactory.getWindow({
        modal: true,
        title : String.format(Tine.Tinebase.appMgr.get('Calendar').i18n._('Adding {0} Attendee to event'), config.attendee.length),
        width : 240,
        height : 250,
        contentPanelConstructor : 'Tine.Calendar.AddToEventPanel',
        contentPanelConstructorConfig : config
    });
    return window;
};