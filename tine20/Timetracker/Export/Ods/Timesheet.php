<?php
/**
 * Timesheet Ods generation class
 *
 * @package     Timetracker
 * @subpackage	Export
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * Timetracker Ods generation class
 * 
 * @package     Timetracker
 * @subpackage	Export
 * 
 */
class Timetracker_Export_Ods_Timesheet extends Tinebase_Export_Spreadsheet_Ods
{
    /**
     * @var string application of this export class
     */
    protected $_applicationName = 'Timetracker';
    
    /**
     * sort records by this field / dir
     *
     * @var array
     */
    protected $_sortInfo = array(
        'sort'  => 'start_date',
        'dir'   => 'ASC'
    );
    
    /**
     * fields with special treatment in addBody
     *
     * @var array
     */
    protected $_specialFields = array('timeaccount');
    
    /**
     * the constructor
     *
     * @param Tinebase_Model_Filter_FilterGroup $_filter
     * @param Tinebase_Controller_Record_Interface $_controller (optional)
     * @param array $_additionalOptions (optional) additional options
     */
    public function __construct(Tinebase_Model_Filter_FilterGroup $_filter, Tinebase_Controller_Record_Interface $_controller = NULL, $_additionalOptions = array())
    {
        $this->_prefKey = Timetracker_Preference::TSODSEXPORTCONFIG;
        $this->_defaultExportname = 'ts_default_ods';
        
        parent::__construct($_filter, $_controller, $_additionalOptions);
    }
    
    /**
     * resolve records
     *
     * @param Tinebase_Record_RecordSet $_records
     */
    protected function _resolveRecords(Tinebase_Record_RecordSet $_records)
    {
        parent::_resolveRecords($_records);
        
        $timeaccountIds = $_records->timeaccount_id;
        $this->_resolvedRecords['timeaccounts'] = Timetracker_Controller_Timeaccount::getInstance()->getMultiple(array_unique(array_values($timeaccountIds)));
    }

    /**
     * get special field value
     *
     * @param Tinebase_Record_Interface $_record
     * @param array $_param
     * @param string $_key
     * @param string $_cellType
     * @return string
     */
    protected function _getSpecialFieldValue(Tinebase_Record_Interface $_record, $_param, $_key = NULL, &$_cellType = NULL)
    {
    	if (is_null($_key)) {
    		throw new Tinebase_Exception_InvalidArgument('Missing required parameter $key');
    	}
    	
        $value = '';
        
        switch($_param['type']) {
            case 'timeaccount':
                $value = $this->_resolvedRecords['timeaccounts'][$this->_resolvedRecords['timeaccounts']->getIndexById($_record->timeaccount_id)]->$_param['field'];
                break;
        }
        return $value;
    }
    
    /**
     * get name of data table
     * 
     * @return string
     */
    protected function _getDataTableName()
    {
        return 'Timesheets';
    }
}
