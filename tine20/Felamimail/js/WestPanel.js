/* 
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Tine.Felamimail');

/**
 * Felamimail west panel
 * 
 * @namespace   Tine.Felamimail
 * @class       Tine.Felamimail.WestPanel
 * @extends     Tine.widgets.mainscreen.WestPanel
 * 
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * 
 * @constructor
 * @xtype       tine.Felamimail.mainscreenwestpanel
 */
Tine.Felamimail.WestPanel = Ext.extend(Tine.widgets.mainscreen.WestPanel, {
    
    cls: 'cal-tree',

    getAdditionalItems: function() {
        return [Ext.apply({
            title: this.app.i18n._('Mail Archiver'),
            forceLayout: true,
            border: false,
            layout: 'hbox',
            layoutConfig: {
                align:'middle'
            },
            defaults: {border: false},
            items: [{
                flex: 1
            }, this.getMailArchiver(), {
                flex: 1
            }]
        }, this.defaults)];
    },
    
    getMailArchiver: function() {
        if (! this.mailArchiver) {
            this.mailArchiver = new Tine.Felamimail.MATreePanel({
                app: this.app, 
                width: 200,
                id : 'mail-archiver-main'
            });
        }
        
        return this.mailArchiver;
    }
});
