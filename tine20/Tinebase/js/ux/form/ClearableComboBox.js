/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.ns('Ext.ux', 'Ext.ux.form');

/**
 * A ComboBox with a secondary trigger button that clears the contents of the ComboBox
 * 
 * @namespace   Ext.ux.form
 * @class       Ext.ux.form.ClearableComboBox
 * @extends     Ext.form.ComboBox
 */
Ext.ux.form.ClearableComboBox = Ext.extend(Ext.form.ComboBox, {
    initComponent : function(){
        Ext.ux.form.ClearableComboBox.superclass.initComponent.call(this);

        this.triggerConfig = {
            tag:'span', cls:'x-form-twin-triggers', style:'padding-right:2px',  // padding needed to prevent IE from clipping 2nd trigger button
            cn:[
                {tag: "img", src: Ext.BLANK_IMAGE_URL, cls: "x-form-trigger x-form-clear-trigger"},            
                {tag: "img", src: Ext.BLANK_IMAGE_URL, cls: "x-form-trigger"}                            
            ]
        };
    },

    getTrigger : function(index){
        return this.triggers[index];
    },

    initTrigger : function(){
        var ts = this.trigger.select('.x-form-trigger', true);
        this.wrap.setStyle('overflow', 'hidden');
        var triggerField = this;
        ts.each(function(t, all, index){
            t.hide = function(){
                var w = triggerField.wrap.getWidth();
                this.dom.style.display = 'none';
                triggerField.el.setWidth(w-triggerField.trigger.getWidth());
            };
            t.show = function(){
                var w = triggerField.wrap.getWidth();
                this.dom.style.display = '';
                triggerField.el.setWidth(w-triggerField.trigger.getWidth());
            };
            var triggerIndex = 'Trigger'+(index+1);

            if(this['hide'+triggerIndex]){
                t.dom.style.display = 'none';
            }
            t.on("click", this['on'+triggerIndex+'Click'], this, {preventDefault:true});
            t.addClassOnOver('x-form-trigger-over');
            t.addClassOnClick('x-form-trigger-click');
        }, this);
        this.triggers = ts.elements;
        this.triggers[0].hide();                   
    },
    
    // clear contents of combobox
    onTrigger1Click : function() {
        this.clearValue();
    },
    
    /**
     * clear value
     */
    clearValue: function() {
        Ext.ux.form.ClearableComboBox.superclass.clearValue.apply(this, arguments);
        this.fireEvent('select', this, this.getRawValue(), this.startValue);
        this.startValue = this.getRawValue();
        this.triggers[0].hide();
    },
    
    // pass to original combobox trigger handler
    onTrigger2Click : function() {
        this.onTriggerClick();
    },
    // show clear triger when item got selected
    onSelect: function(combo, record, index) {
        this.triggers[0].show();
        Ext.ux.form.ClearableComboBox.superclass.onSelect.call(this, combo, record, index);
        this.startValue = this.getValue();
    },
    
    setValue: function(value) {
        Ext.ux.form.ClearableComboBox.superclass.setValue.call(this, value);
        if (value) {
            this.triggers[0].show();
        }
    }
});
Ext.reg('extuxclearablecombofield', Ext.ux.form.ClearableComboBox);
