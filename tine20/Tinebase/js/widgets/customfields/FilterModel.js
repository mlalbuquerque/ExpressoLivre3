/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Tine.widgets', 'Tine.widgets.customfields');

/**
 * @namespace   Tine.widgets.customfields
 * @class       Tine.widgets.customfields.FilterModel
 * @extends     Tine.widgets.grid.FilterModel
 */
Tine.widgets.customfields.FilterModel = Ext.extend(Tine.widgets.grid.FilterModel, {
    /**
     * @cfg {Tine.Tinebase.Application} app
     */
    app: null,
    
    /**
     * @cfg {Record} cfConfig
     */
    cfConfig: null,
    
    valueType: 'customfield',
    
    /**
     * @private
     */
    initComponent: function() {
        this.field = 'customfield:' + this.cfConfig.id;
        this.cfDefinition = this.cfConfig.get('definition');
        this.label =  this.cfDefinition.label;
        
        
        Tine.widgets.customfields.FilterModel.superclass.initComponent.call(this);
        
        // @todo map cf types to valueType
        if (['keyfield', 'bool', 'boolean'].indexOf(Ext.util.Format.lowercase(this.cfDefinition.type)) > -1) {
            this.operators = ['equals', 'not'];
            this.defaultOperator = 'equals';
            this.valueRenderer = this.cfValueRenderer;
        }
        
    },
    
    /**
     * cf value renderer
     * 
     * @param {Ext.data.Record} filter line
     * @param {Ext.Element} element to render to 
     */
    cfValueRenderer: function(filter, el) {
        
        // value
        var value = Tine.widgets.customfields.Field.get(this.app, this.cfConfig, {
            filter: filter,
            width: 200,
            id: 'tw-ftb-frow-valuefield-' + filter.id,
            value: filter.data.value ? filter.data.value : this.defaultValue,
            renderTo: el
        });
        value.on('specialkey', function(field, e){
             if(e.getKey() == e.ENTER){
                 this.onFiltertrigger();
             }
        }, this);
        //value.on('select', this.onFiltertrigger, this);
        
        return value;
    }
});
Tine.widgets.grid.FilterToolbar.FILTERS['tinebase.customfield'] = Tine.widgets.customfields.FilterModel;

