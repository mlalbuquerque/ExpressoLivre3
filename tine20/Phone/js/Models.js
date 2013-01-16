/*
 * Tine 2.0
 * 
 * @package     Phone
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2008-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.namespace('Tine.Phone.Model');

/**
 * Model of a call
 */
Tine.Phone.Model.Call = Ext.data.Record.create([
    { name: 'id' },
    { name: 'line_id' },
    { name: 'phone_id' },
    { name: 'callerid' },
    { name: 'start', type: 'date', dateFormat: Date.patterns.ISO8601Long  },
    { name: 'connected', type: 'date', dateFormat: Date.patterns.ISO8601Long  },
    { name: 'disconnected', type: 'date', dateFormat: Date.patterns.ISO8601Long  },
    { name: 'duration' },
    { name: 'ringing' },
    { name: 'direction' },
    { name: 'source' },
    { name: 'destination' }
]);

/**
 * @type {Tine.Tinebase.data.Record}
 * Voipmanager record definition
 */
Tine.Phone.Model.MyPhone = Tine.Tinebase.data.Record.create(Tine.Tinebase.Model.genericFields.concat([ 
    {name: 'id'},
    {name: 'description'},
    {name: 'template_id'},
    {name: 'web_language'},
    {name: 'language'},
    {name: 'display_method'},
    {name: 'mwi_notification'},
    {name: 'mwi_dialtone'},
    {name: 'headset_device'},
    {name: 'message_led_other'},
    {name: 'global_missed_counter'},
    {name: 'scroll_outgoing'},
    {name: 'show_local_line'},
    {name: 'show_call_status'},
    {name: 'call_waiting'},
    {name: 'web_language_w'},
    {name: 'language_w'},
    {name: 'display_method_w'},
    {name: 'call_waiting_w'},
    {name: 'mwi_notification_w'},
    {name: 'mwi_dialtone_w'},
    {name: 'headset_device_w'},
    {name: 'message_led_other_w'},
    {name: 'global_missed_counter_w'},
    {name: 'scroll_outgoing_w'},
    {name: 'show_local_line_w'},
    {name: 'show_call_status_w'},
    {name: 'lines'}
]), {
    appName: 'Phone',
    modelName: 'SnomPhone',
    idProperty: 'id',
    titleProperty: 'description',
    // ngettext('Phone', 'Phones', n);
    recordName: 'MyPhone',
    recordsName: 'MyPhones',
    containerProperty: 'phone_id',
    // ngettext('phones list', 'phones lists', n);
    containerName: 'phones list',
    containersName: 'phones lists',
    getTitle: function() {
        return this.get('description');
    }
});

/**
 * default Snom.Phone backend
 */
Tine.Phone.MyPhoneBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Phone',
    modelName: 'MyPhone',
    recordClass: Tine.Phone.Model.MyPhone
});
