<?php
/**
 * Syncope
 *
 * @package     Syncope
 * @subpackage  Command
 * @license     http://www.tine20.org/licenses/lgpl.html LGPL Version 3
 * @copyright   Copyright (c) 2008-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * class to handle ActiveSync FolderSync command
 *
 * @package     Syncope
 * @subpackage  Command
 */
class Syncope_Command_FolderSync extends Syncope_Command_Wbxml 
{
    const STATUS_SUCCESS                = 1;
    const STATUS_FOLDER_EXISTS          = 2;
    const STATUS_IS_SPECIAL_FOLDER      = 3;
    const STATUS_FOLDER_NOT_FOUND       = 4;
    const STATUS_PARENT_FOLDER_NOT_FOUND = 5;
    const STATUS_SERVER_ERROR           = 6;
    const STATUS_ACCESS_DENIED          = 7;
    const STATUS_REQUEST_TIMED_OUT      = 8;
    const STATUS_INVALID_SYNC_KEY       = 9;
    const STATUS_MISFORMATTED           = 10;
    const STATUS_UNKNOWN_ERROR          = 11;

    /**
     * some usefull constants for working with the xml files
     *
     */
    const FOLDERTYPE_GENERIC_USER_CREATED   = 1;
    const FOLDERTYPE_INBOX                  = 2;
    const FOLDERTYPE_DRAFTS                 = 3;
    const FOLDERTYPE_DELETEDITEMS           = 4;
    const FOLDERTYPE_SENTMAIL               = 5;
    const FOLDERTYPE_OUTBOX                 = 6;
    const FOLDERTYPE_TASK                   = 7;
    const FOLDERTYPE_CALENDAR               = 8;
    const FOLDERTYPE_CONTACT                = 9;
    const FOLDERTYPE_NOTE                   = 10;
    const FOLDERTYPE_JOURNAL                = 11;
    const FOLDERTYPE_MAIL_USER_CREATED      = 12;
    const FOLDERTYPE_CALENDAR_USER_CREATED  = 13;
    const FOLDERTYPE_CONTACT_USER_CREATED   = 14;
    const FOLDERTYPE_TASK_USER_CREATED      = 15;
    const FOLDERTYPE_JOURNAL_USER_CREATED   = 16;
    const FOLDERTYPE_NOTES_USER_CREATED     = 17;
    const FOLDERTYPE_UNKOWN                 = 18;
    
    protected $_defaultNameSpace    = 'uri:FolderHierarchy';
    protected $_documentElement     = 'FolderSync';
    
    protected $_classes             = array(
        Syncope_Data_Factory::CLASS_CALENDAR,
        Syncope_Data_Factory::CLASS_CONTACTS,
        Syncope_Data_Factory::CLASS_EMAIL,
        Syncope_Data_Factory::CLASS_TASKS
    );

    /**
     * @var string
     */
    protected $_syncKey;
   
    /**
     * parse FolderSync request
     *
     */
    public function handle()
    {
        $xml = simplexml_import_dom($this->_inputDom);
        $syncKey = (int)$xml->SyncKey;
        
        if ($this->_logger instanceof Zend_Log) 
            $this->_logger->debug(__METHOD__ . '::' . __LINE__ . " synckey is $syncKey");
        
        if ($syncKey == 0) {
            $this->_syncState = new Syncope_Model_SyncState(array(
                'device_id' => $this->_device,
                'counter'   => 0,
                'type'      => 'FolderSync',
                'lastsync'  => $this->_syncTimeStamp
            ));
            
            // reset state of foldersync
            $this->_syncStateBackend->resetState($this->_device, 'FolderSync');
            $this->_folderBackend->resetState($this->_device);
        } else {
            if (($this->_syncState = $this->_syncStateBackend->validate($this->_device, 'FolderSync', $syncKey)) instanceof Syncope_Model_SyncState) {
                $this->_syncState->lastsync = $this->_syncTimeStamp;
            } else  {
                $this->_syncStateBackend->resetState($this->_device, 'FolderSync');
                $this->_folderBackend->resetState($this->_device);
            }
        }
    }
    
     /**
     * generate FolderSync response
     * 
     * @todo changes are missing in response (folder got renamed for example)
     */
    public function getResponse()
    {
        $folderSync = $this->_outputDom->documentElement;
        
        if($this->_syncState === false) {
            if ($this->_logger instanceof Zend_Log) 
                $this->_logger->info(__METHOD__ . '::' . __LINE__ . " INVALID synckey provided");
            $folderSync->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Status', self::STATUS_INVALID_SYNC_KEY));

        } else {
            $folderSync->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Status', self::STATUS_SUCCESS));
            
            $adds = array();
            $deletes = array();
            $updates = array();
            
            foreach($this->_classes as $class) {
                try {
                    $dataController = Syncope_Data_Factory::factory($class, $this->_device, $this->_syncTimeStamp);
                } catch (Zend_Exception $ze) {
                    // backend not defined
                    if ($this->_logger instanceof Zend_Log)
                        $this->_logger->info(__METHOD__ . '::' . __LINE__ . " no data backend defined for class: " . $class);
                    continue;
                }
                
                // retrieve all folders available in data backend
                $serverFolders = $dataController->getAllFolders();
                // retrieve all folders sent to client
                $clientFolders = $this->_folderBackend->getFolderState($this->_device, $class);
                
                $serverFoldersIds = array_keys($serverFolders);
                
                // is this the first sync?
                if($this->_syncState->counter == 0) {
                    $clientFoldersIds = array();
                } else {
                    $clientFoldersIds = array_keys($clientFolders);
                } 
                               
                // calculate added entries
                $serverDiff = array_diff($serverFoldersIds, $clientFoldersIds);
                foreach($serverDiff as $serverFolderId) {
                    if (isset($clientFolders[$serverFolderId])) {
                        $adds[] = $clientFolders[$serverFolderId];
                    } else {
                        $adds[] = new Syncope_Model_Folder(array(
                            'device_id'         => $this->_device,
                            'class'             => $class,
                            'folderid'          => $serverFolders[$serverFolderId]['folderId'],
                            'parentid'          => $serverFolders[$serverFolderId]['parentId'],
                            'displayname'       => $serverFolders[$serverFolderId]['displayName'],
                            'type'              => $serverFolders[$serverFolderId]['type'],
                            'creation_time'     => $this->_syncTimeStamp,
                            'lastfiltertype'    => null
                        ));
                    }
                }
                
                // calculate deleted entries
                $serverDiff = array_diff($clientFoldersIds, $serverFoldersIds);
                foreach($serverDiff as $serverFolderId) {
                    $deletes[] = $clientFolders[$serverFolderId];
                }
                
                // calculate changed entries
                $serverIntersect = array_intersect($clientFoldersIds, $serverFoldersIds);
                foreach ($serverIntersect as $serverIntersectId)
                {
                	if (($serverFolders[$serverIntersectId][parentId] != $clientFolders[$serverIntersectId]->parentid) or 
                			($serverFolders[$serverIntersectId][displayName] != $clientFolders[$serverIntersectId]->displayname)) 
                	{
                		$updates[] = new Syncope_Model_Folder(array(
                				'id'                => $clientFolders[$serverIntersectId]->id,
                				'device_id'         => $this->_device,
                				'class'             => $class,
                				'folderid'          => $serverFolders[$serverIntersectId]['folderId'],
                				'parentid'          => $serverFolders[$serverIntersectId]['parentId'],
                				'displayname'       => $serverFolders[$serverIntersectId]['displayName'],
                				'type'              => $serverFolders[$serverIntersectId]['type'],
                				'creation_time'     => $this->_syncTimeStamp,
                				'lastfiltertype'    => null
                		));
                	}
                }
            }
            
            $count = count($adds) + count($updates) + count($deletes);
            if($count > 0) {
                $this->_syncState->counter++;
            }
            
            // create xml output
            $folderSync->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'SyncKey', $this->_syncState->counter));
            
            $changes = $folderSync->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Changes'));            
            $changes->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Count', $count));
            
            foreach($adds as $folder) {
                
                $add = $changes->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Add'));
                $add->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'ServerId', $folder->folderid));
                $add->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'ParentId', $folder->parentid));
                
                $displayName = $this->_outputDom->createElementNS('uri:FolderHierarchy', 'DisplayName');
                $displayName->appendChild($this->_outputDom->createTextNode($folder->displayname));
                $add->appendChild($displayName);
                
                $add->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Type', $folder->type));
                
                // store folder in backend
                if (empty($folder->id)) {
                    $this->_folderBackend->create($folder);
                }
            }
            
            foreach($updates as $folder) {
            	$update = $changes->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Update'));
            	$update->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'ServerId', $folder->folderid));
            	$update->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'ParentId', $folder->parentid));
            
            	$displayName = $this->_outputDom->createElementNS('uri:FolderHierarchy', 'DisplayName');
            	$displayName->appendChild($this->_outputDom->createTextNode($folder->displayname));
            	$update->appendChild($displayName);
            
            	$update->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Type', $folder->type));
            
            	$this->_folderBackend->update($folder);
            }
            
            foreach($deletes as $folder) {
                $delete = $changes->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'Delete'));
                $delete->appendChild($this->_outputDom->createElementNS('uri:FolderHierarchy', 'ServerId', $folder->folderid));
                
                $this->_folderBackend->delete($folder);
            }
            
            if (empty($this->_syncState->id)) {
                $this->_syncStateBackend->create($this->_syncState);
            } else {
                $this->_syncStateBackend->update($this->_syncState);
            }
        }
        
        return $this->_outputDom;
    } 

}
