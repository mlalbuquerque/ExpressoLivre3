<?php
/**
 * Tine 2.0
 *
 * @package     ActiveSync
 * @license     http://www.tine20.org/licenses/agpl-nonus.txt AGPL Version 1 (Non-US)
 *              NOTE: According to sec. 8 of the AFFERO GENERAL PUBLIC LICENSE (AGPL), 
 *              Version 1, the distribution of the Tine 2.0 ActiveSync module in or to the 
 *              United States of America is excluded from the scope of this license.
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * sql backend class for the folder state
 *
 * @package     ActiveSync
 */
class ActiveSync_Backend_Folder extends Tinebase_Backend_Sql_Abstract
{
    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'acsync_folder';
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'ActiveSync_Model_Folder';
        
    /**
     * delete all stored folderId's for given device
     *
     * @param Syncope_Model_IDevice  $_deviceId
     * @param string $_class
     */
    public function resetState(Syncope_Model_IDevice $_deviceId)
    {
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('device_id') . ' = ?', $_deviceId->getId())
        );
        
        $this->_db->delete(SQL_TABLE_PREFIX . $this->_tableName, $where);
    }
    
    /**
     * get array of ids which got send to the client for a given class
     *
     * @param Syncope_Model_IDevice  $_deviceId
     * @param string $_class
     * @return array
     */
    public function getClientState(Syncope_Model_IDevice  $_deviceId, $_class)
    {
        $select = $this->_getSelect('folderid');
        $select->where($this->_db->quoteIdentifier('device_id') . ' = ?', $_deviceId->getId())
            ->where($this->_db->quoteIdentifier('class') . ' = ?', $_class);
        
        $stmt = $this->_db->query($select);
        $result = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);

        return $result;
    }
}
