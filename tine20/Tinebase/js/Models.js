/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.Tinebase.Model');

/**
 * @type {Array}
 * generic Record fields
 */
Tine.Tinebase.Model.genericFields = [
    { name: 'container_id', header: 'Container',                                       isMetaField: false},
    { name: 'creation_time',      type: 'date', dateFormat: Date.patterns.ISO8601Long, isMetaField: true },
    { name: 'created_by',                                                              isMetaField: true },
    { name: 'last_modified_time', type: 'date', dateFormat: Date.patterns.ISO8601Long, isMetaField: true },
    { name: 'last_modified_by',                                                        isMetaField: true },
    { name: 'is_deleted',         type: 'boolean',                                     isMetaField: true },
    { name: 'deleted_time',       type: 'date', dateFormat: Date.patterns.ISO8601Long, isMetaField: true },
    { name: 'deleted_by',                                                              isMetaField: true }
];
    
/**
 * Model of a language
 */
Tine.Tinebase.Model.Language = Ext.data.Record.create([
    { name: 'locale' },
    { name: 'language' },
    { name: 'region' }
]);

/**
 * Model of a timezone
 */
Tine.Tinebase.Model.Timezone = Ext.data.Record.create([
    { name: 'timezone' },
    { name: 'timezoneTranslation' }
]);

/**
 * Model of a role
 */
Tine.Tinebase.Model.Role = Tine.Tinebase.data.Record.create([
    {name: 'id'},
    {name: 'name'},
    {name: 'description'}
], {
    appName: 'Tinebase',
    modelName: 'Role',
    idProperty: 'id',
    titleProperty: 'name',
    recordName: 'Role',
    recordsName: 'Roles',
    containerProperty: null
});

/**
 * Model of a generalised account (user or group)
 */
Tine.Tinebase.Model.Account = Ext.data.Record.create([
    {name: 'id'},
    {name: 'type'},
    {name: 'name'},
    {name: 'data'} // todo: throw away data
]);

/**
 * Model of a container
 */
Tine.Tinebase.Model.Container = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.genericFields.concat([
    {name: 'id'},
    {name: 'name'},
    {name: 'type'},
    {name: 'color'},
    {name: 'path'},
    {name: 'is_container_node', type: 'boolean'},
    {name: 'dtselect', type: 'number'},
    {name: 'application_id'},
    {name: 'account_grants'}
]), {
    appName: 'Tinebase',
    modelName: 'Container',
    idProperty: 'id',
    titleProperty: 'name'
});

/**
 * Model of a grant
 */
Tine.Tinebase.Model.Grant = Ext.data.Record.create([
    {name: 'id'},
    {name: 'account_id'},
    {name: 'account_type'},
    {name: 'account_name', sortType: Tine.Tinebase.common.accountSortType},
    {name: 'freebusyGrant',type: 'boolean'},
    {name: 'readGrant',    type: 'boolean'},
    {name: 'addGrant',     type: 'boolean'},
    {name: 'editGrant',    type: 'boolean'},
    {name: 'deleteGrant',  type: 'boolean'},
    {name: 'privateGrant', type: 'boolean'},
    {name: 'exportGrant',  type: 'boolean'},
    {name: 'syncGrant',    type: 'boolean'},
    {name: 'adminGrant',   type: 'boolean'}
]);

/**
 * Model of a tag
 * 
 * @constructor {Tine.Tinebase.data.Record}
 */
Tine.Tinebase.Model.Tag = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.genericFields.concat([
    {name: 'id'         },
    {name: 'app'        },
    {name: 'owner'      },
    {name: 'name'       },
    {name: 'type'       },
    {name: 'description'},
    {name: 'color'      },
    {name: 'occurrence' },
    {name: 'rights'     },
    {name: 'contexts'   },
    {name: 'selection_occurrence', type: 'number'}
]), {
    appName: 'Tinebase',
    modelName: 'Tag',
    idProperty: 'id',
    titleProperty: 'name',
    // ngettext('Tag', 'Tags', n); gettext('Tag');
    recordName: 'Tag',
    recordsName: 'Tags'
});

/**
 * replace template fields with data
 * @static
 */
Tine.Tinebase.Model.Tag.replaceTemplateField = function(tagData) {
    if (Ext.isArray(tagData)) {
        return Ext.each(tagData, Tine.Tinebase.Model.Tag.replaceTemplateField);
    }
    
    if (Ext.isFunction(tagData.beginEdit)) {
        tagData = tagData.data;
    }
    
    var replace = {
        'CURRENTDATE': Tine.Tinebase.common.dateRenderer(new Date()),
        'CURRENTTIME': Tine.Tinebase.common.timeRenderer(new Date()),
        'USERFULLNAME': Tine.Tinebase.registry.get('currentAccount').accountDisplayName
    };
    
    Ext.each(['name', 'description'], function(field) {
        for(var token in replace) {
            if (replace.hasOwnProperty(token) && Ext.isString(tagData[field])) {
                tagData[field] = tagData[field].replace(new RegExp('###' + token + '###', 'g'), replace[token]);
            }
        }
    }, this);
    
};

/**
 * Model of a PickerRecord
 * 
 * @constructor {Ext.data.Record}
 * 
 * @deprecated
 */
Tine.Tinebase.PickerRecord = Ext.data.Record.create([
    {name: 'id'}, 
    {name: 'name'}, 
    {name: 'data'}
]);

/**
 * Model of a note
 * 
 * @constructor {Ext.data.Record}
 */
Tine.Tinebase.Model.Note = Ext.data.Record.create([
    {name: 'id'             },
    {name: 'note_type_id'   },
    {name: 'note'           },
    {name: 'creation_time', type: 'date', dateFormat: Date.patterns.ISO8601Long },
    {name: 'created_by'     }
]);

/**
 * Model of a note type
 * 
 * @constructor {Ext.data.Record}
 */
Tine.Tinebase.Model.NoteType = Ext.data.Record.create([
    {name: 'id'             },
    {name: 'name'           },
    {name: 'icon'           },
    {name: 'icon_class'     },
    {name: 'description'    },
    {name: 'is_user_type'   }
]);

/**
 * Model of a customfield definition
 */
Tine.Tinebase.Model.Customfield = Ext.data.Record.create([
    { name: 'id'             },
    { name: 'application_id' },
    { name: 'model'          },
    { name: 'name'           },
    { name: 'definition'     },
    { name: 'account_grants' }
]);

/**
 * Model of a customfield value
 */
Tine.Tinebase.Model.CustomfieldValue = Ext.data.Record.create([
    { name: 'record_id'      },
    { name: 'customfield_id' },
    { name: 'value'          }
]);

/**
 * Model of a preference
 * 
 * @constructor {Ext.data.Record}
 */
Tine.Tinebase.Model.Preference = Ext.data.Record.create([
    {name: 'id'             },
    {name: 'name'           },
    {name: 'value'          },
    {name: 'type'           },
    {name: 'label'          },
    {name: 'description'    },
    {name: 'personal_only',         type: 'boolean' },
    {name: 'options'        }
]);

/**
 * Model of an alarm
 * 
 * @constructor {Ext.data.Record}
 */
Tine.Tinebase.Model.Alarm = Ext.data.Record.create([
    {name: 'id'             },
    {name: 'record_id'      },
    {name: 'model'          },
    {name: 'alarm_time',      type: 'date', dateFormat: Date.patterns.ISO8601Long     },
    {name: 'minutes_before' },
    {name: 'sent_time'      },
    {name: 'sent_status'    },
    {name: 'sent_message'   },
    {name: 'options'        }
]);

/**
 * @namespace Tine.Tinebase.Model
 * @class     Tine.Tinebase.Model.ImportJob
 * @extends   Tine.Tinebase.data.Record
 * 
 * Model of an import job
 */
Tine.Tinebase.Model.ImportJob = Tine.Tinebase.data.Record.create([
    {name: 'files'                  },
    {name: 'import_definition_id'   },
    {name: 'model'                  },
    {name: 'import_function'        },
    {name: 'container_id'           },
    {name: 'dry_run'                },
    {name: 'options'                }
], {
    appName: 'Tinebase',
    modelName: 'Import',
    idProperty: 'id',
    titleProperty: 'model',
    // ngettext('Import', 'Imports', n); gettext('Import');
    recordName: 'Import',
    recordsName: 'Imports',
    containerProperty: null
});

/**
 * @namespace Tine.Tinebase.Model
 * @class     Tine.Tinebase.Model.ExportJob
 * @extends   Tine.Tinebase.data.Record
 * 
 * Model of an export job
 */
Tine.Tinebase.Model.ExportJob = Tine.Tinebase.data.Record.create([
    {name: 'filter'                 },
    {name: 'export_definition_id'   },
    {name: 'format'                 },
    {name: 'exportFunction'         },
    {name: 'recordsName'            },
    {name: 'model'                  },
    {name: 'count', type: 'int'     },
    {name: 'options'                }
], {
    appName: 'Tinebase',
    modelName: 'Export',
    idProperty: 'id',
    titleProperty: 'model',
    // ngettext('Export', 'Export', n); gettext('Export');
    recordName: 'Export',
    recordsName: 'Exports',
    containerProperty: null
});

/**
 * Model of an export/import definition
 * 
 * @constructor {Ext.data.Record}
 */
Tine.Tinebase.Model.ImportExportDefinition = Ext.data.Record.create(Tine.Tinebase.Model.genericFields.concat([
    {name: 'id'             },
    {name: 'name'           },
    {name: 'label'          },
    {name: 'filename'       },
    {name: 'plugin'         },
    {name: 'description'    },
    {name: 'model'          },
    {name: 'plugin_options' }
]));

/**
 * @namespace Tine.Tinebase.Model
 * @class     Tine.Tinebase.Model.Credentials
 * @extends   Tine.Tinebase.data.Record
 * 
 * Model of user credentials
 */
Tine.Tinebase.Model.Credentials = Tine.Tinebase.data.Record.create([
    {name: 'id'},
    {name: 'username'},
    {name: 'password'}
], {
    appName: 'Tinebase',
    modelName: 'Credentials',
    idProperty: 'id',
    titleProperty: 'username',
    // ngettext('Credentials', 'Credentials', n); gettext('Credentials');
    recordName: 'Credentials',
    recordsName: 'Credentials',
    containerProperty: null
});

/**
 * @namespace Tine.Tinebase.Model
 * @class     Tine.Tinebase.Model.Relation
 * @extends   Tine.Tinebase.data.Record
 * 
 * Model of a Relation
 */
Tine.Tinebase.Model.Relation = Tine.Tinebase.data.Record.create([
    {name: 'id'},
    {name: 'own_model'},
    {name: 'own_id'},
    {name: 'related_model'},
    {name: 'related_id'},
    {name: 'type'},
    {name: 'remark'},
    {name: 'related_record'}
], {
    appName: 'Tinebase',
    modelName: 'Relation',
    idProperty: 'id',
    titleProperty: 'related_model',
    // ngettext('Relation', 'Relations', n); gettext('Relation');
    recordName: 'Relation',
    recordsName: 'Relations',
    containerProperty: null
});

/**
 * @namespace Tine.Tinebase.Model
 * @class     Tine.Tinebase.Model.Department
 * @extends   Tine.Tinebase.data.Record
 * 
 * Model of a Department
 */
Tine.Tinebase.Model.Department = Tine.Tinebase.data.Record.create([
    {name: 'id'},
    {name: 'name'},
    {name: 'description'}
], {
    appName: 'Tinebase',
    modelName: 'Department',
    idProperty: 'id',
    titleProperty: 'name',
    // ngettext('Department', 'Departments', n); gettext('Department');
    recordName: 'Department',
    recordsName: 'Departments',
    containerProperty: null
});

Tine.Tinebase.Model.Department.getFilterModel = function() {
    return [
        {label: _('Name'),          field: 'name',       operators: ['contains']}
    ];
};

/**
 * @namespace Tine.Tinebase.Model
 * @class     Tine.Tinebase.Model.Config
 * @extends   Tine.Tinebase.data.Record
 * 
 * Model of a application config settings
 */
Tine.Tinebase.Model.Config = Tine.Tinebase.data.Record.create([
    {name: 'id'}, // application name
    {name: 'settings'}
], {
    appName: 'Tinebase',
    modelName: 'Config',
    idProperty: 'id',
    titleProperty: 'id',
    // ngettext('Config', 'Configs', n); gettext('Configs');
    recordName: 'Config',
    recordsName: 'Configs',
    containerProperty: null
});
