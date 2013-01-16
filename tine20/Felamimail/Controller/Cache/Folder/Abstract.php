<?php
/**
 * Tine 2.0
 *
 * @package     Felamimail
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cassiano Dal Pizzol <cassiano.dalpizzol@serpro.gov.br>
 * @copyright   Copyright (c) 2009-2013 Serpro (http://www.serpro.gov.br)
 *
 */

abstract class Felamimail_Controller_Cache_Folder_Abstract extends Felamimail_Controller_Folder_Abstract
{
    /**
     * application name (is needed in checkRight())
     *
     * @var string
     */
    protected $_applicationName = 'Felamimail';
    
    /**
     * folder backend
     *
     * @var Felamimail_Backend_Folder
     */
    protected $_backend = NULL;
    
    /**
     * get folders from imap
     * 
     * @param Felamimail_Model_Account $_account
     * @param string $_folderName
     * @return array
     */
    protected function _getFoldersFromIMAP(Felamimail_Model_Account $_account, $_folderName)
    {
        if (empty($_folderName)) {
            $folders = $this->_getRootFolders($_account);
        } else {
            $folders = $this->_getSubfolders($_account, $_folderName);
        }
        
        return $folders;
    }
    
    /**
     * get root folders and check account capabilities and system folders
     * 
     * @param Felamimail_Model_Account $_account
     * @return array of folders
     */
    protected function _getRootFolders(Felamimail_Model_Account $_account)
    {
        $imap = Felamimail_Backend_ImapFactory::factory($_account);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ 
            . ' Get subfolders of root for account ' . $_account->getId());
        $result = $imap->getFolders('', '%');
        
        return $result;
    }
    
    /**
     * get subfolders
     * 
     * @param $_account
     * @param $_folderName
     * @return array of folders
     */
    protected function _getSubfolders(Felamimail_Model_Account $_account, $_folderName)
    {
        $result = array();
        
        try {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ 
                . ' trying to get subfolders of ' . $_folderName . $this->_delimiter);

            $imap = Felamimail_Backend_ImapFactory::factory($_account);
            $result = $imap->getFolders(Felamimail_Model_Folder::encodeFolderName($_folderName) . $this->_delimiter, '%');
            
            // remove folder if self
            if (in_array($_folderName, array_keys($result))) {
                unset($result[$_folderName]);
            }        
        } catch (Zend_Mail_Storage_Exception $zmse) {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ 
                . ' No subfolders of ' . $_folderName . ' found.');
        }
        
        return $result;
    }
    
    /**
     * update has children flag
     * 
     * @param string|Felamimail_Model_Account $_accountId
     * @param string $_folderName
     * @param boolean $_hasChildren
     */
    protected function _updateHasChildrenCache($_accountId, $_folderName, $_hasChildren)
    {
        if (empty($_folderName)) {
            return;
        }
        
        $parentFolder = Felamimail_Controller_Folder::getInstance()->getByBackendAndGlobalName($_accountId, $_folderName);
        if ($_hasChildren != $parentFolder->has_children) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                . ' Update has_children = ' . $_hasChildren . ' for folder ' . $parentFolder->globalname);
            $parentFolder->has_children = $_hasChildren;
            $this->_backend->update($parentFolder);
        }
    }
    
    /**
     * do recursive update
     * 
     * @param Felamimail_Model_Account $_account
     * @param Tinebase_Record_RecordSet $_folderResult
     */
    protected function _updateRecursive(Felamimail_Model_Account $_account, Tinebase_Record_RecordSet $_folderResult)
    {
        foreach ($_folderResult as $folder) {
            if ($folder->has_children) {
                $this->updateCacheFolder($_account, $folder->globalname, TRUE);
            } else {
                $this->_removeFromCache($_account, $folder->globalname);
            }
            $this->_backend->update($folder);
        }
    }
    
    /**
     * create new folders or get existing folders from db and return record set
     *
     * @param array $_folders
     * @param Felamimail_Model_Account $_account
     * @param string $_parentFolder
     * @return Tinebase_Record_RecordSet of Felamimail_Model_Folder
     * 
     * @todo    move delete sync to extra function
     */
    protected function _getOrCreateFolders(array $_folders, $_account, $_parentFolder)
    {
        $parentFolder = ($_parentFolder !== NULL) ? $_parentFolder : '';
        $result = new Tinebase_Record_RecordSet('Felamimail_Model_Folder');
        $systemFolders = Felamimail_Controller_Folder::getInstance()->getSystemFolders($_account);
        
        // get configured account standard folders here
        if (strtolower($_account->sent_folder) != $systemFolders[2]) {
            $systemFolders[2] = strtolower($_account->sent_folder);
        }
        if (strtolower($_account->trash_folder) != $systemFolders[5]) {
            $systemFolders[5] = strtolower($_account->trash_folder);
        }
        
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($systemFolders, TRUE));
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_folders, TRUE));
        
        // do some mapping and save folder in db (if it doesn't exist
        foreach ($_folders as $folderData) {
            try {
                $folderData['localName'] = Felamimail_Model_Folder::decodeFolderName($folderData['localName']);
                $folderData['globalName'] = Felamimail_Model_Folder::decodeFolderName($folderData['globalName']);
                $isSelectable = $this->_isSelectable($folderData, $_account);
                
                $folder = Felamimail_Controller_Folder::getInstance()->getByBackendAndGlobalName($_account->getId(), $folderData['globalName']);
                
                $folder->is_selectable = $isSelectable;
                $folder->imap_status   = Felamimail_Model_Folder::IMAP_STATUS_OK;
                $folder->has_children  = ($folderData['hasChildren'] == '1');
                
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Update cached folder ' . $folderData['globalName']);
                
            } catch (Tinebase_Exception_NotFound $tenf) {
                // create new folder
                if (empty($folderData['localName'])) {
                    // skip
                    if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Do not add folder ' . $folderData['globalName'] 
                        . '. Localname is empty.');
                    continue;
                    
                } else {
                    $delimiter = (strlen($folderData['delimiter']) === 1) ? $folderData['delimiter'] : '';
                    
                    $folder = new Felamimail_Model_Folder(array(
                        'localname'         => $folderData['localName'],
                        'globalname'        => $folderData['globalName'],
                        'is_selectable'     => $isSelectable,
                        'has_children'      => ($folderData['hasChildren'] == '1'),
                        'account_id'        => $_account->getId(),
                        'imap_timestamp'    => Tinebase_DateTime::now(),
                        'imap_status'       => Felamimail_Model_Folder::IMAP_STATUS_OK,
                        'user_id'           => $this->_currentAccount->getId(),
                        'parent'            => $parentFolder,
                        'system_folder'     => in_array(strtolower($folderData['localName']), $systemFolders),
                        'delimiter'         => $delimiter,
                    ));
                    
                    // update delimiter setting of account
                    if ($folder->delimiter && $folder->delimiter !== $_account->delimiter && $folder->localname === 'INBOX') {
                        $_account->delimiter = $folder->delimiter;
                        $_account = Felamimail_Controller_Account::getInstance()->update($_account);
                    }
                    
                    if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Adding new folder ' . $folderData['globalName'] . ' to cache.');
                    if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . print_r($folder->toArray(), true));
                    
                    $folder = $this->_backend->create($folder);
                }
            }
            
            $result->addRecord($folder);
        }
        
        if (count($_folders) > 0) {
            $this->_removeFromCache($_account, $parentFolder, $result->getArrayOfIds());
        }
        
        return $result;
    }
    
    /**
     * check if folder is selectable: try to select folder on imap server if isSelectable is false/not set
     * - courier imap servers subfolder have isSelectable = 0 but they still can be selected 
     *   @see http://www.tine20.org/bugtracker/view.php?id=2736
     * 
     * @param array $_folderData
     * @param Felamimail_Model_Account $_account
     * @return boolean
     */
    protected function _isSelectable($_folderData, $_account)
    {
        $result = TRUE;
        
        if (! $_folderData['isSelectable'] == '1') {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Folder ' . $_folderData['globalName'] . ' is not selectable.');
            $imap = Felamimail_Backend_ImapFactory::factory($_account);
            try {
                $folderData = $imap->selectFolder(Felamimail_Model_Folder::encodeFolderName($_folderData['globalName']));
            } catch (Zend_Mail_Storage_Exception $zmse) {
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Could not select folder. Skipping it.');
                $result = FALSE;
            }
        }
        
        return $result;
    }
    
    /**
     * remove folders from cache that no longer exist on the imap server
     * 
     * @param Felamimail_Model_Account $_account
     * @param string $_parentFolder
     * @param array $_imapFolderIds if empty, remove all found cached folders
     */
    protected function _removeFromCache(Felamimail_Model_Account $_account, $_parentFolder = NULL, $_imapFolderIds = array())
    {
        $filterData = array(array('field' => 'account_id',  'operator' => 'equals', 'value' => $_account->getId()));
        if ($_parentFolder !== NULL) {
            $filterData[] = array('field' => 'parent',      'operator' => 'equals', 'value' => $_parentFolder);
        } 
        $filter = new Felamimail_Model_FolderFilter($filterData);
        $cachedFolderIds = $this->_backend->search($filter, NULL, TRUE);
        if (count($cachedFolderIds) > count($_imapFolderIds)) {
            // remove folders from cache
            $idsToRemove = array_diff($cachedFolderIds, $_imapFolderIds);
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Removing ' . count($idsToRemove) . ' folders from cache.');
            $this->deleteCacheFolder($idsToRemove);
        }
    }
}