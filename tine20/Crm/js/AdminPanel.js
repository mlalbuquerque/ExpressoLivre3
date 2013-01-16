/*
 * Tine 2.0
 * 
 * @package     Crm
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

Ext.namespace('Tine.Crm');

/**
 * admin settings panel
 * 
 * @namespace   Tine.Crm
 * @class       Tine.Crm.AdminPanel
 * @extends     Tine.widgets.dialog.EditDialog
 * 
 * <p>Crm Admin Panel</p>
 * <p><pre>
 * TODO         generalize this
 * TODO         revert/rollback changes onCancel
 * </pre></p>
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @param       {Object} config
 * @constructor
 * Create a new Tine.Crm.AdminPanel
 */
Tine.Crm.AdminPanel = Ext.extend(Tine.widgets.dialog.EditDialog, {
    /**
     * @private
     */
    //windowNamePrefix: 'LeadEditWindow_',
    appName: 'Crm',
    recordClass: Tine.Crm.Model.Settings,
    recordProxy: Tine.Crm.settingsBackend,
    evalGrants: false,
    
    /**
     * overwrite update toolbars function (we don't have record grants yet)
     * @private
     */
    updateToolbars: function() {
    },
    
    /**
     * executed after record got updated from proxy
     * 
     * @private
     */
    onRecordLoad: function() {
        if (! this.record.get('default_leadstate_id') ) {
            this.record.set('default_leadstate_id', this.record.data.defaults.leadstate_id);
            this.record.set('default_leadsource_id', this.record.data.defaults.leadsource_id);
            this.record.set('default_leadtype_id', this.record.data.defaults.leadtype_id);
        }
        
        if (this.fireEvent('load', this) !== false) {
            this.getForm().loadRecord(this.record);
            this.updateToolbars(this.record, this.recordClass.getMeta('containerProperty'));
            
            this.loadMask.hide();
        }
    },
    
    /**
     * executed when record gets updated from form
     * - add attachments to record here
     * 
     * @private
     * 
     */
    onRecordUpdate: function() {
        Tine.Crm.AdminPanel.superclass.onRecordUpdate.call(this);
        
        var defaults = {
            leadstate_id: this.record.get('default_leadstate_id'), 
            leadsource_id: this.record.get('default_leadsource_id'), 
            leadtype_id: this.record.get('default_leadtype_id')
        };
        
        this.record.set('defaults', defaults);
        
        // save leadstate / commit store
        this.record.set('leadstates', this.getFromStore(this.leadstatePanel.store));
        this.record.set('leadtypes', this.getFromStore(this.leadtypePanel.store));
        this.record.set('leadsources', this.getFromStore(this.leadsourcePanel.store));
    },
    
    /**
     * get values from store (as array)
     * 
     * @param {Ext.data.JsonStore} store
     * @return {Array}
     */
    getFromStore: function(store) {
        var result = [];
        store.each(function(record) {                     
            result.push(record.data);
        }, this);
        store.commitChanges();
        
        return result;
    },
    
    /**
     * returns dialog
     * 
     * NOTE: when this method gets called, all initalisation is done.
     * 
     * @return {Object}
     * @private
     */
    getFormItems: function() {
        
        this.leadstatePanel = new Tine.Crm.LeadState.GridPanel({
            title: this.app.i18n._('Leadstates')
        });
        
        this.leadtypePanel = new Tine.Crm.LeadType.GridPanel({
            title: this.app.i18n._('Leadtypes')
        });
        
        this.leadsourcePanel = new Tine.Crm.LeadSource.GridPanel({
            title: this.app.i18n._('Leadsources')
        });
        
        return {
            xtype: 'tabpanel',
            activeTab: 0,
            border: true,
            items: [{
                title: this.app.i18n._('Defaults'),
                autoScroll: true,
                border: false,
                frame: true,
                xtype: 'columnform',
                formDefaults: {
                    xtype:'combo',
                    anchor: '90%',
                    labelSeparator: '',
                    columnWidth: 1,
                    valueField:'id',
                    typeAhead: true,
                    mode: 'local',
                    triggerAction: 'all',
                    editable: false,
                    allowBlank: false,
                    forceSelection: true
                },
                items: [[{
                    fieldLabel: this.app.i18n._('Leadstate'), 
                    name:'default_leadstate_id',
                    store: Tine.Crm.LeadState.getStore(),
                    displayField:'leadstate',
                    lazyInit: false,
                    value: (Tine.Crm.LeadState.getStore().getCount() > 0) ? Tine.Crm.LeadState.getStore().getAt(0).id : null
                }, {
                    fieldLabel: this.app.i18n._('Leadsource'), 
                    name:'default_leadsource_id',
                    store: Tine.Crm.LeadSource.getStore(),
                    displayField:'leadsource',
                    lazyInit: false,
                    value: (Tine.Crm.LeadSource.getStore().getCount() > 0) ? Tine.Crm.LeadSource.getStore().getAt(0).id : null
                }, {
                    fieldLabel: this.app.i18n._('Leadtype'), 
                    name:'default_leadtype_id',
                    store: Tine.Crm.LeadType.getStore(),
                    displayField:'leadtype',
                    lazyInit: false,
                    value: (Tine.Crm.LeadType.getStore().getCount() > 0) ? Tine.Crm.LeadType.getStore().getAt(0).id : null
                }]]
            }, 
                this.leadstatePanel,
                this.leadtypePanel,
                this.leadsourcePanel
            ]            
        };                
    } // end of getFormItems
});

/**
 * admin panel on update function
 * 
 * TODO         update registry without reloading the mainscreen
 */
Tine.Crm.AdminPanel.onUpdate = function() {
    // reload mainscreen to make sure registry gets updated
    window.location = window.location.href.replace(/#+.*/, '');
}

/**
 * Crm admin settings popup
 * 
 * @param   {Object} config
 * @return  {Ext.ux.Window}
 */
Tine.Crm.AdminPanel.openWindow = function (config) {
    var id = (config.record && config.record.id) ? config.record.id : 0;
    var window = Tine.WindowFactory.getWindow({
        width: 600,
        height: 400,
        name: Tine.Crm.AdminPanel.prototype.windowNamePrefix + id,
        contentPanelConstructor: 'Tine.Crm.AdminPanel',
        contentPanelConstructorConfig: config
    });
    return window;
};

Ext.namespace('Tine.Crm.Admin');

/**
 * @namespace   Tine.Crm.Admin
 * @class       Tine.Crm.Admin.QuickaddGridPanel
 * @extends     Tine.widgets.grid.QuickaddGridPanel
 * 
 * admin config option quickadd grid panel
 */
Tine.Crm.Admin.QuickaddGridPanel = Ext.extend(Tine.widgets.grid.QuickaddGridPanel, {

    /**
     * @private
     */
    initComponent: function() {
        this.app = this.app ? this.app : Tine.Tinebase.appMgr.get('Crm');

        Tine.Crm.Admin.QuickaddGridPanel.superclass.initComponent.call(this);
    }
});
