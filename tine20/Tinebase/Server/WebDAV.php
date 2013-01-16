<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Server
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2011-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * 
 */

/**
 * webdav Server class with handle() function
 * 
 * @package     Tinebase
 * @subpackage  Server
 */
class Tinebase_Server_WebDAV implements Tinebase_Server_Interface
{
   /**
    * @var Sabre_DAV_Server
    */
    protected static $_server;
    
    public function handle()
    {
        try {
            Tinebase_Core::initFramework();
        } catch (Zend_Session_Exception $exception) {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN))
                Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' invalid session. Delete session cookie.');
            Zend_Session::expireSessionCookie();
            
            header('WWW-Authenticate: Basic realm="WebDAV for Tine 2.0"');
            header('HTTP/1.1 401 Unauthorized');
            
            return;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO))
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .' is CalDav, CardDAV or WebDAV request.');
        
        if(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['REMOTE_USER']) && empty($_SERVER['REDIRECT_REMOTE_USER'])) {
            header('WWW-Authenticate: Basic realm="WebDav for Tine 2.0"');
            header('HTTP/1.1 401 Unauthorized');
            
            return;
        }
        
        // when used with (f)cgi no PHP_AUTH variables are available without defining a special rewrite rule
        if(!isset($_SERVER['PHP_AUTH_USER'])) {
            // $_SERVER["REMOTE_USER"] == "Basic didhfiefdhfu4fjfjdsa34drsdfterrde..."
            $basicAuthData = base64_decode(substr(isset($_SERVER["REMOTE_USER"]) ? $_SERVER["REMOTE_USER"] : $_SERVER['REDIRECT_REMOTE_USER'], 6));
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(":", $basicAuthData);
        }
        
        if(Tinebase_Controller::getInstance()->login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['REMOTE_ADDR'], 'TineWebDav') !== true) {
            header('WWW-Authenticate: Basic realm="CardDav for Tine 2.0"');
            header('HTTP/1.1 401 Unauthorized');
            return;                            
        }
        
        self::$_server = new Sabre_DAV_Server(new Tinebase_WebDav_Root());
        
        // compute base uri
        $request = new Zend_Controller_Request_Http();
        self::$_server->setBaseUri($request->getBaseUrl() . '/');
        
        $tempDir = Tinebase_Core::getTempDir();
        if (!empty($tempDir)) {
            $lockBackend = new Sabre_DAV_Locks_Backend_File($tempDir . '/webdav.lock');
            $lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
            self::$_server->addPlugin($lockPlugin);
        }
        
        $authPlugin = new Sabre_DAV_Auth_Plugin(new Tinebase_WebDav_Auth(), null);
        self::$_server->addPlugin($authPlugin);
        
        $aclPlugin = new Sabre_DAVACL_Plugin();
        $aclPlugin->defaultUsernamePath = 'principals/users';
        $aclPlugin->principalCollectionSet = array($aclPlugin->defaultUsernamePath/*, 'principals/groups'*/);
        self::$_server->addPlugin($aclPlugin);
        
        self::$_server->addPlugin(new Sabre_CardDAV_Plugin());
        self::$_server->addPlugin(new Sabre_CalDAV_Plugin());
        self::$_server->addPlugin(new Sabre_CalDAV_Schedule_Plugin());
        self::$_server->addPlugin(new Sabre_DAV_Browser_Plugin());
        
        self::$_server->exec();
    }
    
   /**
    * helper to return request
    *
    * @return Sabre_HTTP_Request
    */
    public static function getRequest()
    {
        return self::$_server ? self::$_server->httpRequest : new Sabre_HTTP_Request();
    }
}
