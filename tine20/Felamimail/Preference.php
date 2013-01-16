<?php
/**
 * Tine 2.0
 *
 * @package     Felamimail
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @todo        make UPDATEINTERVAL a free form preference
 */


/**
 * backend for Felamimail preferences
 *
 * @package     Felamimail
 * @subpackage  Backend
 */
class Felamimail_Preference extends Tinebase_Preference_Abstract
{
    /**************************** application preferences/settings *****************/
    
    /**
     * default email account to use
     *
     */
    const DEFAULTACCOUNT = 'defaultEmailAccount';

    /**
     * email folder update interval
     *
     */
    const UPDATEINTERVAL = 'updateInterval';

    /**
     * use felamimail in addressbook
     *
     */
    const USEINADB = 'useInAdb';
   
    /**
     * use for default note
     *
     */
    const AUTOATTACHNOTE = 'autoAttachNote';
    
    /**
     * show delete confirmation
     *
     * @todo add this to more apps?
     */
    const CONFIRM_DELETE = 'confirmDelete';

    /**
     * show Use Trash option
     *
     */
    const MOVEDELETED_TOTRASH = 'confirmUseTrash';

     /**
     * show Use Trash option
     *
     */
    const DELETE_FROMTRASH = 'deleteFromTrash';
    
    /**
     * show Emails Per Page option
     *
     */
    const EMAILS_PERPAGE = 'emailsPerPage';
    
    /**
     * default filter name
     */
    const DEFAULTPERSISTENTFILTER_NAME = 'All inboxes'; //  _("All inboxes")
    
    /**
     * application
     *
     * @var string
     */
    protected $_application = 'Felamimail';

    /**
     * preference names that have no default option
     * 
     * @var array
     */
    protected $_skipDefaultOption = array(self::DEFAULTACCOUNT);
        
    /**************************** public functions *********************************/
    
    /**
     * get all possible application prefs
     *
     * @return  array   all application prefs
     */
    public function getAllApplicationPreferences()
    {
        $allPrefs = array(
            self::DEFAULTACCOUNT,
            self::UPDATEINTERVAL,
            self::USEINADB,
            self::AUTOATTACHNOTE,
            self::CONFIRM_DELETE,
            self::MOVEDELETED_TOTRASH,
            self::DELETE_FROMTRASH,            
            self::EMAILS_PERPAGE,
        );
            
        return $allPrefs;
    }
    
    /**
     * get translated right descriptions
     * 
     * @return  array with translated descriptions for this applications preferences
     */
    public function getTranslatedPreferences()
    {
        $translate = Tinebase_Translation::getTranslation($this->_application);

        $prefDescriptions = array(
            self::DEFAULTACCOUNT  => array(
                'label'         => $translate->_('Default Email Account'),
                'description'   => $translate->_('The default email account to use when sending mails.'),
            ),
            self::UPDATEINTERVAL  => array(
                'label'         => $translate->_('Email Update Interval'),
                'description'   => $translate->_('How often should Felamimail check for new Emails (in minutes). "0" means never.'),
            ),
            self::USEINADB  => array(
                'label'         => $translate->_('Use in Addressbook'),
                'description'   => $translate->_('Compose Emails from the Addressbook with Felamimail.'),
            ),
            self::AUTOATTACHNOTE  => array(
                'label'         => $translate->_('Use for NOTES'),
                'description'   => $translate->_('Save Note default Value.'),
            ),
            self::CONFIRM_DELETE  => array(
                'label'         => $translate->_('Confirm Delete'),
                'description'   => $translate->_('Show confirmation dialog when deleting mails.'),
            ),
            self::MOVEDELETED_TOTRASH  => array(
                'label'         => $translate->_('Move Deleted Messages to Trash'),
                'description'   => $translate->_('Choose yes, to Move Deleted Messages to Trash.'),
            ),
            self::DELETE_FROMTRASH  => array(
                'label'         => $translate->_('Delete trash messages after how many days'),
                'description'   => $translate->_('Choose a number of days'),
            ),            
            self::EMAILS_PERPAGE  => array(
                'label'         => $translate->_('Emails shown in each page'),
                'description'   => $translate->_('Choose a number of emails to show in each page'),
            ),            
        );
        
        return $prefDescriptions;
    }
    
    /**
     * get preference defaults if no default is found in the database
     *
     * @param string $_preferenceName
     * @return Tinebase_Model_Preference
     */
    public function getApplicationPreferenceDefaults($_preferenceName, $_accountId=NULL, $_accountType=Tinebase_Acl_Rights::ACCOUNT_TYPE_USER)
    {
        $preference = $this->_getDefaultBasePreference($_preferenceName);
        
        switch($_preferenceName) {
            case self::USEINADB:
                $preference->value      = 1;
                $preference->options    = '<?xml version="1.0" encoding="UTF-8"?>
                    <options>
                        <special>' . Tinebase_Preference_Abstract::YES_NO_OPTIONS . '</special>
                    </options>';
                break;
            case self::DEFAULTACCOUNT:
                $preference->personal_only  = TRUE;
                $preference->value          = '';
                break;
            case self::UPDATEINTERVAL:
                $preference->value      = 5;
                $preference->options    = '<?xml version="1.0" encoding="UTF-8"?>
                    <options>';
                for ($i = 1; $i <= 20; $i++) {
                    $preference->options .= '<option>
                        <label>'. $i . '</label>
                        <value>'. $i . '</value>
                    </option>';
                }
                $preference->options    .= '</options>';
                break;
            case self::AUTOATTACHNOTE:
                $preference->options    = '<?xml version="1.0" encoding="UTF-8"?>
                    <options>
                        <special>' . Tinebase_Preference_Abstract::YES_NO_OPTIONS . '</special>
                    </options>';
                break;
            case self::CONFIRM_DELETE:
                $preference->value      = 1;
                $preference->options    = '<?xml version="1.0" encoding="UTF-8"?>
                    <options>
                        <special>' . Tinebase_Preference_Abstract::YES_NO_OPTIONS . '</special>
                    </options>';
                break;
            case self::MOVEDELETED_TOTRASH:
                $preference->value      = 1;
                $preference->options    = '<?xml version="1.0" encoding="UTF-8"?>
                    <options>
                        <special>' . Tinebase_Preference_Abstract::YES_NO_OPTIONS . '</special>
                    </options>';
                break;
            case self::DELETE_FROMTRASH:
                $preference->value      = 0;
                $preference->options    = '<?xml version="1.0" encoding="UTF-8"?>
                    <options>';
                for ($i = 1; $i <= 5; $i++) {
                    $preference->options .= '<option>
                        <label>'. $i . '</label>
                        <value>'. $i . '</value>
                    </option>';
                }
                $preference->options    .= '</options>';
                break;            
            case self::EMAILS_PERPAGE:
                $preference->value      = 50;
                $preference->options    = '<?xml version="1.0" encoding="UTF-8"?>
                    <options>';
                for ($i = 25; $i <= 100; $i=$i*2) {
                    $preference->options .= '<option>
                        <label>'. $i . '</label>
                        <value>'. $i . '</value>
                    </option>';
                }
                $preference->options    .= '</options>';
                break;            
            default:
                throw new Tinebase_Exception_NotFound('Default preference with name ' . $_preferenceName . ' not found.');
        }
        
        return $preference;
    }
    
    /**
     * get special options
     *
     * @param string $_value
     * @return array
     */
    protected function _getSpecialOptions($_value)
    {
        $result = array();
        switch($_value) {
            case self::DEFAULTACCOUNT:
                // get all user accounts
                $accounts = Felamimail_Controller_Account::getInstance()->search();
                foreach ($accounts as $account) {
                    $result[] = array($account->getId(), $account->name);
                }
                break;
            default:
                $result = parent::_getSpecialOptions($_value);
        }
        
        return $result;
    }
}
