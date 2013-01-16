/* 
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Tine.Tinebase.widgets.form');

Tine.Tinebase.widgets.form.AutoCompleteField = Ext.extend(Ext.form.ComboBox, {
    
    /**
     * @cfg {String} property 
     * property to autocomplete (required)
     */
    property: null,
    
    /**
     * @cfg {Ext.data.Record} recordClass
     * record definition class  (required)
     */
    recordClass: null,
    
    /**
     * config
     */
    forceSelection: false,
    triggerAction: 'all',
    minChars: 3,
    queryParam: 'startswith',
    hideTrigger: true,
    
    /**
     * @private
     */
    initComponent: function() {
        if (! this.property && this.name) {
            this.property = this.name;
        }
        
        this.displayField = this.valueField = this.property;
        
        this.store = new Ext.data.JsonStore({
            fields: [this.property],
            baseParams: {
                method: this.recordClass.getMeta('appName') + '.autoComplete' + this.recordClass.getMeta('modelName') + 'Property',
                property: this.property,
                sort: this.property,
                dir: 'ASC'
            },
            root: 'results',
            totalProperty: 'totalcount'
        });
        
        
        Tine.Tinebase.widgets.form.AutoCompleteField.superclass.initComponent.call(this);
    }
});

Ext.reg('tine.widget.field.AutoCompleteField', Tine.Tinebase.widgets.form.AutoCompleteField);
