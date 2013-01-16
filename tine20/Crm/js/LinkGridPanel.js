/*
 * Tine 2.0
 * 
 * @package     Crm
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * TODO         add to extdoc
 */
 
Ext.ns('Tine.Crm.LinkGridPanel');

/**
 * @namespace   Tine.Crm.LinkGridPanel
 * 
 * TODO         move change contact type functions
 */
Tine.Crm.LinkGridPanel.initActions = function() {
    
    var app = Tine.Tinebase.appMgr.get(this.recordClass.getMeta('appName')); 
    if (! app) {
        return;
    }
        
    if (app.i18n) {
        var recordName = app.i18n.n_(
            this.recordClass.getMeta('recordName'), this.recordClass.getMeta('recordsName'), 1
        );
    } else {
        var recordName = this.recordClass.getMeta('recordName');
    }

    var addActionText = app.addButtonText && app.i18n ? app.i18n._hidden(app.addButtonText) : String.format(this.app.i18n._('Add new {0}'), recordName);
    this.actionAdd = new Ext.Action({
        requiredGrant: 'editGrant',
        text: addActionText,
        tooltip: addActionText,
        iconCls: 'action_add',
        disabled: ! (this.record && this.record.get('container_id') 
            && this.record.get('container_id').account_grants 
            && this.record.get('container_id').account_grants.editGrant),
        scope: this,
        handler: function(_button, _event) {
            var editWindow = this.recordEditDialogOpener({
                listeners: {
                    scope: this,
                    'update': this.onUpdate
                }
            });
        }
    });
    
    this.actionUnlink = new Ext.Action({
        requiredGrant: 'editGrant',
        text: String.format(this.app.i18n._('Unlink {0}'), recordName),
        tooltip: String.format(this.app.i18n._('Unlink selected {0}'), recordName),
        disabled: true,
        iconCls: 'action_remove',
        onlySingle: true,
        scope: this,
        handler: function(_button, _event) {                       
            var selectedRows = this.getSelectionModel().getSelections();
            for (var i = 0; i < selectedRows.length; ++i) {
                this.store.remove(selectedRows[i]);
            }           
        }
    });
    
    this.actionEdit = new Ext.Action({
        requiredGrant: 'editGrant',
        text: String.format(this.app.i18n._('Edit {0}'), recordName),
        tooltip: String.format(this.app.i18n._('Edit selected {0}'), recordName),
        disabled: true,
        iconCls: 'actionEdit',
        onlySingle: true,
        scope: this,
        handler: function(_button, _event) {
            var selectedRows = this.getSelectionModel().getSelections();
            var record = selectedRows[0];
            // unset record id for new records
            if (record.phantom) {
                record.id = 0;
            }
            var editWindow = this.recordEditDialogOpener({
                record: record,
                listeners: {
                    scope: this,
                    'update': this.onUpdate
                }
            });
        }
    });

    // init toolbars and ctx menut / add actions
    this.bbar = [                
        this.actionAdd,
        this.actionUnlink
    ];
    
    this.actions = [
        this.actionEdit,
        this.actionUnlink
    ];
    
    if (this.otherActions) {
        this.actions = this.actions.concat(this.otherActions);
    }

    this.contextMenu = new Ext.menu.Menu({
        items: this.actions.concat(['-', this.actionAdd])
    });
    
    this.actions.push(this.actionAdd);
};

/**
 * init store
 * 
 */ 
Tine.Crm.LinkGridPanel.initStore = function() {
    
    this.store = new Ext.data.JsonStore({
        fields: (this.storeFields) ? this.storeFields : this.recordClass
    });

    // focus+select new record
    this.store.on('add', function(store, records, index) {
        (function() {
            if (this.rendered) {
                this.getView().focusRow(index);
                this.getSelectionModel().selectRow(index); 
            }
        }).defer(300, this);
    }, this);
};

/**
 * init ext grid panel
 * 
 * TODO         add grants for linked entries to disable EDIT?
 */
Tine.Crm.LinkGridPanel.initGrid = function() {
    this.cm = this.getColumnModel();
    
    this.selModel = new Ext.grid.RowSelectionModel({multiSelect:true});
    this.enableHdMenu = false;
    this.plugins = this.plugins || [];
    this.plugins.push(new Ext.ux.grid.GridViewMenuPlugin({}));

    // on selectionchange handler
    this.selModel.on('selectionchange', function(sm) {
        var rowCount = sm.getCount();
        var selectedRows = this.getSelectionModel().getSelections();
        if (selectedRows.length > 0) {
            var selectedRecord = selectedRows[0];
        }
        if (this.record && (this.record.get('container_id') && this.record.get('container_id').account_grants)) {
            for (var i=0; i < this.actions.length; i++) {
                this.actions[i].setDisabled(
                    ! this.record.get('container_id').account_grants.editGrant 
                    || (this.actions[i].initialConfig.onlySingle && rowCount != 1)
                    || (this.actions[i] == this.actionEdit && selectedRecord && selectedRecord.phantom == true)
                );
            }
        }
        
    }, this);
    
    // on rowcontextmenu handler
    this.on('rowcontextmenu', function(grid, row, e) {
        e.stopEvent();
        var selModel = grid.getSelectionModel();
        if(!selModel.isSelected(row)) {
            selModel.selectRow(row);
        }
        
        this.contextMenu.showAt(e.getXY());
    }, this);
    
    // doubleclick handler
    this.on('rowdblclick', function(grid, row, e) {
        var selectedRows = grid.getSelectionModel().getSelections();
        record = selectedRows[0];
        if (! record.phantom && this.recordEditDialogOpener != Ext.emptyFn) {
            var editWindow = this.recordEditDialogOpener({
                record: record,
                listeners: {
                    scope: this,
                    'update': this.onUpdate
                }
            });
        }
    }, this);
};
