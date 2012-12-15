<?php
/**
*
* This class handles all Json requests for the application
*
* @package     Clients
* @subpackage  Frontend
*/
class Messenger_Frontend_Json extends Tinebase_Frontend_Json_Abstract
{    
    
    const LOG_FILE = '/tmp/messenger/filetransfer.log';

    /**
    * controller
    *
    * @var Controller_Client
    */
   protected $_controller = NULL;

   /**
    * the constructor
    *
    */
   public function __construct()
   {
       $this->_applicationName = 'Messenger';
       $this->_controller = Messenger_Controller::getInstance();
   }
   
   /**
    *  
    */
   public function getLocalServerInfo($login)
   {
       return $this->_controller->getLocalServerInfo($login);
   }
   
   public function removeTempFiles($files)
   {
       return $this->_controller->removeTempFiles($files);
   }
   
   public function saveChatHistory($id, $title, $content)
   {
       return $this->_controller->saveChatHistory($id, $title, $content);
   }
   
   public function logFileTransfer($text)
   {
       //Tinebase_Core::getLogger()->info($text);
       $bytes = file_put_contents(self::LOG_FILE, date('Y-m-d h:i:s') . '   ' . $text . PHP_EOL, FILE_APPEND);
       
       return $bytes !== false ?
           array('log' => $text) :
           array('log' => 'ERROR ON SAVING FILE');
   }
   
}