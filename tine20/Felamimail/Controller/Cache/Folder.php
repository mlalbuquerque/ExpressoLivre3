<?php
/**
 * Tine 2.0
 *
 * @package     Felamimail
 * @subpackage  Controller
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @todo        this should extend Felamimail_Controller_Folder (like Felamimail_Controller_Cache_Message)
 * @todo        add cleanup routine for deleted (by other clients)/outofdate folders?
 */

/**
 * cache controller for Felamimail folders
 *
 * @package     Felamimail
 * @subpackage  Controller
 */
class Felamimail_Controller_Cache_Folder
{
    /**
     * holds the instance of the singleton
     *
     * @var Felamimail_Controller_Cache_Folder
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton
     */
    private function __construct()
    {
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {        
    }
    
    /**
     * the singleton pattern
     *
     * @return Felamimail_Controller_Cache_Folder
     */
    public static function getInstance() 
    {   
        if (self::$_instance === NULL)
        {
            $adapter = Tinebase_Core::getConfig()->messagecache;
            $adapter = (empty($adapter))?'sql':$adapter;
            $classname = 'Felamimail_Controller_Cache_' . ucfirst($adapter) . '_Folder';
            self::$_instance = $classname::getInstance();
        }
        
        return self::$_instance;
    }    
}
