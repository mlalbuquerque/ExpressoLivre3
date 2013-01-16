<?php
/**
 * Tine 2.0
 * 
 * @package     Sales
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * contract filter Class
 * @package     Sales
 */
class Sales_Model_ContractFilter extends Tinebase_Model_Filter_FilterGroup
{
    /**
     * @var string class name of this filter group
     *      this is needed to overcome the static late binding
     *      limitation in php < 5.3
     */
    protected $_className = 'Sales_Model_ContractFilter';
    
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Sales';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'Sales_Model_Contract';
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'query'                => array('filter' => 'Tinebase_Model_Filter_Query', 'options' => array('fields' => array('description', 'number', 'title'))),
        'container_id'         => array('filter' => 'Tinebase_Model_Filter_Container', 'options' => array('applicationName' => 'Sales')),
    	'created_by'           => array('filter' => 'Tinebase_Model_Filter_User')
    );
}
