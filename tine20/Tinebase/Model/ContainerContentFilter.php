<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Filter
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * container content filter class
 * 
 * @package     Tinebase
 * @subpackage  Filter 
 */
class Tinebase_Model_ContainerContentFilter extends Tinebase_Model_Filter_FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'Tinebase_Model_ContainerContent';
    
    /**
     * @var string class name of this filter group
     *      this is needed to overcome the static late binding
     *      limitation in php < 5.3
     */
    protected $_className = 'Tinebase_Model_ContainerContentFilter';
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'record_id'         => array('filter' => 'Tinebase_Model_Filter_Id'),
        'content_seq'       => array('filter' => 'Tinebase_Model_Filter_Int'),
        'container_id'      => array('filter' => 'Tinebase_Model_Filter_Id'),
        'action'            => array('filter' => 'Tinebase_Model_Filter_Text'),
        'time'              => array('filter' => 'Tinebase_Model_Filter_DateTime'),
    );
}
