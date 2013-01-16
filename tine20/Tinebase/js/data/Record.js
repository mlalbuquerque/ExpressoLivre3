/*
 * Tine 2.0
 * 
 * @package     Tine
 * @subpackage  Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 */
 
Ext.ns('Tine.Tinebase', 'Tine.Tinebase.data');

Tine.Tinebase.data.Record = function(data, id){
    if (id || id === 0) {
        this.id = id;
    } else if (data[this.idProperty]) {
        this.id = data[this.idProperty];
    } else {
        this.id = ++Ext.data.Record.AUTO_ID;
    }
    this.data = data;
};

/**
 * @namespace Tine.Tinebase.data
 * @class     Tine.Tinebase.data.Record
 * @extends   Ext.data.Record
 * 
 * Baseclass of Tine 2.0 models
 */
Ext.extend(Tine.Tinebase.data.Record, Ext.data.Record, {
    /**
     * @cfg {String} appName
     * internal/untranslated app name (required)
     */
    appName: null,
    /**
     * @cfg {String} modelName
     * name of the model/record  (required)
     */
    modelName: null,
    /**
     * @cfg {String} idProperty
     * property of the id of the record
     */
    idProperty: 'id',
    /**
     * @cfg {String} titleProperty
     * property of the title attibute, used in generic getTitle function  (required)
     */
    titleProperty: null,
    /**
     * @cfg {String} recordName
     * untranslated record/item name
     */
    recordName: 'record',
    /**
     * @cfg {String} recordName
     * untranslated records/items (plural) name
     */
    recordsName: 'records',
    /**
     * @cfg {String} containerProperty
     * name of the container property
     */
    containerProperty: 'container_id',
    /**
     * @cfg {String} containerName
     * untranslated container name
     */
    containerName: 'container',
    /**
     * @cfg {string} containerName
     * untranslated name of container (plural)
     */
    containersName: 'containers',
    
    cfExp: /^#(.+)/,
    
    /**
     * Get the value of the {@link Ext.data.Field#name named field}.
     * @param {String} name The {@link Ext.data.Field#name name of the field} to get the value of.
     * @return {Object} The value of the field.
     */
    get: function(name) {
        
        if (cfName = String(name).match(this.cfExp)) {
            return this.data.customfields ? this.data.customfields[cfName[1]] : null;
        }
        
        return this.data[name];
    },
    
    /**
     * Set the value of the {@link Ext.data.Field#name named field}.
     * @param {String} name The {@link Ext.data.Field#name name of the field} to get the value of.
     * @return {Object} The value of the field.
     */
    set : function(name, value) {
        var encode = Ext.isPrimitive(value) ? String : Ext.encode,
            current = this.get(name);
            
        if(encode(current) == encode(value)) {
            return;
        }        
        this.dirty = true;
        if(!this.modified){
            this.modified = {};
        }
        if(this.modified[name] === undefined){
            this.modified[name] = current;
        }
        
        if (cfName = String(name).match(this.cfExp)) {
            this.data.customfields = this.data.customfields || {};
            this.data.customfields[cfName[1]] = value;
        } else {
            this.data[name] = value;
        }
        
        if(!this.editing){
            this.afterEdit();
        }
    },
    
    /**
     * returns title of this record
     * 
     * @return {String}
     */
    getTitle: function() {
        return this.titleProperty ? this.get(this.titleProperty) : '';
    },
    
    /**
     * converts data to String
     * 
     * @return {String}
     */
    toString: function() {
        return Ext.encode(this.data);
    }
});

/**
 * Generate a constructor for a specific Record layout.
 * 
 * @param {Array} def see {@link Ext.data.Record#create}
 * @param {Object} meta information see {@link Tine.Tinebase.data.Record}
 * 
 * <br>usage:<br>
<b>IMPORTANT: the ngettext comments are required for the translation system!</b>
<pre><code>
var TopicRecord = Tine.Tinebase.data.Record.create([
    {name: 'summary', mapping: 'topic_title'},
    {name: 'details', mapping: 'username'}
], {
    appName: 'Tasks',
    modelName: 'Task',
    idProperty: 'id',
    titleProperty: 'summary',
    // ngettext('Task', 'Tasks', n);
    recordName: 'Task',
    recordsName: 'Tasks',
    containerProperty: 'container_id',
    // ngettext('to do list', 'to do lists', n);
    containerName: 'to do list',
    containesrName: 'to do lists'
});
</code></pre>
 * @static
 */
Tine.Tinebase.data.Record.create = function(o, meta) {
    var f = Ext.extend(Tine.Tinebase.data.Record, {});
    var p = f.prototype;
    Ext.apply(p, meta);
    p.fields = new Ext.util.MixedCollection(false, function(field){
        return field.name;
    });
    for(var i = 0, len = o.length; i < len; i++){
        p.fields.add(new Ext.data.Field(o[i]));
    }
    f.getField = function(name){
        return p.fields.get(name);
    };
    f.getMeta = function(name) {
        return p[name];
    };
    f.getDefaultData = function() {
        return {};
    };
    f.getFieldDefinitions = function() {
        return p.fields.items;
    };
    f.getFieldNames = function() {
        if (! p.fieldsarray) {
            var arr = p.fieldsarray = [];
            Ext.each(p.fields.items, function(item) {arr.push(item.name);});
        }
        return p.fieldsarray;
    };
    f.hasField = function(n) {
        return p.fields.indexOfKey(n) >= 0;
    };
    f.getRecordName = function() {
        return Tine.Tinebase.appMgr.get(p.appName).i18n._(p.recordName);
    };
    f.getRecordsName = function() {
        return Tine.Tinebase.appMgr.get(p.appName).i18n._(p.recordsName);
    };
    f.getContainerName = function() {
        return Tine.Tinebase.appMgr.get(p.appName).i18n._(p.containerName);
    };
    f.getContainersName = function() {
        return Tine.Tinebase.appMgr.get(p.appName).i18n._(p.containersName);
    };
    Tine.Tinebase.data.RecordMgr.add(f);
    return f;
};

Tine.Tinebase.data.RecordManager = Ext.extend(Ext.util.MixedCollection, {
    add: function(record) {
        if (! Ext.isFunction(record.getMeta)) {
            throw new Ext.Error('only records of type Tinebase.data.Record could be added');
        }
        var appName = record.getMeta('appName'),
            modelName = record.getMeta('modelName');
            
        if (! appName && modelName) {
            throw new Ext.Error('appName and modelName must be in the metadatas');
        }
        
//        console.log('register model "' + appName + '.' + modelName + '"');
        Tine.Tinebase.data.RecordManager.superclass.add.call(this, appName + '.' + modelName, record);
    },
    
    get: function(appName, modelName) {
        if (Ext.isFunction(appName.getMeta)) {
            return appName;
        }
        if (! modelName && appName.modelName) {
            modelName = appName.modelName;
        }
        if (appName.appName) {
            appName = appName.appName;
        }
            
        if (! Ext.isString(appName)) {
            throw new Ext.Error('appName must be a string');
        }
        
        Ext.each([appName, modelName], function(what) {
            if (! Ext.isString(what)) return;
            var parts = what.split(/(?:_Model_)|(?:\.)/);
            if (parts.length > 1) {
                appName = parts[0];
                modelName = parts[1];
            }
        });
        
        return Tine.Tinebase.data.RecordManager.superclass.get.call(this, appName + '.' + modelName);
    }
});
Tine.Tinebase.data.RecordMgr = new Tine.Tinebase.data.RecordManager(true);