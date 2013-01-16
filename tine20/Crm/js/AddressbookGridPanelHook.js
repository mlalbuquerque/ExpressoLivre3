/*
 * Tine 2.0
 * 
 * @package     Crm
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.ns('Tine.Crm');

/**
 * @namespace   Tine.Crm
 * @class       Tine.Crm.AddressbookGridPanelHook
 * 
 * <p>Crm Addressbook Hook</p>
 * <p>
 * </p>
 * 
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * 
 * @constructor
 */
Tine.Crm.AddressbookGridPanelHook = function(config) {
    
    Tine.log.info('initialising crm addressbook hooks');
    Ext.apply(this, config);
    
    var text = this.app.i18n.n_hidden(Tine.Crm.Model.Lead.prototype.recordName, Tine.Crm.Model.Lead.prototype.recordsName, 1);
    
    // NOTE: due to the action updater this action is bound the the adb grid only!
    this.newLeadAction = new Ext.Action({
        actionType: 'new',
        requiredGrant: 'readGrant',
        allowMultiple: true,
        text: text,
        iconCls: this.app.getIconCls(),
        scope: this,
        handler: this.onNewLead,
        listeners: {
            scope: this,
            render: this.onRender
        }
    });
    
    this.addLeadAction = new Ext.Action({
        actionType: 'add',
        requiredGrant: 'readGrant',
        allowMultiple: true,
        text: text,
        iconCls: this.app.getIconCls(),
        scope: this,
        handler: this.onAddLead,
        listeners: {
            scope: this,
            render: this.onRender
        }
    });    
    
    // register in contextmenu
    Ext.ux.ItemRegistry.registerItem('Addressbook-GridPanel-ContextMenu-New', this.newLeadAction, 80);
    Ext.ux.ItemRegistry.registerItem('Addressbook-GridPanel-ContextMenu-Add', this.addLeadAction, 80);

};

Ext.apply(Tine.Crm.AddressbookGridPanelHook.prototype, {
    
    /**
     * @property app
     * @type Tine.Crm.Application
     * @private
     */
    app: null,
    
    /**
     * @property newLeadAction
     * @type Tine.widgets.ActionUpdater
     * @private
     */
    newLeadAction: null,

    /**
     * @property addLeadAction
     * @type Tine.widgets.ActionUpdater
     * @private
     */
    addLeadAction: null,    
    
    /**
     * @property ContactGridPanel
     * @type Tine.Addressbook.ContactGridPanel
     * @private
     */
    ContactGridPanel: null,
    
    /**
     * get addressbook contact grid panel
     */
    getContactGridPanel: function() {
        if (! this.ContactGridPanel) {
            this.ContactGridPanel = Tine.Tinebase.appMgr.get('Addressbook').getMainScreen().getCenterPanel();
        }
        
        return this.ContactGridPanel;
    },
    
    /**
     * compose an email to selected contacts
     * 
     * @param {Button} btn 
     */
    onNewLead: function(btn) {
        var contacts = this.getContactGridPanel().grid.getSelectionModel().getSelections(),
            leadData = Tine.Crm.Model.Lead.getDefaultData();
        
        leadData.relations = [].concat(leadData.relations);
        Ext.each(contacts, function(contact) {
            leadData.relations.push({
                type: 'customer',
                related_record: contact.data
            });
        }, this);
        
        
        Tine.Crm.LeadEditDialog.openWindow({
            record: new Tine.Crm.Model.Lead(leadData, 0)
        });
    },
    
    onAddLead: function(btn) {
        var contacts = this.getSelectionsAsArray();
        Tine.Crm.AddToLeadPanel.openWindow({attendee: contacts});
    },

    /**
     * gets the current selection as an array 
     */
    getSelectionsAsArray: function() {
        var contacts = this.getContactGridPanel().grid.getSelectionModel().getSelections(),
            cont = [];
            
        Ext.each(contacts, function(contact) {
           if(contact.data) cont.push(contact.data);
        });
        
        return cont;
    },
    
    /**
     * add to action updater the first time we render
     */
    onRender: function() {
        var actionUpdater = this.getContactGridPanel().actionUpdater,
            registeredActions = actionUpdater.actions;
            
        if (registeredActions.indexOf(this.addLeadAction) < 0) {
            actionUpdater.addActions([this.addLeadAction]);
        }
        if (registeredActions.indexOf(this.newLeadAction) < 0) {
            actionUpdater.addActions([this.newLeadAction]);
        }        
    }

});
