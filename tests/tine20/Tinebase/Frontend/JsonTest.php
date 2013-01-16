<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Json
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    Tinebase_Frontend_JsonTest::main();
}

/**
 * Test class for Tinebase_Group
 */
class Tinebase_Frontend_JsonTest extends PHPUnit_Framework_TestCase
{
    /**
     * unit under test (UIT)
     * @var Tinebase_Frontend_Json
     */
    protected $_instance;

    /**
     * @var array test objects
     */
    protected $_objects = array();
    
    /**
     * clear preferences after test?
     * 
     * @var boolean
     */
    protected $_clearPrefs = FALSE;
    
    /**
     * Runs the test methods of this class.
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tinebase_Frontend_JsonTest');
        PHPUnit_TextUI_TestRunner::run($suite);
    }
    
    /**
     * set up tests
     *
     */
    public function setUp()
    {
        $this->_instance = new Tinebase_Frontend_Json();
        
        $this->_objects['record'] = array(
            'id'        => 1,
            'model'     => 'Addressbook_Model_Contact',
            'backend'    => 'Sql',
        );        

		$this->_objects['group'] = new Tinebase_Model_Group(array(
			'name'			=> 'phpunit test group',
			'description'	=> 'phpunit test group'
		));

		$this->_objects['role'] = new Tinebase_Model_Role(array(
			'name'			=> 'phpunit test role',
			'description'	=> 'phpunit test role'
		));

        $this->_objects['note'] = new Tinebase_Model_Note(array(
            'note_type_id'      => 1,
            'note'              => 'phpunit test note',    
            'record_model'      => $this->_objects['record']['model'],
            'record_backend'    => $this->_objects['record']['backend'],       
            'record_id'         => $this->_objects['record']['id']
        ));        
    }
    
    /**
     * tear down prefs
     */
    public function tearDown()
    {
        if ($this->_clearPrefs) {
            $query = Tinebase_Core::getDb()->quoteInto(
                'DELETE FROM ' . SQL_TABLE_PREFIX . 'preferences WHERE application_id = ?', 
                Tinebase_Application::getInstance()->getApplicationByName('Tinebase')->getId()
            );
            Tinebase_Core::getDb()->query($query);
        }
        
        // reset tz in core
        Tinebase_Core::set(Tinebase_Core::USERTIMEZONE, Tinebase_Core::getPreference()->getValue(Tinebase_Preference::TIMEZONE));
    }
    
    /**
     * try to add a note type
     *
     */
    public function testSearchNotes()
    {
        Tinebase_Notes::getInstance()->addNote($this->_objects['note']);

        $filter = array(array(
            'field' => 'query',
            'operator' => 'contains',
            'value' => 'phpunit'
        ));
        $paging = array();
        
        $notes = $this->_instance->searchNotes($filter, $paging);
        
        $this->assertGreaterThan(0, $notes['totalcount']);        
        $this->assertEquals($this->_objects['note']->note, $notes['results'][0]['note']);
        
        // delete note
        Tinebase_Notes::getInstance()->deleteNotesOfRecord(
            $this->_objects['record']['model'], 
            $this->_objects['record']['backend'], 
            $this->_objects['record']['id']
        );        
    }
    
    /**
     * try to delete role and then search
     */
    public function testSearchRoles()
    {
    	$role = Tinebase_Acl_Roles::getInstance()->createRole($this->_objects['role']);
    	
    	$filter = array(array(
            'field' 	=> 'query',
            'operator' 	=> 'contains',
            'value' 	=> 'phpunit test role'
        ));
        $paging = array(
        	'start'	=> 0,
        	'limit'	=> 1
        );
        
        $roles = $this->_instance->searchRoles($filter, $paging);
        
        $this->assertGreaterThan(0, $roles['totalcount']);        
        $this->assertEquals($this->_objects['role']->name, $roles['results'][0]['name']);
        
        // delete role
        Tinebase_Acl_Roles::getInstance()->deleteRoles($role->id);  
    }
    
    /**
     * test getCountryList
     *
     */
    public function testGetCountryList()
    {
        $list = $this->_instance->getCountryList();
        $this->assertTrue(count($list['results']) > 200);
    }
    
    /**
     * test get translations
     *
     */
    public function testGetAvailableTranslations()
    {
        $list = $this->_instance->getAvailableTranslations();
        $this->assertTrue(count($list['results']) > 3);
    }
    
    /**
     * tests locale fallback
     */
    public function testSetLocaleFallback()
    {
        // de_LU -> de
        $this->_instance->setLocale('de_LU', FALSE, FALSE);
        $this->assertEquals('de', (string)Zend_Registry::get('locale'), 'Fallback to generic german did not succseed');
        
        $this->_instance->setLocale('zh', FALSE, FALSE);
        $this->assertEquals('zh_CN', (string)Zend_Registry::get('locale'), 'Fallback to simplified chinese did not succseed');
        
        $this->_instance->setLocale('foo_bar', FALSE, FALSE);
        $this->assertEquals('en', (string)Zend_Registry::get('locale'), 'Exception fallback to english did not succseed');
    }
    
    /**
     * test set locale and save it in db
     */
    public function testSetLocaleAsPreference()
    {
        $oldPreference = Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE};
        
        $locale = 'de';
        $result = $this->_instance->setLocale($locale, TRUE, FALSE);
        
        // get config setting from db
        $preference = Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE};
        $this->assertEquals($locale, $preference, "Didn't get right locale preference.");
        
        // restore old setting
        Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE} = $oldPreference;
    }

    /**
     * test set timezone and save it in db
     */
    public function testSetTimezoneAsPreference()
    {
        $oldPreference = Tinebase_Core::getPreference()->{Tinebase_Preference::TIMEZONE};
        
        $timezone = 'America/Vancouver';
        $result = $this->_instance->setTimezone($timezone, true);        
        
        // check json result
        $this->assertEquals($timezone, $result);
        
        // get config setting from db
        $preference = Tinebase_Core::getPreference()->{Tinebase_Preference::TIMEZONE};
        $this->assertEquals($timezone, $preference, "Didn't get right timezone preference.");
        
        // restore old settings
        Tinebase_Core::set('userTimeZone', $oldPreference);
        Tinebase_Core::getPreference()->{Tinebase_Preference::TIMEZONE} = $oldPreference;
    }
    
    /**
     * get notes types
     */
    public function testGetNotesTypes()
    {
        $noteTypes = $this->_instance->getNoteTypes();
        $this->assertTrue($noteTypes['totalcount'] >= 5);
    }
    
    /**
     * search preferences by application
     *
     */
    public function testSearchPreferences()
    {
        // search prefs
        $result = $this->_instance->searchPreferencesForApplication('Tinebase', $this->_getPreferenceFilter());
        
        // check results
        $this->assertTrue(isset($result['results']));
        $this->assertGreaterThan(2, $result['totalcount']);
        
        //check locale/timezones options
        foreach ($result['results'] as $pref) {
            switch($pref['name']) {
                case Tinebase_Preference::LOCALE:
                    $this->assertGreaterThan(10, count($pref['options']));
                    break;
                case Tinebase_Preference::TIMEZONE:
                    $this->assertGreaterThan(100, count($pref['options']));
                    break;
            }
            // check label and description
            $this->assertTrue(isset($pref['label']) && !empty($pref['label']));
            $this->assertTrue(isset($pref['description']) && !empty($pref['description']));
        }
    }

    /**
     * search preferences by application felamimail
     *
     */
    public function testSearchFelamimailPreferences()
    {
        // search prefs
        $result = $this->_instance->searchPreferencesForApplication('Felamimail', '');
        
        // check results
        $this->assertTrue(isset($result['results']));
        $this->assertGreaterThan(0, $result['totalcount']);
    }

    /**
     * search preferences by application
     *
     */
    public function testSearchPreferencesWithOptions()
    {
        $this->_clearPrefs = TRUE;
        
        // add new default pref
        $pref = $this->_getPreferenceWithOptions();
        $pref = Tinebase_Core::getPreference()->create($pref);        
        
        // search prefs
        $results = $this->_instance->searchPreferencesForApplication('Tinebase', $this->_getPreferenceFilter());
        
        // check results
        $this->assertTrue(isset($results['results']));
        $this->assertGreaterThan(3, $results['totalcount']);
        
        foreach ($results['results'] as $result) {
            if ($result['name'] == 'defaultapp') {
                $this->assertEquals(Tinebase_Model_Preference::DEFAULT_VALUE, $result['value']);
                $this->assertTrue(is_array($result['options']));
                $this->assertEquals(3, count($result['options']));
                $this->assertContains('option1', $result['options'][1][1]);
            } else if ($result['name'] == Tinebase_Preference::TIMEZONE) {
                $this->assertTrue(is_array($result['options'][0]), 'options should be arrays');
            }
        }
        
        Tinebase_Core::getPreference()->delete($pref);
    }
    
    /**
     * search preferences of another user
     *
     * @todo add check for the case that searching user has no admin rights
     */
    public function testSearchPreferencesOfOtherUsers()
    {
        $this->_clearPrefs = TRUE;
        
        // add new default pref
        $pref = $this->_getPreferenceWithOptions();
        $pref->account_id = 2;
        $pref->account_type = Tinebase_Acl_Rights::ACCOUNT_TYPE_USER;
        $pref = Tinebase_Core::getPreference()->create($pref);        
        
        // search prefs
        $results = $this->_instance->searchPreferencesForApplication('Tinebase', $this->_getPreferenceFilter(TRUE, FALSE, 2));
        
        // check results
        $this->assertTrue(isset($results['results']));
        $this->assertEquals(1, $results['totalcount']);
    }
    
    /**
     * save preferences for user
     *
     * @todo add test for saving of other users prefs and acl check
     */
    public function testSavePreferences()
    {
        $this->_clearPrefs = TRUE;
        
        $prefData = $this->_getUserPreferenceData();
        $this->_instance->savePreferences($prefData, false);

        // search saved prefs
        $results = $this->_instance->searchPreferencesForApplication('Tinebase', $this->_getPreferenceFilter(FALSE));
        
        // check results
        $this->assertTrue(isset($results['results']));
        $this->assertGreaterThan(2, $results['totalcount']);
        
        $savedPrefData = array();
        foreach ($results['results'] as $result) {
            if ($result['name'] == 'timezone') {
                $savedPrefData['Tinebase'][$result['name']] = array('value' => $result['value']);
            
                $this->assertTrue(is_array($result['options']), 'options missing');
                $this->assertGreaterThan(100, count($result['options']));
            }            
        }
        $this->assertEquals($prefData, $savedPrefData);
    }
    
    /**
     * tests if 'use default' appears in options and if it can be selected and if it changes if default changes
     */
    public function testGetSetChangeDefaultPref()
    {
        $this->_clearPrefs = TRUE;
        
        $locale = $this->_getLocalePref();
        foreach ($locale['options'] as $option) {
            if ($option[0] == Tinebase_Model_Preference::DEFAULT_VALUE) {
                $result = $option;
                $defaultString = $option[1];
            }
        }
        
        $this->assertTrue(isset($defaultString));
        $this->assertContains('(auto)', $defaultString);

        // set user pref to en first then to 'use default'
        Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE} = 'en';
        Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE} = Tinebase_Model_Preference::DEFAULT_VALUE;
        $this->assertEquals('auto', Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE});
        
        // set new default locale
        $prefData['Tinebase'][$locale['id']] = array('value' => 'de', 'type' => 'default', 'name' => Tinebase_Preference::LOCALE);
        $this->_instance->savePreferences($prefData, true);
        
        $updatedLocale = $this->_getLocalePref();
        foreach ($updatedLocale['options'] as $option) {
            if ($option[0] == Tinebase_Model_Preference::DEFAULT_VALUE) {
                $result = $option;
                $defaultString = $option[1];
            }
        }
        $this->assertEquals(count($locale['options']), count($updatedLocale['options']), 'option count has to be equal');
        $this->assertContains('(Deutsch)', $defaultString);
        $this->assertEquals('de', Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE});
    }
    
    /**
     * get locale pref
     */
    protected function _getLocalePref()
    {
        $results = $this->_instance->searchPreferencesForApplication('Tinebase', $this->_getPreferenceFilter());
        foreach ($results['results'] as $result) {
            if ($result['name'] == Tinebase_Preference::LOCALE) {
                $locale = $result;
            }
        }
        
        $this->assertTrue(isset($locale));
        
        return $locale;
    }

    /**
     * get admin prefs
     */
    public function testGetAdminPreferences()
    {
        $this->_clearPrefs = TRUE;
        
        // set new default locale
        $locale = $this->_getLocalePref();
        $prefData['Tinebase'][$locale['id']] = array('value' => 'de', 'type' => 'default', 'name' => Tinebase_Preference::LOCALE);
        $this->_instance->savePreferences($prefData, true);
        
        // check as admin
        $results = $this->_instance->searchPreferencesForApplication('Tinebase', $this->_getPreferenceFilter(FALSE, TRUE));
        foreach ($results['results'] as $pref) {
            if ($pref['name'] !== Tinebase_Preference::LOCALE) {
                $this->assertEquals(Tinebase_Model_Preference::DEFAULT_VALUE, $pref['value']);
            } else {
                $this->assertEquals(Tinebase_Model_Preference::TYPE_ADMIN, $pref['type']);
                $this->assertEquals('de', $pref['value']);
            }
        }

        // check as user
        $locale = $this->_getLocalePref();
        $this->assertEquals(Tinebase_Model_Preference::TYPE_ADMIN, $locale['type']);
        $this->assertEquals(Tinebase_Model_Preference::DEFAULT_VALUE, $locale['value']);
    }
    
    /**
     * save admin prefs
     *
     */
    public function testSaveAdminPreferences()
    {
        $this->_clearPrefs = TRUE;
        
        // add new default pref
        $pref = $this->_getPreferenceWithOptions();
        $pref = Tinebase_Core::getPreference()->create($pref);        
        
        $prefData = array();
        $prefData['Tinebase'][$pref->getId()] = array('value' => 'test', 'type' => 'forced');
        $this->_instance->savePreferences($prefData, true);

        // search saved prefs
        $results = $this->_instance->searchPreferencesForApplication('Tinebase', $this->_getPreferenceFilter(TRUE));
        
        // check results
        $this->assertTrue(isset($results['results']));
        $this->assertEquals(1, $results['totalcount']);
        $this->assertEquals($prefData['Tinebase'][$pref->getId()]['value'], $results['results'][0]['value']);
        $this->assertEquals($prefData['Tinebase'][$pref->getId()]['type'], $results['results'][0]['type']);
    }
    
    /**
     * save state and load it with registry data
     *
     * @todo save old state and recover it after the test
     */
    public function testSaveAndGetState()
    {
        $testData = array(
            'bla'   => 'blubb',
            'zzing' => 'zzang'
        );
        
        Tinebase_State::getInstance()->saveStateInfo($testData);
        
        $stateInfo = Tinebase_State::getInstance()->loadStateInfo();
        
        $this->assertEquals($testData, $stateInfo);
        
        //$registryData = $this->_instance->getAllRegistryData();
    }
    
    /**
     * test get all registry data
     * 
     * @return void
     * 
     * @todo add more assertions
     */
    public function testGetAllRegistryData()
    {
        $registryData = $this->_instance->getAllRegistryData();
        $currentUser = Tinebase_Core::getUser();
        
        $this->assertEquals($currentUser->toArray(), $registryData['Tinebase']['currentAccount']);
        $this->assertEquals(
            Addressbook_Controller_Contact::getInstance()->getContactByUserId($currentUser->getId())->toArray(), 
            $registryData['Tinebase']['userContact']
        );
    }
    
    /**
     * testGetUserProfile
     */
    public function testGetUserProfile()
    {
        $profile = $this->_instance->getUserProfile(Tinebase_Core::getUser()->getId());

        $this->assertTrue(is_array($profile));
        $this->assertTrue(array_key_exists('userProfile', $profile));
        $this->assertTrue(is_array($profile['userProfile']));
        $this->assertTrue(array_key_exists('readableFields', $profile));
        $this->assertTrue(is_array($profile['readableFields']));
        $this->assertTrue(array_key_exists('updateableFields', $profile));
        $this->assertTrue(is_array($profile['updateableFields']));
        
        // try to get user profile of different user
        $this->setExpectedException('Tinebase_Exception_AccessDenied');
        
        $sclever = array_value('sclever',Zend_Registry::get('personas'));
        $this->_instance->getUserProfile($sclever->getId());
    }
    
    /**
     * testGetUserProfileConfig
     */
    public function testGetUserProfileConfig()
    {
        $config = $this->_instance->getUserProfileConfig();
        
        $this->assertTrue(is_array($config));
        $this->assertTrue(array_key_exists('possibleFields', $config));
        $this->assertTrue(is_array($config['possibleFields']));
        $this->assertTrue(array_key_exists('readableFields', $config));
        $this->assertTrue(is_array($config['readableFields']));
        $this->assertTrue(array_key_exists('updateableFields', $config));
        $this->assertTrue(is_array($config['updateableFields']));
    }
    
    /**
     * testSetUserProfileConfig
     */
    public function testSetUserProfileConfig()
    {
        $config = $this->_instance->getUserProfileConfig();
        
        $idx = array_search('n_prefix', $config['readableFields']);
        if ($idx !== false) {
            unset ($config['readableFields'][$idx]);
        }
        
        $idx = array_search('tel_home', $config['updateableFields']);
        if ($idx !== false) {
            unset ($config['updateableFields'][$idx]);
        }
        
        $this->_instance->setUserProfileConfig($config);
    }
    
    /**
     * testupdateUserProfile
     */
    public function testUpdateUserProfile()
    {
        $profile = $this->_instance->getUserProfile(Tinebase_Core::getUser()->getId());
        $profileData = $profile['userProfile'];
        
        $this->assertFalse(array_search('n_prefix', $profileData));
        
        $profileData['tel_home'] = 'mustnotchange';
        $profileData['email_home'] = 'email@userprofile.set';
        
        $this->_instance->updateUserProfile($profileData);
        
        $updatedProfile = $this->_instance->getUserProfile(Tinebase_Core::getUser()->getId());
        $updatedProfileData = $updatedProfile['userProfile'];
        $this->assertNotEquals('mustnotchange', $updatedProfileData['tel_home']);
        $this->assertEquals('email@userprofile.set', $updatedProfileData['email_home']);
    }
    
    /**
     * testGetSaveApplicationConfig
     */
    public function testGetSaveApplicationConfig()
    {
        $config = $this->_instance->getConfig('Admin');
        $this->assertGreaterThan(0, count($config));
        
        $data = array(
            'id'        => 'Admin',
            'settings'  => Admin_Controller::getInstance()->getConfigSettings(),
        );
        
        $newConfig = $this->_instance->saveConfig($data);
        
        $this->assertEquals($config, $newConfig);
    }
    
    /******************** protected helper funcs ************************/
    
    /**
     * get preference filter
     *
     * @param bool $_savedPrefs
     * @return array
     */
    protected function _getPreferenceFilter($_savedPrefs = FALSE, $_adminPrefs = FALSE, $_userId = NULL)
    {
        if ($_userId === NULL) {
            $_userId = Tinebase_Core::getUser()->getId();
        }
        
        $result = array(
            array(
                'field' => 'account', 
                'operator' => 'equals', 
                'value' => array(
                    'accountId'     => ($_adminPrefs) ? 0 : $_userId,
                    'accountType'   => ($_adminPrefs) 
                        ? Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE 
                        : Tinebase_Acl_Rights::ACCOUNT_TYPE_USER
                )
            )
        );

        if ($_savedPrefs) {
            $result[] = array(
                'field' => 'name', 
                'operator' => 'contains', 
                'value' => 'defaultapp'
            );
        }
        
        return $result;
    }

    /**
     * get preference data for testSavePreferences()
     *
     * @return array
     */
    protected function _getUserPreferenceData()
    {
        return array(
            'Tinebase' => array(
                'timezone' => array('value' => 'Europe/Amsterdam'),
            )
        );        
    }
    
    /**
     * get preference with options
     *
     * @return Tinebase_Model_Preference
     */
    protected function _getPreferenceWithOptions()
    {
        return new Tinebase_Model_Preference(array(
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Tinebase')->getId(),
            'name'              => 'defaultapp',
            'value'             => 'value1',
            'account_id'        => 0,
            'account_type'      => Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE,
            'type'              => Tinebase_Model_Preference::TYPE_ADMIN,
            'options'           => '<?xml version="1.0" encoding="UTF-8"?>
                <options>
                    <option>
                        <label>option1</label>
                        <value>value1</value>
                    </option>
                    <option>
                        <label>option2</label>
                        <value>value2</value>
                    </option>
                </options>'
        ));
    }
}
