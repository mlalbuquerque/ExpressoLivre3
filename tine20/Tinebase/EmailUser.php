<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  User
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * 
 * @todo        think about splitting email user model in two (imap + smtp)
 */

/**
 * class Tinebase_EmailUser
 * 
 * Email Account Managing
 * 
 * @package Tinebase
 * @subpackage User
 */
class Tinebase_EmailUser
{
    /**
     * dbmail backend const
     * 
     * @staticvar string
     */
    const DBMAIL    = 'Dbmail';

	/**
     * Dovecot imap backend const
     * 
     * @staticvar string
     */
    const DOVECOT_IMAP    = 'Dovecot_imap';
    
    /**
     * postfix backend const
     * 
     * @staticvar string
     */
    const POSTFIX    = 'Postfix';

    /**
     * imap ldap backend const
     * 
     * @staticvar string
     */
    const LDAP_IMAP      = 'Ldap_imap';

    /**
     * smtp ldap backend const
     * 
     * @staticvar string
     */
    const LDAP_SMTP      = 'Ldapsmtp';
    
    /**
     * smtp ldap backend const
     * 
     * @staticvar string
     */
    const LDAP_SMTP_QMAIL      = 'Ldapsmtpqmail';

    /**
     * cyrus backend const
     * 
     * @staticvar string
     */
    const CYRUS    = 'Cyrus';
    
    /**
     * backend object instances
     * 
     * @var array
     */
    private static $_backends = array();
    
    /**
     * configs as static class var to minimize db queries
     *  
     * @var array
     */
    private static $_configs = array();
    
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
     * @param string $_configType
     * @return Tinebase_User_Plugin_Abstract
     */
    public static function getInstance($_configType = Tinebase_Config::IMAP) 
    {
        $backendType = self::getConfiguredBackend($_configType);
        #if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' Email user backend: ' . $backendType);
        
        return self::factory($backendType);
    }
    
    /**
     * return an instance of the current backend
     *
     * @param   string $_type name of the backend
     * @return  Tinebase_User_Plugin_Abstract
     * @throws  Tinebase_Exception_InvalidArgument
     */
    public static function factory($_type = NULL) 
    {
        switch($_type) {
            case self::LDAP_IMAP:
                if (!isset(self::$_backends[$_type])) {
                    self::$_backends[$_type] = new Tinebase_EmailUser_Imap_LdapDbmailSchema();
                }
                break;
                
            case self::DBMAIL:
                if (!isset(self::$_backends[$_type])) {
                    self::$_backends[$_type] = new Tinebase_EmailUser_Imap_Dbmail();
                }
                break;
            
            case self::CYRUS:
                if (!isset(self::$_backends[$_type])) {
                    self::$_backends[$_type] = new Tinebase_EmailUser_Imap_Cyrus();
                }
                break;
                
            case self::POSTFIX:
                if (!isset(self::$_backends[$_type])) {
                    self::$_backends[$_type] = new Tinebase_EmailUser_Smtp_Postfix();
                }
                break;
                
            case self::LDAP_SMTP:
                if (!isset(self::$_backends[$_type])) {
                    self::$_backends[$_type] = new Tinebase_EmailUser_Smtp_LdapDbmailSchema();
                }
                break;
                
            case self::LDAP_SMTP_QMAIL:
                if (!isset(self::$_backends[$_type])) {
                    self::$_backends[$_type] = new Tinebase_EmailUser_Smtp_LdapQmailSchema();
                }
                break;
			
            case self::DOVECOT_IMAP:
                if (!isset(self::$_backends[$_type])) {
                    self::$_backends[$_type] = new Tinebase_EmailUser_Imap_Dovecot();
                }
                break;
                
            default:
                throw new Tinebase_Exception_InvalidArgument("Backend type $_type not implemented.");
        }
        
        $result = self::$_backends[$_type];
        
        return $result;
    }
    
    /**
     * returns the configured backend
     * 
     * @param string $_configType
     * @return string
     * @throws Tinebase_Exception_NotFound
     */
    public static function getConfiguredBackend($_configType = Tinebase_Config::IMAP)
    {
        $result = '';        
        
        $config = self::getConfig($_configType);
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($config, TRUE));
        
        if (isset($config['backend'])) {
            $backend = ucfirst(strtolower($config['backend']));
            switch ($_configType) {
                case Tinebase_Config::IMAP:
                    if ($backend == self::DBMAIL) {
                        $result = self::DBMAIL;
                    } else if ($backend == self::LDAP_IMAP) {
                        $result = self::LDAP_IMAP;
                    } else if ($backend == self::CYRUS) {
                        $result = self::CYRUS;
                    } else if ($backend == self::DOVECOT_IMAP) {
                        $result = self::DOVECOT_IMAP;
                    } 
                    break;
                case Tinebase_Config::SMTP:
                    if ($backend == self::POSTFIX) {
                        $result = self::POSTFIX;
                    } else if ($backend == self::LDAP_SMTP) {
                        $result = self::LDAP_SMTP;
                    } else if ($backend == self::LDAP_SMTP_QMAIL) {
                        $result = self::LDAP_SMTP_QMAIL;
                    }
                    break;
            }
        }

        if (empty($result)) {
            throw new Tinebase_Exception_NotFound("Config for type $_configType / $backend not found.");
        }
        
        return $result;
    }
    
    /**
     * merge two email users
     * 
     * @param Tinebase_Model_EmailUser $_emailUserImap
     * @param Tinebase_Model_EmailUser $_emailUserSmtp
     * @return Tinebase_Model_EmailUser|NULL
     */
    public static function merge($_emailUserImap, $_emailUserSmtp)
    {
        $result = NULL;
        
        if ($_emailUserImap !== NULL && $_emailUserSmtp !== NULL) {
            // merge
            $_emailUserImap->emailAliases = $_emailUserSmtp->emailAliases;
            $_emailUserImap->emailForwards = $_emailUserSmtp->emailForwards;
            $_emailUserImap->emailForwardOnly = $_emailUserSmtp->emailForwardOnly;
            $_emailUserImap->emailAddress = $_emailUserSmtp->emailAddress;
            $result = $_emailUserImap;
            
        } else if ($_emailUserImap !== NULL) {
            $result =  $_emailUserImap;
            
        } else if ($_emailUserSmtp !== NULL) {
            $result =  $_emailUserSmtp;
        }
        
        return $result;
    }
    
    /**
     * check if email users are managed for backend/config type
     * 
     * @param string $_configType IMAP/SMTP
     * @return boolean
     */
    public static function manages($_configType)
    {
        $config = self::getConfig($_configType);
        
        $result = (isset($config['backend']) && ! empty($config['backend']) && $config['backend'] != 'standard' && isset($config['active']) && $config['active'] == true);
        
        return $result;
    }
    
    /**
     * get config for type IMAP/SMTP
     * 
     * @param string $_configType
     * @return array
     */
    public static function getConfig($_configType)
    {
        if (!isset(self::$_configs[$_configType])) {
            self::$_configs[$_configType] = Tinebase_Config::getInstance()->getConfigAsArray($_configType);
        }
        
        return self::$_configs[$_configType];
    }
}
