<?php
/**
 * Addressbook Proxy for the backend
 * 
 * @package     Addresbook
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cassiano Dal Pizzol <cassiano.dalpizzol@serpro.gov.br>
 * @copyright   Copyright (c) 2012 SERPRO (http://www.serpro.gov.br)
 */

/**
 * Addresbook Backend proxy backend
 * Intercepts the class to the Adressbook backend and instantiate the correct Backend based on the filter
 *
 * @package     Addressbook
 * @subpackage  Backend
 */
class Addressbook_Backend_AddressbookProxy
{
    private $_lastUserBackend = 'lastUserBakend';
    /**
     * object instance
     *
     * @var Addressbook_Backend_Factory
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
     * @return Addressbook_Backend_AddressbookProxy
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new Addressbook_Backend_AddressbookProxy();
        }
        
        return self::$_instance;
    }
    
    /**
     * Route all calls to the backend to the correct Addresbook Backend and get the correct backend type
     *
     * @param  string $_name
     * @param  array  $_arguments
     * @return  mixed
     */
    public function __call($_name, $_arguments)
    {
        //seting the initial values
        $backendType = Addressbook_Backend_Factory::SQL;
        $arrOptions = array();
        
        $container = $this->_recursiveSearchContainer($_arguments);
        if (!(is_null($container)))
        {
            $backendType = $container->backend;
            $arrOptions = $container->decodeBackendOptions();
            $arrOptions['container'] = $container->id;
            $_SESSION[$this->_lastUserBackend] = array(
                'backendtype' => $backendType,
                'arrOptions'  => $arrOptions,
                );
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Container Found '
                                                                                  . ' Backend Type -  ' . $backendType);
        }
        else
        {
            $bk = $_SESSION[$this->_lastUserBackend];            
            if (!(is_null($bk)))
            {
                switch ($_name)
                {
                    case "get":
                    case "getImage":
                    case "getMultiple":
                    case "getAll":
                        $backendType = $bk['backendtype'];
                        $arrOptions  = $bk['arrOptions'];
                      break;
                }
            }
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No Container Found'
                                                                                          . ' Assuming default values');
        }
        
        Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Calling ' . $_name . ' from the 
                                                                                                       ' .$backendType);
        return call_user_func_array(array(Addressbook_Backend_Factory::factory($backendType, $arrOptions), $_name), 
                                                                                                           $_arguments);
    }
    
    /**
     * Recursive search the filters for the conainer
     * 
     * @param type $_arguments
     * @return Tinebase_Model_Container | NULL
     */
    protected function _recursiveSearchContainer($_filters)
    {
        $return = NULL;
        foreach ($_filters as $filter)
        {
            if (is_object($filter))
            {
                $objClass = get_class($filter);
                switch ($objClass)
                {
                    case 'Addressbook_Model_ContactFilter':
                        $return = $this->_recursiveSearchContainer($filter);
                        break(2);
                    case 'Tinebase_Model_Filter_Container':
                        try
                        {
                            $arrContainers = $filter->getContainerIds();
                            if (count($arrContainers) > 0)
                            {
                                if ($arrContainers[0] != 0)
                                {
                                    $return = Tinebase_Container::getInstance()
                                                                            ->getContainerById((int) $arrContainers[0]);
                                }
                            }
                            else
                            {
                                Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' No Container Found');
                            }
                        }
                        catch (Exception $e)
                        {
                            $message = ' Error Resolving the container' . "\n" . $e->getMessage();
                            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . $message);
                        }
                        break(2);
                }
            }
        }
        return $return;
    }
}