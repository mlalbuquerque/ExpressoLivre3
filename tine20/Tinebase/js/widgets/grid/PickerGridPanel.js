/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.widgets.grid');

/**
 * Picker GridPanel
 * 
 * @namespace   Tine.widgets.grid
 * @class       Tine.widgets.grid.PickerGridPanel
 * @extends     Ext.grid.GridPanel
 * 
 * <p>Picker GridPanel</p>
 * <p><pre>
 * </pre></p>
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @param       {Object} config
 * @constructor
 * Create a new Tine.widgets.grid.PickerGridPanel
 */
Tine.widgets.grid.PickerGridPanel = Ext.extend(Ext.grid.EditorGridPanel, {
    /**
     * @cfg {bool}
     * enable bottom toolbar
     */
    enableBbar: true,

    /**
     * @cfg {bool}
     * enable top toolbar (with search combo)
     */
    enableTbar: false,
    
    /**
     * store to hold records
     * 
     * @type Ext.data.Store
     * @property store
     */
    store: null,
    
    /**
     * record class
     * @cfg {Tine.Tinebase.data.Record} recordClass
     */
    recordClass: null,
    
    /**
     * defaults for new records of this.recordClass
     * @cfg {Object} recordClass
     */
    recordDefaults: null,
    
    /**
     * record class
     * @cfg {Tine.Tinebase.data.Record} recordClass
     */
    searchRecordClass: null,
    
    /**
     * search combo class
     * @cfg {} searchComboClass
     */
    searchComboClass: null,
    
    /**
     * search combo config
     * @cfg {} searchComboConfig
     */
    searchComboConfig: null,
    
    /**
     * is the row selected after adding?
     * @type Boolean
     */
    selectRowAfterAdd: true,
    
    /**
     * is the row highlighted after adding?
     * @type Boolean
     */
    highlightRowAfterAdd: false,
    
    /**
     * @type Ext.Menu
     * @property contextMenu
     */
    contextMenu: null,
    
    /**
     * @cfg {Array} contextMenuItems
     * additional items for contextMenu
     */
    contextMenuItems: null,
    
    /**
     * @cfg {Array} Array of column's config objects where the config options are in
     */
    configColumns: null,
    
    /**
     * @private
     */
    initComponent: function() {
        this.contextMenuItems = (this.contextMenuItems !== null) ? this.contextMenuItems : [];
        this.configColumns = (this.configColumns !== null) ? this.configColumns : [];
        this.searchComboConfig = this.searchComboConfig || {};
        
        this.initStore();
        this.initActionsAndToolbars();
        this.initGrid();
        
        Tine.widgets.grid.PickerGridPanel.superclass.initComponent.call(this);
    },

    /**
     * init store
     * @private
     */
    initStore: function() {
        
        if (this.store === null) {
            this.store = new Ext.data.SimpleStore({
                fields: this.recordClass
            });
        }
        
        // focus+select new record
        this.store.on('add', function(store, records, index) {
            (function() {
                if (this.rendered) {
                    if (this.selectRowAfterAdd) {
                        this.getView().focusRow(index);
                        this.getSelectionModel().selectRow(index);
                    } else if (this.highlightRowAfterAdd && records.length === 1){
                        // some eyecandy
                        var row = this.getView().getRow(index);
                        Ext.fly(row).highlight();
                    }
                }
            }).defer(300, this);
        }, this);
    },

    /**
     * init actions and toolbars
     */
    initActionsAndToolbars: function() {
        
        this.actionRemove = new Ext.Action({
            text: _('Remove record'),
            disabled: true,
            scope: this,
            handler: this.onRemove,
            iconCls: 'action_deleteContact'
        });
        
        var contextItems = [this.actionRemove];
        this.contextMenu = new Ext.menu.Menu({
            items: contextItems.concat(this.contextMenuItems)
        });
        
        if (this.enableBbar) {
            this.bbar = new Ext.Toolbar({
                items: [
                    this.actionRemove
                ]
            });
        }

        if (this.enableTbar) {
            this.tbar = new Ext.Toolbar({
                items: [
                    this.getSearchCombo()
                ],
                listeners: {
                    scope: this,
                    resize: this.onTbarResize
                }
            });
        }
    },
    
    onTbarResize: function(tbar) {
        if (tbar.items.getCount() == 1) {
            var combo = tbar.items.get(0),
                gridWidth = this.getGridEl().getWidth(),
                offsetWidth = combo.getEl().getLeft() - this.getGridEl().getLeft();
            
            if (tbar.items.getCount() == 1) {
                tbar.items.get(0).setWidth(gridWidth - offsetWidth);
            }
        }
    },
    
    /**
     * init grid (column/selection model, ctx menu, ...)
     */
    initGrid: function() {
        this.cm = this.getColumnModel();
        
        this.selModel = new Ext.grid.RowSelectionModel({multiSelect:true});
        
        // remove non-plugin config columns
        var nonPluginColumns = [];
        for (var i=0; i < this.configColumns.length; i++) {
            if (!this.configColumns[i].init || typeof(this.configColumns[i].init) != 'function') {
                nonPluginColumns.push(this.configColumns[i]);
            }
        }
        for (var i=0; i < nonPluginColumns.length; i++) {
            this.configColumns.remove(nonPluginColumns[i]);
        }
        this.plugins = this.configColumns;
    
        // on selectionchange handler
        this.selModel.on('selectionchange', function(sm) {
            var rowCount = sm.getCount();
            this.actionRemove.setDisabled(rowCount == 0);
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
    },
    
    /**
     * @return {Tine.Tinebase.widgets.form.RecordPickerComboBox|this.searchComboClass}
     */
    getSearchCombo: function() {
        var searchComboClass = (this.searchComboClass !== null) ? this.searchComboClass : Tine.Tinebase.widgets.form.RecordPickerComboBox;
        
        return new searchComboClass(Ext.apply({
            recordStore: this.store,
            blurOnSelect: true,
            recordClass: (this.searchRecordClass !== null) ? this.searchRecordClass : this.recordClass,
            newRecordClass: this.recordClass,
            newRecordDefaults: this.recordDefaults,
            emptyText: _('Search for records ...'),
            onSelect: this.onAddRecordFromCombo
        }, this.searchComboConfig));        
    },
    
    /**
     * @param {Record} recordToAdd
     * 
     * TODO make reset work correctly -> show emptyText again
     */
    onAddRecordFromCombo: function(recordToAdd) {
        var record = new this.newRecordClass(Ext.applyIf(recordToAdd, this.newRecordDefaults), recordToAdd.id);
        
        // check if already in
        if (! this.recordStore.getById(record.id)) {
            this.recordStore.add([record]);
        }
        this.collapse();
        this.clearValue();
        this.reset();
    },
    
    /**
     * remove handler
     * 
     * @param {} button
     * @param {} event
     */
    onRemove: function(button, event) {                       
        var selectedRows = this.getSelectionModel().getSelections();
        for (var i = 0; i < selectedRows.length; ++i) {
            this.store.remove(selectedRows[i]);
        }           
    },
    
    /**
     * key down handler
     * @private
     */
    onKeyDown: function(e){
        if (e.ctrlKey) {
            switch (e.getKey()) {
                case e.A:
                    // select all records
                    this.getSelectionModel().selectAll(true);
                    e.preventDefault();
                    break;
            }
        } else {
            switch (e.getKey()) {
                case e.DELETE:
                    // delete selected record(s)
                    this.onRemove();
                    break;
            }
        }
    }
});

Ext.reg('wdgt.pickergrid', Tine.widgets.grid.PickerGridPanel);
