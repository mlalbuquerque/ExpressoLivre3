/**
 * Tine 2.0
 * 
 * @package     Courses
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.namespace('Tine.Courses');

/**
 * Course grid panel
 */
Tine.Courses.CourseGridPanel = Ext.extend(Tine.widgets.grid.GridPanel, {
    // model generics
    recordClass: Tine.Courses.Model.Course,
    evalGrants: false,
    
    // grid specific
    defaultSortInfo: {field: 'name', direction: 'ASC'},
    gridConfig: {
        autoExpandColumn: 'name'
    },
    
    /**
     * init Tine.Courses.CourseGridPanel
     */
    initComponent: function() {
        this.recordProxy = Tine.Courses.coursesBackend;
        
        this.gridConfig.columns = this.getColumns();
        this.initFilterToolbar();
        
        this.plugins = this.plugins || [];
        this.plugins.push(this.filterToolbar);
        
        Tine.Courses.CourseGridPanel.superclass.initComponent.call(this);
    },
    
    /**
     * initialises filter toolbar
     */
    initFilterToolbar: function() {
        this.filterToolbar = new Tine.widgets.grid.FilterToolbar({
            filterModels: [
                {label: _('Quick search'),    field: 'query',       operators: ['contains']},
                {filtertype: 'foreignrecord', 
                    app: this.app,
                    foreignRecordClass: Tine.Tinebase.Model.Department,
                    ownField: 'type',
                    operators: ['equals']
                }
            ],
            defaultFilter: 'query',
            filters: [],
            plugins: [
                new Tine.widgets.grid.FilterToolbarQuickFilterPlugin()
            ]
        });
    },
    
    /**
     * returns cm
     */
    getColumns: function(){
        return [{
            id: 'name',
            header: this.app.i18n._("Name"),
            width: 200,
            sortable: true,
            dataIndex: 'name'
        },{
            id: 'type',
            header: this.app.i18n._("Type"),
            width: 150,
            sortable: true,
            dataIndex: 'type',
            renderer: this.courseTypeRenderer
        }
        // TODO make these configurable (http://forge.tine20.org/mantisbt/view.php?id=5884)
//        ,{
//            id: 'internet',
//            header: this.app.i18n._("Internet Access"),
//            width: 150,
//            sortable: true,
//            dataIndex: 'internet',
//            renderer: Tine.Tinebase.common.booleanRenderer,
//            hidden: true
//        },{
//            id: 'fileserver',
//            header: this.app.i18n._("Fileserver Access"),
//            width: 150,
//            sortable: true,
//            dataIndex: 'fileserver',
//            renderer: Tine.Tinebase.common.booleanRenderer,
//            hidden: true
//        }
        ];
    },
    
    /**
     * course type renderer
     * 
     * @param {} value
     * @return {}
     */
    courseTypeRenderer: function(value) {
        return (value.name);
    },
    
    /**
     * return additional tb items: internet/fileserver access on/off
     * 
     * @return {Array} with Ext.Action
     * 
     * TODO make these configurable (http://forge.tine20.org/mantisbt/view.php?id=5884)
     */
    getToolbarItems: function() {
        return [];
        
//        this.internetOnButton = new Ext.Action({
//            text: this.app.i18n._('Internet Access On'),
//            iconCls: 'action_enable',
//            scope: this,
//            disabled: true,
//            requiredGrant: 'readGrant',
//            allowMultiple: true,
//            type: 'internet',
//            access: 1,
//            handler: this.updateAccessHandler
//        });
//        this.internetOffButton = new Ext.Action({
//            text: this.app.i18n._('Internet Access Off'),
//            iconCls: 'action_disable',
//            scope: this,
//            disabled: true,
//            requiredGrant: 'readGrant',
//            allowMultiple: true,
//            type: 'internet',
//            access: 0,
//            handler: this.updateAccessHandler
//        });
//        this.fileserverOnButton = new Ext.Action({
//            text: this.app.i18n._('Fileserver Access On'),
//            iconCls: 'action_enable',
//            scope: this,
//            disabled: true,
//            requiredGrant: 'readGrant',
//            allowMultiple: true,
//            type: 'fileserver',
//            access: 1,
//            handler: this.updateAccessHandler
//        });
//        this.fileserverOffButton = new Ext.Action({
//            text: this.app.i18n._('Fileserver Access Off'),
//            iconCls: 'action_disable',
//            scope: this,
//            disabled: true,
//            requiredGrant: 'readGrant',
//            allowMultiple: true,
//            type: 'fileserver',
//            access: 0,
//            handler: this.updateAccessHandler
//        });
//        return [
//            Ext.apply(new Ext.Button(this.internetOnButton), {
//                scale: 'medium',
//                rowspan: 2,
//                iconAlign: 'top'
//            }),
//            Ext.apply(new Ext.Button(this.internetOffButton), {
//                scale: 'medium',
//                rowspan: 2,
//                iconAlign: 'top'
//            }),
//            Ext.apply(new Ext.Button(this.fileserverOnButton), {
//                scale: 'medium',
//                rowspan: 2,
//                iconAlign: 'top'
//            }),
//            Ext.apply(new Ext.Button(this.fileserverOffButton), {
//                scale: 'medium',
//                rowspan: 2,
//                iconAlign: 'top'
//            })
//        ];
    },
    
    /**
     * update access of course(s)
     * 
     * @param {Ext.Action} button
     * @param {} event
     */
    updateAccessHandler: function(button, event) {
        
        var courses = this.grid.getSelectionModel().getSelections();            
        var toUpdateIds = [];
        for (var i = 0; i < courses.length; ++i) {
            toUpdateIds.push(courses[i].data.id);
        }
        
        Ext.Ajax.request({
            params: {
                method: 'Courses.updateAccess',
                ids: toUpdateIds,
                type: button.type,
                access: button.access
            },
            success: function(_result, _request) {
                this.store.load();
            },
            failure: function(result, request){
                Ext.MessageBox.alert(
                    this.app.i18n._('Failed'), 
                    this.app.i18n._('Some error occured while trying to update the courses.')
                );
            },
            scope: this
        });
    }
});
