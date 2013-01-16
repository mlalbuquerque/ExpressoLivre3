<?php
/**
 * Tine 2.0
 *
 * @package     ActiveSync
 * @license     http://www.tine20.org/licenses/agpl-nonus.txt AGPL Version 1 (Non-US)
 *              NOTE: According to sec. 8 of the AFFERO GENERAL PUBLIC LICENSE (AGPL), 
 *              Version 1, the distribution of the Tine 2.0 ActiveSync module in or to the 
 *              United States of America is excluded from the scope of this license.
 * @copyright   Copyright (c) 2008-2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * class documentation
 *
 * @package     ActiveSync
 */
class ActiveSync_Command_FolderCreate extends ActiveSync_Command_Wbxml 
{        
    protected $_defaultNameSpace    = 'uri:FolderHierarchy';
    protected $_documentElement     = 'FolderCreate';
    
    protected $_classes             = array('Contacts', 'Tasks', 'Email');
    
    /**
     * synckey sent from client
     *
     * @var string
     */
    protected $_syncKey;
    protected $_parentId;
    protected $_displayName;
    protected $_type;
    
    /**
     * the folderState sql backend
     *
     * @var ActiveSync_Backend_FolderState
     */
    protected $_folderStateBackend;
    
    /**
     * instance of ActiveSync_Controller
     *
     * @var ActiveSync_Controller
     */
    protected $_controller;
    
    /**
     * the constructor
     *
     * @param  mixed                    $_requestBody
     * @param  ActiveSync_Model_Device  $_device
     * @param  string                   $_policyKey
     */
    public function __construct($_requestBody, ActiveSync_Model_Device $_device = null, $_policyKey = null)
    {
        parent::__construct($_requestBody, $_device, $_policyKey);
            
        $this->_folderStateBackend   = new ActiveSync_Backend_FolderState();
        $this->_controller           = ActiveSync_Controller::getInstance();

    }
    
    /**
     * parse FolderCreate request
     *
     */
    public function handle()
    {
        $xml = simplexml_import_dom($this->_inputDom);
        
        $this->_syncKey     = (int)$xml->SyncKey;
        $this->_parentId    = (string)$xml->ParentId;
        $this->_displayName = (string)$xml->DisplayName;
        $this->_type        = (int)$xml->Type;
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " synckey is $this->_syncKey");        
        
        switch((int)$xml->Type) {
            case ActiveSync_Command_FolderSync::FOLDERTYPE_CALENDAR_USER_CREATED:
                break;
                
            case ActiveSync_Command_FolderSync::FOLDERTYPE_CONTACT_USER_CREATED:
                break;
                
            case ActiveSync_Command_FolderSync::FOLDERTYPE_MAIL_USER_CREATED:
                break;
                
            case ActiveSync_Command_FolderSync::FOLDERTYPE_TASK_USER_CREATED:
                break;
        }
        
    }
    
    /**
     * generate FolderCreate response
     */
    public function getResponse()
    {
        $folderCreate = $this->_outputDom->documentElement;
        
        if($this->_syncKey > '0' && $this->_controller->validateSyncKey($this->_device, $this->_syncKey, 'FolderSync') === false) {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " INVALID synckey");
            $folderCreate->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Status', ActiveSync_Command_FolderSync::STATUS_INVALID_SYNC_KEY));
        } else {
            $newSyncKey = $this->_syncKey + 1;
            
            $folderId = Tinebase_Record_Abstract::generateUID();
            
            // create xml output
            $folderCreate->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Status', ActiveSync_Command_FolderSync::STATUS_SUCCESS));
            $folderCreate->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'SyncKey', $newSyncKey));
            $folderCreate->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'ServerId', $folderId));

            $this->_addFolderState('Email', $folderId);
            
            $this->_controller->updateSyncKey($this->_device, $newSyncKey, $this->_syncTimeStamp, 'FolderSync');
        }
        
        return $this->_outputDom;
    }
    
    /**
     * save folderstate (aka: remember that we have sent the folder to the client)
     *
     * @param string $_class the class from the xml
     * @param string $_folderId the Tine 2.0 id of the folder
     */
    protected function _addFolderState($_class, $_folderId)
    {
        $folderState = new ActiveSync_Model_FolderState(array(
            'device_id'     => $this->_device->getId(),
            'class'         => $_class,
            'folderid'      => $_folderId,
            'creation_time' => $this->_syncTimeStamp
        ));
        
        /**
         * if the entry got added earlier, and there was an error, the entry gets added again
         * @todo it's better to wrap the whole process into a transation
         */
        try {
            $this->_folderStateBackend->create($folderState);
        } catch (Zend_Db_Statement_Exception $e) {
            $this->_deleteFolderState($_class, $_folderId);
            $this->_folderStateBackend->create($folderState);
        }
    }
    
    /**
     * delete folderstate (aka: forget that we have sent the folder to the client)
     *
     * @param string $_class the class from the xml
     * @param string $_contentId the Tine 2.0 id of the folder
     */
    protected function _deleteFolderState($_class, $_folderId)
    {
        $folderStateFilter = new ActiveSync_Model_FolderStateFilter(array(
            array(
                    'field'     => 'device_id',
                    'operator'  => 'equals',
                    'value'     => $this->_device->getId()
            ),
            array(
                    'field'     => 'class',
                    'operator'  => 'equals',
                    'value'     => $_class
            ),
            array(
                    'field'     => 'folderid',
                    'operator'  => 'equals',
                    'value'     => $_folderId
            )
        ));
        $state = $this->_folderStateBackend->search($folderStateFilter, NULL, true);
        
        if(count($state) > 0) {
            $this->_folderStateBackend->delete($state[0]);
        } else {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " no folderstate found for " . print_r($folderStateFilter->toArray(), true));
        }
    }    
}
