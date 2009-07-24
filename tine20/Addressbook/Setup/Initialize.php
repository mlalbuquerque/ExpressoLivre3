<?php
/**
 * Tine 2.0
  * 
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Jonas Fischer <j.fischer@metaways.de>
 * @copyright   Copyright (c) 2008-2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id: TineInitial.php 9535 2009-07-20 10:30:05Z p.schuele@metaways.de $
 *
 */

/**
 * class for Addressbook initialization
 * 
 * @todo move {@see _createInitialAdminAccount} to a better place (resolve dependency from addressbook)
 * 
 * @package Addressbook
 */
class Addressbook_Setup_Initialize extends Setup_Initialize
{

    /**
     * Override method: Setup needs additional initialisation
     * 
     * @see tine20/Setup/Setup_Initialize#_initialize($_application)
     */
    public function _initialize(Tinebase_Model_Application $_application)
    {
    	$this->_createInitialAdminAccount('tine20admin', 'lars', 'Tine 2.0', 'Admin Account'); //needed to give anyone read rights to the internal addressbook (see _createInitialRights) 
        parent::_initialize($_application);
    }
    /**
     * Override method because this app requires special rights
     * @see tine20/Setup/Setup_Initialize#_createInitialRights($_application)
     * 
     */
    protected function _createInitialRights(Tinebase_Model_Application $_application)
    {
        parent::_createInitialRights($_application);

        $groupsBackend = Tinebase_Group::factory(Tinebase_Group::SQL);
        $adminGroup = $groupsBackend->getGroupByName(Tinebase_Config::getInstance()->getConfig(Tinebase_Config::DEFAULT_ADMIN_GROUP)->value);
        
        // give anyone read rights to the internal addressbook
        // give Adminstrators group read/edit/admin rights to the internal addressbook
        $internalAddressbook = Tinebase_Container::getInstance()->getContainerByName('Addressbook', 'Internal Contacts', Tinebase_Model_Container::TYPE_INTERNAL);
        //Tinebase_Container::getInstance()->addGrants($internalAddressbook, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, $userGroup, array(
        Tinebase_Container::getInstance()->addGrants($internalAddressbook, Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE, '0', array(
            Tinebase_Model_Container::GRANT_READ
        ), TRUE);
        Tinebase_Container::getInstance()->addGrants($internalAddressbook, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, $adminGroup, array(
            Tinebase_Model_Container::GRANT_READ,
            Tinebase_Model_Container::GRANT_EDIT,
            Tinebase_Model_Container::GRANT_ADMIN
        ), TRUE);               
    }
    
    /**
     * create initial admin account
     *
     * @param string $_loginName
     * @param string $_password
     * @param string $_firstname
     * @param string $_lastname
     */
    protected function _createInitialAdminAccount($_loginName, $_password, $_firstname, $_lastname)
    {
        if (Tinebase_Core::getAuthType() !== Tinebase_Auth_Factory::SQL) {
            Tinebase_Core::getLogger()->info("Skip creation of initial admin account because the authtype is not " . Tinebase_Auth_Factory::SQL);
            return;
        }

        // get admin & user groups
        $groupsBackend = Tinebase_Group::factory(Tinebase_Group::SQL);
        $adminGroup = $groupsBackend->getGroupByName(Tinebase_Config::getInstance()->getConfig(Tinebase_Config::DEFAULT_ADMIN_GROUP)->value);
        $userGroup  = $groupsBackend->getGroupByName(Tinebase_Config::getInstance()->getConfig(Tinebase_Config::DEFAULT_USER_GROUP)->value);
        
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Creating initial admin user(' . $_loginName . ')');

        // add the admin account
        $accountsBackend = Tinebase_User::factory(Tinebase_User::SQL);

        $account = new Tinebase_Model_FullUser(array(
            'accountLoginName'      => $_loginName,
            'accountStatus'         => 'enabled',
            'accountPrimaryGroup'   => $userGroup->getId(),
            'accountLastName'       => $_lastname,
            'accountDisplayName'    => $_lastname . ', ' . $_firstname,
            'accountFirstName'      => $_firstname,
            'accountExpires'        => NULL,
            'accountEmailAddress'   => NULL,
        ));

        $accountsBackend->addUser($account);

        Tinebase_Core::set('currentAccount', $account);

        // set the password for the account
        Tinebase_User::getInstance()->setPassword($_loginName, $_password, $_password);

        // add the admin account to all groups
        Tinebase_Group::getInstance()->addGroupMember($adminGroup, $account);
        Tinebase_Group::getInstance()->addGroupMember($userGroup, $account);
    }
}