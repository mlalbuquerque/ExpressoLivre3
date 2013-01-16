<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Acl
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * 
 * @todo        extend/use sql abstract backend
 */

/**
 * this class handles the roles
 * 
 * @package     Tinebase
 * @subpackage  Acl
 */
class Tinebase_Acl_Roles
{    
    /**
     * @var Zend_Db_Adapter_Pdo_Mysql
     */
    protected $_db;
    
    /**
     * the Zend_Dd_Table object
     *
     * @var Tinebase_Db_Table
     */
    protected $_rolesTable;
    
    /**
     * the Zend_Dd_Table object for role members
     *
     * @var Tinebase_Db_Table
     */
    protected $_roleMembersTable;

    /**
     * the Zend_Dd_Table object for role rights
     *
     * @var Tinebase_Db_Table
     */
    protected $_roleRightsTable;
    
    /**
     * holdes the _instance of the singleton
     *
     * @var Tinebase_Acl_Roles
     */
    private static $_instance = NULL;
    
    /**
     * the clone function
     *
     * disabled. use the singleton
     */
    private function __clone() 
    {
    }
    
    /**
     * the constructor
     *
     * disabled. use the singleton
     * temporarly the constructor also creates the needed tables on demand and fills them with some initial values
     */
    private function __construct() {

        $this->_rolesTable = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . 'roles'));
        $this->_roleMembersTable = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . 'role_accounts'));
        $this->_roleRightsTable = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . 'role_rights'));
        $this->_db = Tinebase_Core::getDb();
    }    
    
    /**
     * the singleton pattern
     *
     * @return Tinebase_Acl_Roles
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tinebase_Acl_Roles;
        }
        
        return self::$_instance;
    }        

    /**
     * check if one of the roles the user is in has a given right for a given application
     * - admin right includes all other rights
     *
     * @param   string|Tinebase_Model_Application $_application the application (one of: app name, id or record)
     * @param   int $_accountId the numeric id of a user account
     * @param   int $_right the right to check for
     * @return  bool
     * @throws  Tinebase_Exception_AccessDenied
     */
    public function hasRight($_application, $_accountId, $_right) 
    {   
        try {
            $appId = Tinebase_Model_Application::convertApplicationIdToInt($_application);
        } catch (Tinebase_Exception_NotFound $tenf) {
            Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Application ' . $_application . ' is not installed.');
            return false;
            
        }
        $application = Tinebase_Application::getInstance()->getApplicationById($appId);
        if ($application->status != 'enabled') {
            Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Application ' . $_application . ' is disabled.');
            return false;
        }
        
        $roleMemberships = $this->getRoleMemberships($_accountId);
        
        if (empty($roleMemberships)) {
            Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $_accountId . ' has no role memberships.');
            return false;
        }

        $select = $this->_roleRightsTable->select();
        $select->where($this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' IN (?)', $roleMemberships))
               ->where('(' .    $this->_db->quoteInto($this->_db->quoteIdentifier('right') . ' = ?', $_right) 
                     . ' OR ' . $this->_db->quoteInto($this->_db->quoteIdentifier('right') . ' = ?', Tinebase_Acl_Rights::ADMIN) . ')')
               ->where($this->_db->quoteInto($this->_db->quoteIdentifier('application_id') . ' = ?', $application->getId()));
               
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $select->__toString());

        if (!$row = $this->_roleRightsTable->fetchRow($select)) {
            $result = false;
        } else {
            $result = true;
        }
        
        return $result;
    }

    /**
     * returns list of applications the user is able to use
     *
     * this function takes group memberships into account. Applications the accounts is able to use
     * must have any (was: the 'run') right set and the application must be enabled
     * 
     * @param   int $_accountId the numeric account id
     * @param   boolean $_anyRight is any right enough to geht app?
     * @return  array list of enabled applications for this account
     * @throws  Tinebase_Exception_AccessDenied if user has no role memberships
     */
    public function getApplications($_accountId, $_anyRight = FALSE)
    {  
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $_anyRight);
        
        $roleMemberships = $this->getRoleMemberships($_accountId);
        
        if (empty($roleMemberships)) {
            //throw new Tinebase_Exception_AccessDenied('User has no role memberships', 610);
            return new Tinebase_Record_RecordSet('Tinebase_Model_Application');
        }

        $rightIdentifier = $this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'role_rights.right');
        
        $select = $this->_db->select()
            ->distinct() 
            ->from(SQL_TABLE_PREFIX . 'role_rights', array())
            ->join(SQL_TABLE_PREFIX . 'applications', 
                $this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'role_rights.application_id') . 
                ' = ' . $this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'applications.id'))            
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' IN (?)', $roleMemberships))
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'applications.status') . ' = ?', Tinebase_Application::ENABLED))
            ->order('order', 'ASC');
        
        if ($_anyRight) {
            $select->where($this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'role_rights.right') . " IS NOT NULL");
        } else {
            $select->where($this->_db->quoteInto($this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'role_rights.right') . ' = ?', Tinebase_Acl_Rights::RUN));
        }
        
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $select->__toString());
        
        $stmt = $this->_db->query($select);
        
        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Application', $stmt->fetchAll(Zend_Db::FETCH_ASSOC));
        
        return $result;
    }

    /**
     * returns rights for given application and accountId
     *
     * @param   string $_application the name of the application
     * @param   int $_accountId the numeric account id
     * @return  array list of rights
     * @throws  Tinebase_Exception_AccessDenied
     * 
     * @todo    add right group by to statement if possible or remove duplicates in result array
     */
    public function getApplicationRights($_application, $_accountId) 
    {
        $application = Tinebase_Application::getInstance()->getApplicationByName($_application);
        
        if ($application->status != 'enabled') {
            throw new Tinebase_Exception_AccessDenied('User has no rights. the application is disabled.');
        }
        
        $roleMemberships = $this->getRoleMemberships($_accountId);
                        
        $select = $this->_db->select()
        	->from(SQL_TABLE_PREFIX . 'role_rights', array('account_rights' => Tinebase_Backend_Sql_Command::getAggregateFunction($this->_db, $this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'role_rights.right'))))
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier(SQL_TABLE_PREFIX . 'role_rights.application_id') . ' = ?', $application->getId()))
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' IN (?)', $roleMemberships))
            ->group(SQL_TABLE_PREFIX . 'role_rights.application_id');
       
        $stmt = $this->_db->query($select);

        $row = $stmt->fetch(Zend_Db::FETCH_ASSOC);
        
        if ($row === false) {
            return array();
        }

        $rights = explode(',', $row['account_rights']);
        
        // remove duplicates
        $result = array();
        foreach ( $rights as $right ) {
            if ( !in_array($right, $result) ) {
                $result[] = $right;
            }
        }    
        
        return $result;
    }
        
    /**
     * Searches roles according to filter and paging
     * 
     * @param  Tinebase_Model_RoleFilter  $_filter
     * @param  Tinebase_Model_Pagination  $_paging
     * @return Tinebase_Record_RecordSet  Set of Tinebase_Model_Role
     */
    public function searchRoles($_filter, $_paging)
    {
        $select = $_filter->getSelect();
        
        $_paging->appendPaginationSql($select);
        
        return new Tinebase_Record_RecordSet('Tinebase_Model_Role', $this->_db->fetchAssoc($select));
    }

    /**
     * Returns roles count
     * 
     * @param Tinebase_Model_RoleFilter $_filter
     * @return int
     */
    public function searchCount($_filter)
    {
        $select = $_filter->getSelect();
        
        $roles = new Tinebase_Record_RecordSet('Tinebase_Model_Role', $this->_db->fetchAssoc($select));
        return count($roles);
    }
    
    /**
     * Returns role identified by its id
     * 
     * @param   int  $_roleId
     * @return  Tinebase_Model_Role  
     * @throws  Tinebase_Exception_InvalidArgument
     * @throws  Tinebase_Exception_NotFound
     */
    public function getRoleById($_roleId)
    {
        $roleId = (int)$_roleId;
        if ($roleId != $_roleId && $roleId <= 0) {
            throw new Tinebase_Exception_InvalidArgument('$_roleId must be integer and greater than 0');
        }
        
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $roleId);
        if (!$row = $this->_rolesTable->fetchRow($where)) {
            throw new Tinebase_Exception_NotFound("role with id $roleId not found");
        }
        
        $result = new Tinebase_Model_Role($row->toArray());
        
        return $result;
    }
    

    /**
     * Returns role identified by its name
     * 
     * @param   string $_roleName
     * @return  Tinebase_Model_Role  
     * @throws  Tinebase_Exception_NotFound
     */
    public function getRoleByName($_roleName)
    {            
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' = ?', $_roleName);

        if (!$row = $this->_rolesTable->fetchRow($where)) {
            throw new Tinebase_Exception_NotFound("Role $_roleName not found.");
        }
        
        $result = new Tinebase_Model_Role($row->toArray());
        
        return $result;
    }
    
    /**
     * Get multiple roles
     *
     * @param string|array $_ids Ids
     * @return Tinebase_Record_RecordSet
     */
    public function getMultiple($_ids)
    {
    	$result = new Tinebase_Record_RecordSet('Tinebase_Model_Role');
    	
    	if (! empty($_ids)) {
	        $select = $this->_rolesTable->select();
	        $select->where($this->_db->quoteIdentifier('id') . ' IN (?)', array_unique((array) $_ids));
	        
	        $rows = $this->_rolesTable->fetchAll($select);
	        foreach ($rows as $row) {
	        	$result->addRecord(new Tinebase_Model_Role($row->toArray()));
	        }
    	}
    	
    	return $result;
    }
    
    /**
     * Creates a single role
     * 
     * @param  Tinebase_Model_Role
     * @return Tinebase_Model_Role
     */
    public function createRole(Tinebase_Model_Role $_role)
    {
        $data = $_role->toArray();
        if(is_object(Tinebase_Core::getUser())) {
            $data['created_by'] = Tinebase_Core::getUser()->getId();
        }
        $data['creation_time'] = Tinebase_DateTime::now()->get(Tinebase_Record_Abstract::ISO8601LONG);
        
        $newId = $this->_rolesTable->insert($data); 
        
        if ($newId === NULL) {
           $newId = $this->_db->lastSequenceId(substr(SQL_TABLE_PREFIX . 'roles', 0,26) . '_seq');
        }
        
        $role = $this->getRoleById($newId);
        return $role;
    }
    
    /**
     * updates a single role
     * 
     * @param  Tinebase_Model_Role $_role
     * @return Tinebase_Model_Role
     */
    public function updateRole(Tinebase_Model_Role $_role)
    {
        $data = $_role->toArray();
        $data['last_modified_by'] = Tinebase_Core::getUser()->getId();
        $data['last_modified_time'] = Tinebase_DateTime::now()->get(Tinebase_Record_Abstract::ISO8601LONG);
        
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $_role->getId());
        $this->_rolesTable->update($data, $where); 
        
        $role = $this->getRoleById($_role->getId());
        return $role;
    }
    
    /**
     * Deletes roles identified by their identifiers
     * 
     * @param   string|array id(s) to delete
     * @return  void
     * @throws  Tinebase_Exception_Backend
     */
    public function deleteRoles($_ids)
    {        
        $ids = ( is_array($_ids) ) ? implode(",", $_ids) : $_ids;

        try {
            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($this->_db);
            
            // delete role acls/members first
            $this->_roleMembersTable->delete( "role_id in ( $ids )");
            $this->_roleRightsTable->delete( "role_id in ( $ids )");
            
            // delete role
            $this->_rolesTable->delete( "id in ( $ids )");
            
            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
            
        } catch (Exception $e) {
            Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' error while deleting role ' . $e->__toString());
            Tinebase_TransactionManager::getInstance()->rollBack();
            throw new Tinebase_Exception_Backend($e->getMessage());
        }
    }
    
    /**
     * Delete all Roles returned by {@see getRoles()} using {@see deleteRoles()}
     * @return void
     */
    public function deleteAllRoles()
    {
        $roleIds = array();
        $roles = $this->_rolesTable->fetchAll();
        foreach ($roles as $role) {
          $roleIds[] = $role->id;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Deleting ' . count($roles) .' roles');
        
        if (count($roles) > 0) {
            $this->deleteRoles($roleIds);
        }
    }
    
    /**
     * get list of role members 
     *
     * @param   int $_roleId
     * @return  array of array with account ids & types
     * @throws  Tinebase_Exception_AccessDenied
     */
    public function getRoleMembers($_roleId)
    {
        $roleId = (int)$_roleId;
        if ($roleId != $_roleId && $roleId <= 0) {
            throw new Tinebase_Exception_AccessDenied('$_roleId must be integer and greater than 0');
        }
        
        $members = array();
        
        $select = $this->_roleMembersTable->select();
        $select->where($this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' = ?', $_roleId));
        
        $rows = $this->_roleMembersTable->fetchAll($select)->toArray();
        
        return $rows;
    }

    /**
     * get list of role members 
     *
     * @param   int $_accountId
     * @return  array of array with account ids & types
     * @throws  Tinebase_Exception_NotFound
     */
    public function getRoleMemberships($_accountId)
    {
        $accountId = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupMemberships = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
        if(empty($groupMemberships)) {
            throw new Tinebase_Exception_NotFound('Any account must belong to at least one group. The account with accountId ' . $accountId . ' does not belong to any group.');        
        }
        
        $memberships = array();
        
        $select = $this->_roleMembersTable->select();
        $select ->where($this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . ' = ?', $_accountId) . ' AND ' . $this->_db->quoteInto($this->_db->quoteIdentifier('account_type') . ' = ?', Tinebase_Acl_Rights::ACCOUNT_TYPE_USER))
                ->orwhere($this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . ' IN (?)', $groupMemberships) . ' AND ' .  $this->_db->quoteInto($this->_db->quoteIdentifier('account_type') . ' = ?', Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP));
        
        $rows = $this->_roleMembersTable->fetchAll($select)->toArray();
        
        foreach ($rows as $membership) {
            $memberships[] = $membership['role_id'];
        }

        return $memberships;
    }

    /**
     * set role members 
     *
     * @param   int $_roleId
     * @param   array $_roleMembers with role members ("account_type" => account type, "account_id" => account id)
     * @throws  Tinebase_Exception_InvalidArgument
     */
    public function setRoleMembers($_roleId, array $_roleMembers)
    {
        $roleId = (int)$_roleId;
        if ($roleId != $_roleId && $roleId > 0) {
            throw new Tinebase_Exception_InvalidArgument('$_roleId must be integer and greater than 0');
        }
        
        // remove old members
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' = ?', $roleId);
        $this->_roleMembersTable->delete($where);
              
        $validTypes = array( Tinebase_Acl_Rights::ACCOUNT_TYPE_USER, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE);
        foreach ($_roleMembers as $member) {
            if (!in_array($member['type'], $validTypes)) {
                throw new Tinebase_Exception_InvalidArgument('account_type must be one of ' . 
                    implode(', ', $validTypes) . ' (values given: ' . 
                    print_r($member, true) . ')');
            }
            
            $data = array(
                'role_id'       => $roleId,
                'account_type'  => $member['type'],
                'account_id'    => $member['id'],
            );
            $this->_roleMembersTable->insert($data); 
        }
    }
    
    /**
     * set all roles an user is member of
     *
     * @param  array  $_account as role member ("account_type" => account type, "account_id" => account id)
     * @param  mixed  $_roleIds
     * @return array
     */
    public function setRoleMemberships($_account, $_roleIds)
    {
        if ($_roleIds instanceof Tinebase_Record_RecordSet) {
            $_roleIds = $_roleIds->getArrayOfIds();
        }
        
        if(count($_roleIds) === 0) {
            throw new Tinebase_Exception_InvalidArgument('user must belong to at least one role');
        }
        
        $validTypes = array( Tinebase_Acl_Rights::ACCOUNT_TYPE_USER, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE);

        if (! in_array($_account['type'], $validTypes)) {
            throw new Tinebase_Exception_InvalidArgument('account_type must be one of ' . 
                implode(', ', $validTypes) . ' (values given: ' . 
                print_r($_account, true) . ')');
        }
        
        $roleMemberships = $this->getRoleMemberships($_account['id']);
        
        $removeRoleMemberships = array_diff($roleMemberships, $_roleIds);
        $addRoleMemberships    = array_diff($_roleIds, $roleMemberships);
        
        foreach ($addRoleMemberships as $roleId) {
            $this->addRoleMember($roleId, $_account);
        }
        
        foreach ($removeRoleMemberships as $roleId) {
            $this->removeRoleMember($roleId, $_account);
        }
        
        return $this->getRoleMemberships($_account['id']);
    }
    
    /**
     * add a new member to a role
     *
     * @param  string  $_roleId
     * @param  array   $_account as role member ("account_type" => account type, "account_id" => account id)
     */
    public function addRoleMember($_roleId, $_account)
    {
    	$roleId = (int)$_roleId;
        if ($roleId != $_roleId && $roleId > 0) {
            throw new Tinebase_Exception_InvalidArgument('$_roleId must be integer and greater than 0');
        }
    	
        $validTypes = array( Tinebase_Acl_Rights::ACCOUNT_TYPE_USER, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE);

        if (! in_array($_account['type'], $validTypes)) {
            throw new Tinebase_Exception_InvalidArgument('account_type must be one of ' . 
                implode(', ', $validTypes) . ' (values given: ' . 
                print_r($_account, true) . ')');
        }
        
        $data = array(
            'role_id'       => $roleId,
            'account_type'  => $_account['type'],
            'account_id'    => $_account['id'],
        );
                
        try {
        	$this->_roleMembersTable->insert($data);
            
            // invalidate cache
            Tinebase_Core::get(Tinebase_Core::CACHE)->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('role'));     
                   
        } catch (Zend_Db_Statement_Exception $e) {
            // account is already member of this group
        }
    }
    
    /**
     * remove one member from the role
     *
     * @param  mixed  $_groupId
     * @param  array   $_account as role member ("account_type" => account type, "account_id" => account id)
     */
    public function removeRoleMember($_roleId, $_account)
    {
        $roleId = (int)$_roleId;
        if ($roleId != $_roleId && $roleId > 0) {
            throw new Tinebase_Exception_InvalidArgument('$_roleId must be integer and greater than 0');
        }
        
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . '= ?', $roleId),
            $this->_db->quoteInto($this->_db->quoteIdentifier('account_type') . '= ?', $_account['type']),
            $this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . '= ?', $_account['id']),
        );
         
        $this->_roleMembersTable->delete($where);
        
        // invalidate cache
        Tinebase_Core::get(Tinebase_Core::CACHE)->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('role'));
    }
    
    /**
     * get list of role rights 
     *
     * @param   int $_roleId
     * @return  array of array with application ids & rights
     * @throws  Tinebase_Exception_InvalidArgument
     */
    public function getRoleRights($_roleId)
    {
        $roleId = (int)$_roleId;
        if ($roleId != $_roleId || $roleId <= 0) {
            throw new Tinebase_Exception_InvalidArgument('$_roleId must be integer and greater than 0');
        }
        
        $rights = array();
        
        $select = $this->_roleRightsTable->select();
        $select->where($this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' = ?', $_roleId));
        
        $rows = $this->_roleRightsTable->fetchAll($select)->toArray();
        
        foreach ($rows as $right) {
            $rights[] = array ( 
                'application_id'    => $right['application_id'], 
                'right'             => $right['right']
            );
        }
        return $rights;
    }

    /**
     * set role rights 
     *
     * @param   int $_roleId
     * @param   array $_roleRights with role rights ("application_id" => app id, "right" => the right to set)
     * @throws  Tinebase_Exception_InvalidArgument
     */
    public function setRoleRights($_roleId, array $_roleRights)
    {
        $roleId = (int)$_roleId;
        if ( $roleId != $_roleId && $roleId > 0 ) {
            throw new Tinebase_Exception_InvalidArgument('$_roleId must be integer and greater than 0');
        }
        
        // remove old rights
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' = ?', $roleId);
        $this->_roleRightsTable->delete($where);
                
        foreach ( $_roleRights as $right ) {
            $data = array(
                'role_id'           => $roleId,
                'application_id'    => $right['application_id'],
                'right'             => $right['right'],
            );
            $this->_roleRightsTable->insert($data); 
        }
        
        // invalidate cache
        Tinebase_Core::get(Tinebase_Core::CACHE)->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('rights'));
    }

    /**
     * add single role rights 
     *
     * @param   int $_roleId
     * @param   int $_applicationId
     * @param   string $_right
     */
    public function addSingleRight($_roleId, $_applicationId, $_right)
    {        
        // check if already in
        $select = $this->_roleRightsTable->select();
        $select->where($this->_db->quoteInto($this->_db->quoteIdentifier('role_id') . ' = ?', $_roleId))
               ->where($this->_db->quoteInto($this->_db->quoteIdentifier('right') . ' = ?', $_right))
               ->where($this->_db->quoteInto($this->_db->quoteIdentifier('application_id') . ' = ?', $_applicationId));
        
        if (!$row = $this->_roleRightsTable->fetchRow($select)) {                        
            $data = array(
                'role_id'           => $_roleId,
                'application_id'    => $_applicationId,
                'right'             => $_right,
            );
            $this->_roleRightsTable->insert($data); 
        }
    }
    
    /**
     * Create initial Roles
     * 
     * @todo make hard coded role names ('user role' and 'admin role') configurable
     * 
     * @return void
     */
    public function createInitialRoles()
    {
        $groupsBackend = Tinebase_Group::getInstance();
        
        $adminGroup = $groupsBackend->getDefaultAdminGroup();
        $userGroup  = $groupsBackend->getDefaultGroup();
        
        // add roles and add the groups to the roles
        $adminRole = new Tinebase_Model_Role(array(
            'name'                  => 'admin role',
            'description'           => 'admin role for tine. this role has all rights per default.',
        ));
        $adminRole = $this->createRole($adminRole);
        $this->setRoleMembers($adminRole->getId(), array(
            array(
                'id'    => $adminGroup->getId(),
                'type'  => Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, 
            )
        ));
        
        $userRole = new Tinebase_Model_Role(array(
            'name'                  => 'user role',
            'description'           => 'userrole for tine. this role has only the run rights for all applications per default.',
        ));
        $userRole = $this->createRole($userRole);
        $this->setRoleMembers($userRole->getId(), array(
            array(
                'id'    => $userGroup->getId(),
                'type'  => Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, 
            )
        ));
    }
    
}
