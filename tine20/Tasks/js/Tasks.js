/**
 * Tine 2.0
 * 
 * @package     Tasks
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

Ext.ns('Tine', 'Tine.Tasks');

/**
 * @namespace   Tine.Tasks
 * @class       Tine.Tasks.Application
 * @extends     Tine.Tinebase.Application
 * Tasks Application Object <br>
 * 
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */
Tine.Tasks.Application = Ext.extend(Tine.Tinebase.Application, {
    
    /**
     * auto hook text _('New Task')
     */
    addButtonText: 'New Task'
});

// default mainscreen
Tine.Tasks.MainScreen = Tine.widgets.MainScreen;

Tine.Tasks.TreePanel = function(config) {
    Ext.apply(this, config);
    
    this.id = 'TasksTreePanel';
    this.recordClass = Tine.Tasks.Task;
    
    this.filterMode = 'filterToolbar';
    Tine.Tasks.TreePanel.superclass.constructor.call(this);
};
Ext.extend(Tine.Tasks.TreePanel, Tine.widgets.container.TreePanel, {
    afterRender: function() {
        this.supr().afterRender.apply(this, arguments);
        //this.selectContainerPath(Tine.Tinebase.container.getMyNodePath());
    }
});

Tine.Tasks.FilterPanel = function(config) {
    Ext.apply(this, config);
    Tine.Tasks.FilterPanel.superclass.constructor.call(this);
};
Ext.extend(Tine.Tasks.FilterPanel, Tine.widgets.persistentfilter.PickerPanel, {
    filter: [{field: 'model', operator: 'equals', value: 'Tasks_Model_TaskFilter'}]
});

// Task model
Tine.Tasks.TaskArray = Tine.Tinebase.Model.genericFields.concat([
    { name: 'id' },
    { name: 'percent', header: 'Percent' },
    { name: 'completed', type: 'date', dateFormat: Date.patterns.ISO8601Long },
    { name: 'due', type: 'date', dateFormat: Date.patterns.ISO8601Long },
    { name: 'start_time', type: 'date', dateFormat: Date.patterns.ISO8601Long },  
    // ical common fields
    { name: 'class' },
    { name: 'description' },
    { name: 'geo' },
    { name: 'location' },
    { name: 'organizer' },
    { name: 'originator_tz' },
    { name: 'priority' },
    { name: 'status' },
    { name: 'summary' },
    { name: 'url' },
    // ical common fields with multiple appearance
    { name: 'attach' },
    { name: 'attendee' },
    { name: 'tags' },
    { name: 'comment' },
    { name: 'contact' },
    { name: 'related' },
    { name: 'resources' },
    { name: 'rstatus' },
    // scheduleable interface fields
    { name: 'dtstart', type: 'date', dateFormat: Date.patterns.ISO8601Long },
    { name: 'duration', type: 'date', dateFormat: Date.patterns.ISO8601Long },
    { name: 'recurid' },
    // scheduleable interface fields with multiple appearance
    { name: 'exdate' },
    { name: 'exrule' },
    { name: 'rdate' },
    { name: 'rrule' },
    // tine 2.0 notes field
    { name: 'notes'},
    // tine 2.0 alarms field
    { name: 'alarms'},
    // relations with other objects
    { name: 'relations'}
]);

/**
 * Task record definition
 */
Tine.Tasks.Task = Tine.Tinebase.data.Record.create(Tine.Tasks.TaskArray, {
    appName: 'Tasks',
    modelName: 'Task',
    idProperty: 'id',
    titleProperty: 'summary',
    // ngettext('Task', 'Tasks', n); gettext('Tasks');
    recordName: 'Task',
    recordsName: 'Tasks',
    containerProperty: 'container_id',
    // ngettext('to do list', 'to do lists', n); gettext('to do lists');
    containerName: 'to do list',
    containersName: 'to do lists'
});

/**
 * returns default account data
 * 
 * @namespace Tine.Tasks.Task
 * @static
 * @return {Object} default data
 */
Tine.Tasks.Task.getDefaultData = function() {
    var app = Tine.Tinebase.appMgr.get('Tasks');
    
    return {
        'class': 'PUBLIC',
        percent: 0,
        organizer: Tine.Tinebase.registry.get('currentAccount'),
        container_id: app.getMainScreen().getWestPanel().getContainerTreePanel().getDefaultContainer()
    };
};

/**
 * @namespace Tine.Tasks.Task
 * 
 * get task filter
 *  
 * @return {Array} filter objects
 * @static
 */ 
Tine.Tasks.Task.getFilterModel = function() {
    var app = Tine.Tinebase.appMgr.get('Tasks');
    
    return [
        {label: _('Quick search'),                  field: 'query',    operators: ['contains']},
        {filtertype: 'tine.widget.container.filtermodel', app: app, recordClass: Tine.Tasks.Task},
        {label: app.i18n._('Summary'),         field: 'summary' },
        {label: app.i18n._('Start Date'),        field: 'start_time', valueType: 'date', operators: ['within', 'before', 'after']},
        {label: app.i18n._('Due Date'),        field: 'due', valueType: 'date', operators: ['within', 'before', 'after']},
        {
            label: app.i18n._('Status'),
            field: 'status',
            filtertype: 'tine.widget.keyfield.filter', 
            app: app, 
            defaultValue: Tine.Tasks.Task.getClosedStatus(), 
            keyfieldName: 'taskStatus', 
            defaultOperator: 'notin'
        },
        {label: app.i18n._('Responsible'),     field: 'organizer', valueType: 'user'},
        {filtertype: 'tinebase.tag', app: app},
        {label: app.i18n._('Last modified'),   field: 'last_modified_time', valueType: 'date'},
        {label: app.i18n._('Last modifier'),   field: 'last_modified_by',   valueType: 'user'},
        {label: app.i18n._('Creation Time'),   field: 'creation_time',      valueType: 'date'},
        {label: app.i18n._('Creator'),         field: 'created_by',         valueType: 'user'}
    ];
};

/**
 * @namespace Tine.Tasks.Task
 * 
 * get closed status ids
 *  
 * @return {Array} status ids objects
 * @static
 */ 
Tine.Tasks.Task.getClosedStatus = function() {
    var reqStatus = [];
        
    Tine.Tinebase.widgets.keyfield.StoreMgr.get('Tasks', 'taskStatus').each(function(status) {
        if (! status.get('is_open')) {
            reqStatus.push(status.get('id'));
        }
    }, this);
    
    return reqStatus;
};

/**
 * default tasks backend
 */
Tine.Tasks.JsonBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Tasks',
    modelName: 'Task',
    recordClass: Tine.Tasks.Task
});

Ext.ns('Tine.Tasks.Model');

Tine.Tasks.Model.Status = Tine.Tinebase.data.Record.create([
    { name: 'id' },
    { name: 'value' },
    { name: 'icon' },
    { name: 'system' },
    { name: 'is_open' },
    { name: 'i18nValue' }
], {
    appName: 'Tasks',
    modelName: 'Status',
    idProperty: 'id',
    titleProperty: 'i18nValue',
    // ngettext('Status', 'Status', n); gettext('Status');
    recordName: 'Status',
    recordsName: 'Status'
});
