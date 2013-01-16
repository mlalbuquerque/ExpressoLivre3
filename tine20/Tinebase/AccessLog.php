<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */ 

/**
 * this class provides functions to get, add and remove entries from/to the access log
 * 
 * @package     Tinebase
 */
class Tinebase_AccessLog extends Tinebase_Controller_Record_Abstract
{
    /**
     * @var Tinebase_Backend_Sql
     */
    protected $_backend;
    
    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_AccessLog
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     *
     */
    private function __construct()
    {
        $this->_modelName = 'Tinebase_Model_AccessLog';
        $this->_omitModLog = TRUE;
        $this->_doContainerACLChecks = FALSE;
        
        $this->_backend = new Tinebase_Backend_Sql(array(
            'modelName' => $this->_modelName, 
            'tableName' => 'access_log',
        ));
    }
    
    /**
     * the singleton pattern
     *
     * @return Tinebase_AccessLog
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tinebase_AccessLog;
        }
        
        return self::$_instance;
    }

    /**
     * add logout entry to the access log
     *
     * @param string $_sessionId the session id
     * @param string $_ipAddress the ip address the user connects from
     * @return void|Tinebase_Model_AccessLog
     */
    public function setLogout($_sessionId, $_ipAddress = NULL)
    {
        try {
            $loginRecord = $this->_backend->getByProperty($_sessionId, 'sessionid');
        } catch (Tinebase_Exception_NotFound $tenf) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Could not find access log login record for session id ' . $_sessionId);
            return;
        }
        
        $loginRecord->lo = Tinebase_DateTime::now()->get(Tinebase_Record_Abstract::ISO8601LONG);
        if ($_ipAddress !== NULL) {
            $loginRecord->ip = $_ipAddress;
        }
        
        return $this->update($loginRecord);
    }
}
