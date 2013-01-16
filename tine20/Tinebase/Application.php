<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Application
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 *
 * @todo        add 'getTitleTranslation' function?
 * @todo        use Tinebase_Backend_Sql?
 * @todo        migrate from Zend_Db_Table to plain Zend_Db
 */

/**
 * the class provides functions to handle applications
 * 
 * @package     Tinebase
 * @subpackage  Application
 */
class Tinebase_Application
{
    /**
     * application enabled
     *
     */
    const ENABLED  = 'enabled';
    
    /**
     * application disabled
     *
     */
    const DISABLED = 'disabled';
    
    /**
     * the table object for the SQL_TABLE_PREFIX . applications table
     *
     * @var Zend_Db_Table_Abstract
     */
    protected $_applicationTable;

    /**
     * Table name
     *
     * @var string
     */
    protected $_tableName;

    /**
     * application objects cache
     * 
     * @var array (id/name => Tinebase_Model_Application)
     */
    protected $_applicationCache = array();
    
    /**
     * the db adapter
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_db = '';
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() 
    {
        $this->_tableName = SQL_TABLE_PREFIX . 'applications';
        $this->_applicationTable = new Tinebase_Db_Table(array('name' => $this->_tableName));
        $this->_db = Tinebase_Core::getDb();
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {        
    }

    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_Application
     */
    private static $instance = NULL;
    
    /**
     * Returns instance of Tinebase_Application
     *
     * @return Tinebase_Application
     */
    public static function getInstance() 
    {
        if (self::$instance === NULL) {
            self::$instance = new Tinebase_Application;
        }
        
        return self::$instance;
    }
    
    /**
     * returns one application identified by id
     *
     * @param Tinebase_Model_Application|string $_applicationId the id of the application
     * @throws Tinebase_Exception_NotFound
     * @return Tinebase_Model_Application the information about the application
     */
    public function getApplicationById($_applicationId)
    {
        $applicationId = Tinebase_Model_Application::convertApplicationIdToInt($_applicationId);
        
        if (isset($this->_applicationCache[$applicationId])) {
            return $this->_applicationCache[$applicationId];
        }
        
        $cache = Tinebase_Core::get(Tinebase_Core::CACHE);
        if ($cache instanceof Zend_Cache_Core) {
            $cacheId = 'getApplicationById_' . $_applicationId;
            if ($cache->test($cacheId)) {
                $result = $cache->load($cacheId);
                
                $this->_addToClassCache($result);
                
                return $result;
            }
        }
        
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?' , $applicationId);
        $rows = $this->_applicationTable->fetchAll($where)->toArray();
        
        if (empty($rows)) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Application not found. Id: ' . $applicationId);
            throw new Tinebase_Exception_NotFound('Application not found.');
        }
        
        $result = new Tinebase_Model_Application($rows[0], TRUE);
        
        if ($cache instanceof Zend_Cache_Core) {
            $cache->save($result, $cacheId, array('applications'));
            $cache->save($result, 'getApplicationByName_' . $result->name, array('applications'));
        }
        
        $this->_addToClassCache($result);
        
        return $result;
    }

    /**
     * returns one application identified by application name
     * - results are cached
     *
     * @param string $_applicationName the name of the application
     * @return Tinebase_Model_Application the information about the application
     * @throws Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function getApplicationByName($_applicationName)
    {
        if(empty($_applicationName) || ! is_string($_applicationName)) {
            throw new Tinebase_Exception_InvalidArgument('$_applicationName can not be empty / has to be string.');
        }
        
        if (isset($this->_applicationCache[$_applicationName])) {
            return $this->_applicationCache[$_applicationName];
        }
        
        $cache = Tinebase_Core::get(Tinebase_Core::CACHE);
        
        if ($cache instanceof Zend_Cache_Core) {
            $cacheId = 'getApplicationByName_' . $_applicationName;
            if ($cache->test($cacheId)) {
                $result = $cache->load($cacheId);
                
                $this->_addToClassCache($result);
                
                return $result;
            }
        } 
        
        $select = $this->_db->select();
        $select->from($this->_tableName)
               ->where($this->_db->quoteIdentifier('name') . ' = ?', $_applicationName);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' getting application by name: ' . $select->assemble());

        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();
        
        if (!$queryResult) {
            throw new Tinebase_Exception_NotFound("Application $_applicationName not found.");
        }
        $result = new Tinebase_Model_Application($queryResult, TRUE);
        
        if ($cache instanceof Zend_Cache_Core) {
            $cache->save($result, $cacheId, array('applications'));
            $cache->save($result, 'getApplicationById_' . $result->getId(), array('applications'));
        }
        
        $this->_addToClassCache($result);

        return $result;
    }
    
    /**
     * get list of installed applications
     *
     * @param string $_sort optional the column name to sort by
     * @param string $_dir optional the sort direction can be ASC or DESC only
     * @param string $_filter optional search parameter
     * @param int $_limit optional how many applications to return
     * @param int $_start optional offset for applications
     * @return Tinebase_RecordSet_Application
     */
    public function getApplications($_filter = NULL, $_sort = 'id', $_dir = 'ASC', $_start = NULL, $_limit = NULL)
    {
        $where = array();
        if($_filter !== NULL) {
            $where[] = $this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' LIKE ?', '%' . $_filter . '%');
        }
        
        $rowSet = $this->_applicationTable->fetchAll($where, $_sort, $_dir, $_limit, $_start);

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Application', $rowSet->toArray(), TRUE);

        return $result;
    }    
    
    /**
     * get enabled or disabled applications
     *
     * @param int $_state can be Tinebase_Application::ENABLED or Tinebase_Application::DISABLED
     * @return Tinebase_Record_RecordSet list of applications
     */
    public function getApplicationsByState($_status)
    {
        if($_status !== Tinebase_Application::ENABLED && $_status !== Tinebase_Application::DISABLED) {
            throw new Tinebase_Exception_InvalidArgument('$_status can be only Tinebase_Application::ENABLED or Tinebase_Application::DISABLED');
        }
        $where[] = $this->_db->quoteInto($this->_db->quoteIdentifier('status') . ' = ?', $_status);
        
        $rowSet = $this->_applicationTable->fetchAll($where);

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Application', $rowSet->toArray(), TRUE);

        return $result;
    }    
    
    /**
     * return the total number of applications installed
     *
     * @param $_filter
     * 
     * @return int
     */
    public function getTotalApplicationCount($_filter = NULL)
    {
        $where = array();
        if($_filter !== NULL) {
            $where[] = $this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' LIKE ?', '%' . $_filter . '%');
        }
        $count = $this->_applicationTable->getTotalCount($where);
        
        return $count;
    }
    
    /**
     * return if application is installed
     *
     * @param  string  $_applicationName  the application name
     * 
     * @return bool
     */
    public function isInstalled($_applicationName)
    {
        try {
            $this->getApplicationByName($_applicationName);
            
            return true;
        } catch (Tinebase_Exception_NotFound $tenf) {
            return false;
        }
    }
    
    /**
     * set application state
     *
     * @param   array $_applicationIds application ids to set new state for
     * @param   string $_state the new state
     * @throws  Tinebase_Exception_InvalidArgument
     */
    public function setApplicationState(array $_applicationIds, $_state)
    {
        if($_state != Tinebase_Application::DISABLED && $_state != Tinebase_Application::ENABLED) {
            throw new Tinebase_Exception_InvalidArgument('$_state can be only Tinebase_Application::DISABLED  or Tinebase_Application::ENABLED');
        }
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' IN (?)', $_applicationIds)
        );
        
        $data = array(
            'status' => $_state
        );
        
        $affectedRows = $this->_applicationTable->update($data, $where);
        
        $this->_cleanCache();
        //error_log("AFFECTED:: $affectedRows");
    }
    
    /**
     * add new appliaction 
     *
     * @param Tinebase_Model_Application $_application the new application object
     * @return Tinebase_Model_Application the new application with the applicationId set
     */
    public function addApplication(Tinebase_Model_Application $_application)
    {
        if (empty($_application->id)) {
            $newId = $_application->generateUID();
            $_application->setId($newId);
        }
        
        $data = $_application->toArray();
        unset($data['tables']);
        
        $this->_applicationTable->insert($data);
        
        $result = $this->getApplicationById($_application->id);
        
        return $result;
    }
    
    /**
     * get all possible application rights
     *
     * @param   int application id
     * @return  array   all application rights
     */
    public function getAllRights($_applicationId)
    {
        $application = Tinebase_Application::getInstance()->getApplicationById($_applicationId);
        
        // call getAllApplicationRights for application (if it has specific rights)
        $appAclClassName = $application->name . '_Acl_Rights';
        if (@class_exists($appAclClassName)) {
            $appAclObj = call_user_func(array($appAclClassName, 'getInstance'));
            $allRights = $appAclObj->getAllApplicationRights();
        } else {
            $allRights = Tinebase_Acl_Rights::getInstance()->getAllApplicationRights($application->name);   
        }
        
        return $allRights;
    }

    /**
     * get right description
     *
     * @param   int     application id
     * @param   string  right
     * @return  array   right description
     */
    public function getAllRightDescriptions($_applicationId)
    {
        $application = Tinebase_Application::getInstance()->getApplicationById($_applicationId);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Getting right descriptions for ' . $application->name );
        
        // call getAllApplicationRights for application (if it has specific rights)
        $appAclClassName = $application->name . '_Acl_Rights';
        if (! @class_exists($appAclClassName)) {
            $appAclClassName = 'Tinebase_Acl_Rights';
        }
        
        $descriptions = call_user_func(array($appAclClassName, 'getTranslatedRightDescriptions'));
        return $descriptions;
    }
    
    /**
     * get tables of application
     *
     * @param Tinebase_Model_Application $_applicationId
     * @return array
     */
    public function getApplicationTables($_applicationId)
    {
        $applicationId = Tinebase_Model_Application::convertApplicationIdToInt($_applicationId);
        
        $select = $this->_db->select()
            ->from(SQL_TABLE_PREFIX . 'application_tables', array('name'))
            ->where($this->_db->quoteIdentifier('application_id') . ' = ?', $applicationId);
            
        $stmt = $this->_db->query($select);
        $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);

        if($rows === NULL) {
            return array();
        }
        
        $tables = array();
        foreach($rows as $row) {
            $tables[] = $row['name'];
        }
        return $tables;
    }
    
    /**
     * remove table from application_tables table
     *
     * @param Tinebase_Model_Application|string $_applicationId the applicationId
     * @param string $_tableName the table name
     */
    public function removeApplicationTable($_applicationId, $_tableName)
    {
        $applicationId = Tinebase_Model_Application::convertApplicationIdToInt($_applicationId);
        
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('application_id') . '= ?', $applicationId),
            $this->_db->quoteInto($this->_db->quoteIdentifier('name') . '= ?', $_tableName)
        );
        
        $this->_db->delete(SQL_TABLE_PREFIX . 'application_tables', $where);
    }
    
    /**
     * remove application from applications table
     *
     * @param Tinebase_Model_Application|string $_applicationId the applicationId
     */
    public function deleteApplication($_applicationId)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Removing app ' . $_applicationId . ' from applications table.');
        
        $applicationId = Tinebase_Model_Application::convertApplicationIdToInt($_applicationId);
        
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('id') . '= ?', $applicationId)
        );
        
        $this->_db->delete(SQL_TABLE_PREFIX . 'applications', $where);
        
        $this->_cleanCache($applicationId);
    }
    
    /**
     * add table to tine registry
     *
     * @param Tinebase_Model_Application
     * @param string name of table
     * @param int version of table
     * @return int
     */
    public function addApplicationTable($_applicationId, $_name, $_version)
    {
        $applicationId = Tinebase_Model_Application::convertApplicationIdToInt($_applicationId);
        
        $applicationData = array(
            'application_id'    => $applicationId,
            'name'              => $_name,
            'version'           => $_version
        );
        
        $this->_db->insert(SQL_TABLE_PREFIX . 'application_tables', $applicationData);
    }
    
    /**
     * update application
     * 
     * @param Tinebase_Model_Application $_application
     * @return Tinebase_Model_Application
     */
    public function updateApplication(Tinebase_Model_Application $_application)
    {
        $backend = new Tinebase_Backend_Sql(array(
            'modelName' => 'Tinebase_Model_Application', 
            'tableName' => 'applications',
        ));
        
        $result = $backend->update($_application);
        
        $this->_cleanCache($result);
        
        return $result;
    }
    
    
    /**
     * delete containers, configs and other data of an application
     * 
     * NOTE: if a table with foreign key constraints to applications is added, we need to make sure that the data is deleted here 
     * 
     * @param Tinebase_Model_Application $_applicationName
     * @return void
     */
    public function removeApplicationData(Tinebase_Model_Application $_application)
    {
        $dataToDelete = array(
            'container'     => array('tablename' => ''),
            'config'        => array('tablename' => ''),
            'customfield'	=> array('tablename' => ''),
            'rights'        => array('tablename' => 'role_rights'),
            'definitions'   => array('tablename' => 'importexport_definition'),
            'filter'        => array('tablename' => 'filter'),
        );
        $countMessage = ' Deleted';
        
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('application_id') . '= ?', $_application->getId())
        );        
        foreach ($dataToDelete as $dataType => $info) {
            switch ($dataType) {
                case 'container':
                    $count = Tinebase_Container::getInstance()->deleteContainerByApplicationId($_application->getId());
                    break;
                case 'config':
                    $count = Tinebase_Config::getInstance()->deleteConfigByApplicationId($_application->getId());
                    break;
              	case 'customfield':
              		$count = Tinebase_CustomField::getInstance()->deleteCustomFieldsForApplication($_application->getId());
              		break;
                default:
                    if (array_key_exists('tablename', $info) && ! empty($info['tablename'])) {
                        $count = $this->_db->delete(SQL_TABLE_PREFIX . $info['tablename'], $where);
                    } else {
                        Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' No tablename defined for ' . $dataType);
                        $count = 0;
                    }
            }
            $countMessage .= ' ' . $count . ' ' . $dataType . '(s) /';
        }
        
        $countMessage .= ' for application ' . $_application->name;
        Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . $countMessage);
    }            
    
    /**
     * add TMA to "in class" cache and to Zend Cache
     * 
     * @param  Tinebase_Model_Application  $_application
     */
    protected function _addToClassCache(Tinebase_Model_Application $_application)
    {
        $this->_applicationCache[$_application->getId()] = $_application;
        $this->_applicationCache[$_application->name]    = $_application;
    }
    
    /**
     * remove TMA from "in class" cache and Zend Cache
     * 
     * remove cache entry for given class only, when application id AND name are known
     * otherwise forget all cached TMA
     * 
     * @return void
     */
    protected function _cleanCache($_applicationId = null)
    {
        if ($_applicationId instanceof Tinebase_Model_Application) {
            $application = $_applicationId;
        } elseif (isset($this->_applicationCache[$_applicationId])) {
            $application = $this->_applicationCache[$_applicationId];
        }

        /*
         *  we always reset the "in class" cache. Otherwise we rum into problems when we 
         *  try to uninstall and install all applications again
         *  hint: Tinebase can't be uninstalled because the app tables got droped before 
         */
        if (isset($application)) {
            // remove from Zend Cache
            $cacheId = 'getApplicationById_' . $application->getId();
            Tinebase_Core::get(Tinebase_Core::CACHE)->remove($cacheId);
            
            $cacheId = 'getApplicationByName_' . $application->name;
            Tinebase_Core::get(Tinebase_Core::CACHE)->remove($cacheId);
        } else {
            Tinebase_Core::get(Tinebase_Core::CACHE)->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('applications'));
            
        }
        
        $this->_applicationCache = array();
    }
}
