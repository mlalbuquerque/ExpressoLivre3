<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  User
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * defines the datatype for a full users
 * 
 * this datatype contains all information about an user
 * the usage of this datatype should be restricted to administrative tasks only
 * 
 * @package     Tinebase
 * @property    string                  accountStatus
 * @property    Tinebase_Model_SAMUser  sambaSAM            object holding samba settings
 * @property    string                  accountEmailAddress email address of user
 * @property    DateTime                accountExpires      date when account expires  
 * @property    string                  accountFullName     fullname of the account
 * @property    string                  accountDisplayName  displayname of the account
 * @property    string                  accountLoginName    account login name
 * @property    string                  accountPrimaryGroup primary group id
 * @property    string                  container_id        
 * @property    array                   groups              list of group memberships
 * @property    DateTime                lastLoginFailure    time of last login failure
 * @property    int                        loginFailures       number of login failures
 * @subpackage  User
 */
class Tinebase_Model_FullUser extends Tinebase_Model_User
{
    /**
     * list of zend inputfilter
     * 
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_filters = array(
        //'accountId'             => 'Digits',
        'accountLoginName'      => array('StringTrim', 'StringToLower'),
        //'accountPrimaryGroup'   => 'Digits',
        'accountDisplayName'    => 'StringTrim',
        'accountLastName'       => 'StringTrim',
        'accountFirstName'      => 'StringTrim',
        'accountFullName'       => 'StringTrim',
        'accountEmailAddress'   => array('StringTrim', 'StringToLower'),
        'openid'                => array(array('Empty', null))
    ); // _/-\_
    
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     * @todo add valid values for status
     */
    protected $_validators;

    /**
     * name of fields containing datetime or or an array of datetime
     * information
     *
     * @var array list of datetime fields
     */    
    protected $_datetimeFields = array(
        'accountLastLogin',
        'accountLastPasswordChange',
        'accountExpires',
        'lastLoginFailure'
    );
    
    /**
     * @see Tinebase_Record_Abstract
     */
    public function __construct($_data = NULL, $_bypassFilters = false, $_convertDates = true)
    {
        $this->_validators = array(
            'accountId'             => array('allowEmpty' => true),
            'accountLoginName'      => array('presence' => 'required'),
            'accountLastLogin'      => array('allowEmpty' => true),
            'accountLastLoginfrom'  => array('allowEmpty' => true),
            'accountLastPasswordChange' => array('allowEmpty' => true),
            'accountStatus'         => array(new Zend_Validate_InArray(array(
                Tinebase_Model_User::ACCOUNT_STATUS_ENABLED,
                Tinebase_Model_User::ACCOUNT_STATUS_DISABLED,
                Tinebase_Model_User::ACCOUNT_STATUS_BLOCKED,
                Tinebase_Model_User::ACCOUNT_STATUS_EXPIRED)
            ), Zend_Filter_Input::DEFAULT_VALUE => Tinebase_Model_User::ACCOUNT_STATUS_ENABLED),
            'accountExpires'        => array('allowEmpty' => true),
            'accountPrimaryGroup'   => array('presence' => 'required'),
            'accountDisplayName'    => array('presence' => 'required'),
            'accountLastName'       => array('presence' => 'required'),
            'accountFirstName'      => array('allowEmpty' => true),
            'accountFullName'       => array('presence' => 'required'),
            'accountEmailAddress'   => array('allowEmpty' => true),
            'accountHomeDirectory'  => array('allowEmpty' => true),
            'accountLoginShell'     => array('allowEmpty' => true),
            'lastLoginFailure'      => array('allowEmpty' => true),
            'loginFailures'         => array('allowEmpty' => true),
            'sambaSAM'              => array('allowEmpty' => true),
            'openid'                => array('allowEmpty' => true),
            'contact_id'            => array('allowEmpty' => true),
            'container_id'          => array('allowEmpty' => true),
            'emailUser'             => array('allowEmpty' => true),
            'groups'                => array('allowEmpty' => true),
            'imapUser'              => array('allowEmpty' => true),
            'smtpUser'              => array('allowEmpty' => true),
            'visibility'            => array(new Zend_Validate_InArray(array(
                Tinebase_Model_User::VISIBILITY_HIDDEN, 
                Tinebase_Model_User::VISIBILITY_DISPLAYED)
            ), Zend_Filter_Input::DEFAULT_VALUE => Tinebase_Model_User::VISIBILITY_DISPLAYED),
        );
        
        parent::__construct($_data, $_bypassFilters, $_convertDates);
    }
    
    /**
     * return the public informations of this user only
     *
     * @return Tinebase_Model_User
     */
    public function getPublicUser()
    {
        $result = new Tinebase_Model_User($this->toArray(), true);
        
        return $result;
    }
    
    /**
     * returns user login name
     *
     * @return string
     */
    public function __toString()
    {
        return $this->accountLoginName;
    }
    
    /**
     * returns TRUE if user has to change his/her password (compare sambaSAM->pwdMustChange with Tinebase_DateTime::now())
     * NOTE: this only applies for user with samba settings atm
     * 
     * @return boolean
     */
    public function mustChangePassword()
    {
        $result = FALSE;
        
        if ($this->sambaSAM instanceof Tinebase_Model_SAMUser 
            && isset($this->sambaSAM->pwdMustChange) 
            && $this->sambaSAM->pwdMustChange instanceof DateTime) 
        {
            if ($this->sambaSAM->pwdMustChange->compare(Tinebase_DateTime::now()) < 0) {
                if (!isset($this->sambaSAM->pwdLastSet)) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                        . ' User ' . $this->accountLoginName . ' has to change his pw: it got never set by user');
                        
                    $result = TRUE;
                    
                } else if (isset($this->sambaSAM->pwdLastSet) && $this->sambaSAM->pwdLastSet instanceof DateTime) {
                    $dateToCompare = $this->sambaSAM->pwdLastSet;
                    
                    if ($this->sambaSAM->pwdMustChange->compare($dateToCompare) > 0) {
                        if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                            . ' User ' . $this->accountLoginName . ' has to change his pw: ' . $this->sambaSAM->pwdMustChange . ' > ' . $dateToCompare);
                            
                        $result = TRUE;
                    }
                } else {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Password is up to date.');
                }
            }
        }
        
        return $result;
    }
}
