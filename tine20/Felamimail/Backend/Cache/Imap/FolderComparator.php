<?php

/**
 * Tine 2.0
 *
 * @package     Felamimail
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cassiano Dal Pizzol <cassiano.dalpizzol@serpro.gov.br>
 * @author      Bruno Costa Vieira <bruno.vieira-costa@serpro.gov.br>
 * @author      Mario Cesar Kolling <mario.kolling@serpro.gov.br>
 * @copyright   Copyright (c) 2009-2013 Serpro (http://www.serpro.gov.br)
 *
 * @todo create an Account Map or reuse some Zend object
 * @todo organize the accountMap Code, put into a singleton class to use it globally????
 */

final class Felamimail_Backend_Cache_Imap_FolderComparator
{
    
    protected $_systemFolders = array('inbox', 'drafts', 'sent', 'templates', 'junk', 'trash');
    
    public function __construct(Felamimail_Model_Account $_account = null) {
        
        $this->_folderDelimiter = $_account->delimiter;
        $this->_personalNamespace = rtrim($_account->ns_personal, $this->_folderDelimiter);
        $this->_sharedNamespace = rtrim(empty($_account->ns_shared) ?
                $_account->ns_other : $_account->ns_shared, $this->_folderDelimiter);

    }
    
    public function compare($_folder1, $_folder2)
    {
        $folder1 = array_pop(explode($this->_folderDelimiter, $_folder1));
        $folder2 = array_pop(explode($this->_folderDelimiter, $_folder2));
        
        if (in_array(strtolower($folder1), $this->_systemFolders) && !in_array(strtolower($folder2), $this->_systemFolders))
        {
            return -1;
        }
        else if (!in_array(strtolower($folder1), $this->_systemFolders) && in_array(strtolower($folder2), $this->_systemFolders))
        {
            return 1;
        }
        
        $translate = Tinebase_Translation::getTranslation('Felamimail');
        
        return strcasecmp($translate->_($folder1), $translate->_($folder2));
    }
}    
