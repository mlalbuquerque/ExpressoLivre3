<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Record
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * defines the datatype for one container
 * 
 * @package     Tinebase
 * @subpackage  Record
 * @property    string application_id
 * @property    string type
 * @property    sting  owner_id
 * 
 * NOTE: container class is in the transition from int based grants to string based
 *       grants! In the next refactoring step of container class, int based grants 
 *       will be replaced. Also the grants will not longer be part of container class!
 *       This way apps can define their own grants
 */
class Tinebase_Model_Container extends Tinebase_Record_Abstract
{
    /**
     * type for personal containers
     */
    const TYPE_PERSONAL = 'personal';
    
    /**
     * type for shared container
     */
    const TYPE_SHARED = 'shared';
    
    /**
     * type for shared container
     */
    const TYPE_OTHERUSERS = 'otherUsers';
    
    /**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */    
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * list of zend inputfilter
     * 
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_filters = array(
        'name'              => 'StringTrim'
    );
       
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators =  array(
        'id'                => array('Digits', 'allowEmpty' => true),
        'name'              => array('presence' => 'required'),
        'type'              => array(array('InArray', array(self::TYPE_PERSONAL, self::TYPE_SHARED))),
        'backend'           => array('presence' => 'required'),
        'color'             => array('allowEmpty' => true, array('regex', '/^#[0-9a-fA-F]{6}$/')),
        'application_id'    => array('Alnum', 'presence' => 'required'),
        'account_grants'    => array('allowEmpty' => true), // non persistent
        'owner_id'          => array('allowEmpty' => true), // non persistent + only set for personal folders
        'path'              => array('allowEmpty' => true), // non persistent
        'backend_options'   => array('allowEmpty' => true),
        
    // only gets updated in increaseContentSequence() + readonly in normal record context
        'content_seq'       => array('allowEmpty' => true),
    
    // modlog fields
        'created_by'             => array('allowEmpty' => true),
        'creation_time'          => array('allowEmpty' => true),
        'last_modified_by'       => array('allowEmpty' => true),
        'last_modified_time'     => array('allowEmpty' => true),
        'is_deleted'             => array('allowEmpty' => true),
        'deleted_time'           => array('allowEmpty' => true),
        'deleted_by'             => array('allowEmpty' => true),
    );
    
    /**
     * datetime fields
     *
     * @var array
     */
    protected $_datetimeFields = array(
        'creation_time',
        'last_modified_time',
        'deleted_time',
    );    
    
    /**
    * name of fields that should not be persisted during create/update in backend
    *
    * @var array
    */
    protected $_readOnlyFields = array('content_seq');
    
    /**
     * Name of the fields stored in the backend_options
     * @var type 
     */
    protected static $_mapBackendOptions = array(
        'host'                      => array('ldapHost', null),
        'port'                      => array('ldapPort', null),
        'useSsl'                    => array(null, null),
        'username'                  => array('ldapAccount', null),
        'password'                  => array('ldapPassword', null),
        'bindRequiresDn'            => array(null, null),
        'baseDn'                    => array('ldapDn', null),
        'accountCanonicalForm'      => array(null, null),
        'accountDomainName'         => array(null, null),
        'accountDomainNameShort'    => array(null, null),
        'accountFilterFormat'       => array(null, null),
        'allowEmptyPassword'        => array(null, null),
        'useStartTls'               => array(null, null),
        'optReferrals'              => array(null, null),
        'tryUsernameSplit'          => array(null, null),
        'ldapQuickSearch'           => array(null, false),
        'filter'                    => array('ldapObjectClass', null),
        'maxResults'                => array('ldapMaxResults', null),
        'scope'                     => array('ldapRecursive', false),
        'attributes'                => array(null, null),
    );
    
    
    /**
     * converts a int, string or Tinebase_Model_Container to a containerid
     *
     * @param   int|string|Tinebase_Model_Container $_containerId the containerid to convert
     * @return  int
     * @throws  Tinebase_Exception_InvalidArgument
     */
    static public function convertContainerIdToInt($_containerId)
    {
        if($_containerId instanceof Tinebase_Model_Container) {
            if($_containerId->getId() === NULL) {
                throw new Tinebase_Exception_InvalidArgument('No container id set.');
            }
            $id = (int) $_containerId->getId();
        } else {
            $id = (int) $_containerId;
        }
        
        if ($id === 0) {
            throw new Tinebase_Exception_InvalidArgument('Container id can not be 0 (' . $_containerId . ').');
        }
        
        return $id;
    }
    
    /**
     * (non-PHPdoc)
     * @see Tinebase/Record/Tinebase_Record_Abstract#setFromArray($_data)
     */
    public function setFromArray(array $_data)
    {
        parent::setFromArray($_data);
        
        switch ($this->type) {
            case Tinebase_Model_Container::TYPE_SHARED:
                $this->path = "/{$this->type}/{$this->getId()}";
                break;
                
            case Tinebase_Model_Container::TYPE_PERSONAL:
                if (!empty($this->owner_id)) {
                    $this->path = "/{$this->type}/{$this->owner_id}/{$this->getId()}";
                }
                break;
        }
    }
    
    /**
     * gets path of this container
     *
     * @return string path
     */
    public function getPath()
    {
        switch ($this->type) {
            case Tinebase_Model_Container::TYPE_PERSONAL:
                $this->path = "/{$this->type}/{$this->getOwner()}/{$this->getId()}";
                break;
        }
        
        return $this->path;
    }
    
    /**
     * returns owner of this container
     * 
     * @throws Exception
     */
    public function getOwner()
    {
        if ($this->type == self::TYPE_SHARED) return NULL;
        
        if (! $this->owner_id) {
            // we need to find out who has admin grant
            $allGrants = Tinebase_Container::getInstance()->getGrantsOfContainer($this, true);
            
            // pick the first user with admin grants
            foreach ($allGrants as $grants) {
                if ($grants->{Tinebase_Model_Grants::GRANT_ADMIN} === true) {
                    $this->owner_id = $grants->account_id;
                    break;
                }
            }
            if (! $this->owner_id) {
                throw new Exception('could not find container admin');
            }
        }
        
        return $this->owner_id;
    }
    
    /**
     * checks if container is a personal container of given account
     * 
     * @param mixed $account
     */
    public function isPersonalOf($account)
    {
        return $this->type == Tinebase_Model_Container::TYPE_PERSONAL 
            && $this->getOwner() == Tinebase_Model_User::convertUserIdToInt($account);
    }
    
    /**
     * returns containerId if given path represents a (single) container
     * 
     * @static
     * @param  String path
     * @return String|Bool
     */
    public static function pathIsContainer($_path)
    {
        if (preg_match("/^\/personal\/[0-9a-z_\-]+\/([a-f0-9]+)|^\/shared\/([a-f0-9]+)/i", $_path, $matches)) {
            return array_key_exists(2, $matches) ? $matches[2] : $matches[1];
        }
        
        return false;
    }

    /**
     * resolves container_id property
     * 
     * @param Tinebase_Record_Abstract $_record
     * @param string $_containerProperty
     */
    public static function resolveContainerOfRecord($_record, $_containerProperty = 'container_id')
    {
        if (! $_record instanceof Tinebase_Record_Abstract) {
            return;
        }
        
        if (! $_record->has($_containerProperty) || empty($_record->{$_containerProperty})) {
            return;
        }
        
        try {
            $container = Tinebase_Container::getInstance()->getContainerById($_record->{$_containerProperty});
        } catch (Tinebase_Exception_NotFound $tenf) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $tenf);
            return;
        }
        
        $container->resolveGrantsAndPath();
        
        $_record->{$_containerProperty} = $container;
    }
    
    /**
     * resolves container grants and path
     */
    public function resolveGrantsAndPath()
    {
        $this->account_grants = Tinebase_Container::getInstance()->getGrantsOfAccount(Tinebase_Core::getUser(), $this);
        $this->path = $this->getPath();
    }
    
    /**
     * returns owner id if given path represents a personal _node_
     * 
     * @static
     * @param  String $_path
     * @return String|Bool
     */
    public static function pathIsPersonalNode($_path)
    {
        if (preg_match("/^\/personal\/([0-9a-z_\-]+)$/i", $_path, $matches)) {
            // transform current user 
            return $matches[1] == Tinebase_Model_User::CURRENTACCOUNT ? Tinebase_Core::getUser()->getId() : $matches[1];
        }
        
        return false;
    }
    
    /**
     * returns containername
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
    
    /**
     * Decode and return the array of backendOptions
     * @param array $_backendOptions 
     * @return array
     */
    public static function decodeBackendOptions($_backendOptions)
    {
        return Zend_Json::decode($_backendOptions);
    }
    
    /**
     *Encode and set the backend_options value
     * 
     * @param array $_backendOptions 
     * @return string
     */
    public static function encodeBackendOptions($_backendOptions)
    {   
        return Zend_Json::encode($_backendOptions);
    }
    
    /**
     * Resolve the Backend Options to save and retrive
     * 
     * @param type $_backendOptions
     * @param bool $_reverse false to save, true to retrieve
     * @return type Tinebase_Record_Interface
     */
    public static function resolveBackendOptions($_backendOptions, $_reverse = FALSE)
    {
        $recordData = $_backendOptions;
        foreach (self::$_mapBackendOptions as $key=>$value)
        {
            if ($_reverse)
            {
                $recordData[$value[0]] = $recordData['backend_options'][$key];
            }
            else
            {
                $recordData['backend_options'][$key] = (!(is_null($_backendOptions[$value[0]])))?
                                                                                  $_backendOptions[$value[0]]:$value[1];
            }
        }
        return $recordData;
    }
}
