/*
 * Tine 2.0
 * 
 * @package     Felamimail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.namespace('Tine.Felamimail');

/**
 * @namespace   Tine.Felamimail
 * @class       Tine.Felamimail.AclsGrid
 * @extends     Tine.widgets.dialog.EditDialog
 * 
 * <p>Account Edit Dialog</p>
 * <p>
 * </p>
 * 
 * @author      Bruno Vieira Costa <bruno.vieira-costa@serpro.gov.br>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * 
 * @param       {Object} config
 * @constructor
 * Create a new Tine.Felamimail.AclsGrid
 * 
 */
Tine.Felamimail.AclsGrid = Ext.extend(Tine.widgets.account.PickerGridPanel, {

    /**
     * Tine.widgets.account.PickerGridPanel config values
     */
    selectType: 'user',
    selectTypeDefault: 'user',
    hasAccountPrefix: true,
    recordClass: Tine.Felamimail.Model.Acl,
    
    /**
     * @private
     */
    initComponent: function () {
        this.initColumns();
        
        Tine.Felamimail.AclsGrid.superclass.initComponent.call(this);
    },
    
    initColumns: function() {
        this.configColumns = [
            new Ext.ux.grid.CheckColumn({
                header: _('Read'),
                tooltip: _('Read messages from folders'),
                dataIndex: 'readacl',
                width: 55
            }),
            new Ext.ux.grid.CheckColumn({
                header: _('Write'),
                tooltip: _('Write and delete messages from folders'),
                dataIndex: 'writeacl',
                width: 55
            }),
            new Ext.ux.grid.CheckColumn({
                header: _('Send as'),
                tooltip: _('Send as folder owner'),
                dataIndex: 'sendacl',
                width: 55
            })            
        ];
    }
});