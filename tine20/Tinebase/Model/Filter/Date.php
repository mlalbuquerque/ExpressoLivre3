<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Filter
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * 
 * @todo        add year to 'inweek' filter?
 */

/**
 * Tinebase_Model_Filter_Date
 * 
 * @package     Tinebase
 * @subpackage  Filter
 * 
 * filters date in one property
 */
class Tinebase_Model_Filter_Date extends Tinebase_Model_Filter_Abstract implements Tinebase_Model_Filter_Imap_Interface
{
    /**
     * @var array list of allowed operators
     */
    protected $_operators = array(
        0 => 'equals',
        1 => 'within',
        2 => 'before',
        3 => 'after',
        4 => 'isnull',
        5 => 'notnull',
        6 => 'inweek'
    );
    
    /**
     * @var array maps abstract operators to sql operators
     */
    protected $_opSqlMap = array(
        'equals'     => array('sqlop' => ' = ?'),
        'within'     => array('sqlop' => array(' >= ? ', ' <= ?')),
        'before'     => array('sqlop' => ' < ?'),
        'after'      => array('sqlop' => ' > ?'),
        'isnull'     => array('sqlop' => ' IS NULL'),
        'notnull'    => array('sqlop' => ' IS NOT NULL'),
        'inweek'     => array('sqlop' => array(' >= ? ', ' <= ?')),
    );
    
    /**
     * date format string
     *
     * @var string
     */
    protected $_dateFormat = 'Y-m-d';
    
    /**
     * appends sql to given select statement
     *
     * @param Zend_Db_Select                $_select
     * @param Tinebase_Backend_Sql_Abstract $_backend
     */
     public function appendFilterSql($_select, $_backend)
    {
        // prepare value
        $value = (array)$this->_getDateValues($this->_operator, $this->_value);
        
        // quote field identifier
        $field = $this->_getQuotedFieldName($_backend);
        
        // db
        $this->_db = Tinebase_Core::getDb();
         
        // append query to select object
        foreach ((array)$this->_opSqlMap[$this->_operator]['sqlop'] as $num => $operator) {
            if (array_key_exists($num, $value)) {
                if (get_parent_class($this) === 'Tinebase_Model_Filter_Date' || in_array($this->_operator, array('isnull', 'notnull'))) {
                    $_select->where($field . $operator, $value[$num]);
                } else {
                    $value = Tinebase_Backend_Sql_Command::setDateValue($this->_db, $value[$num]);
                    $_select->where( Tinebase_Backend_Sql_Command::setDate($this->_db, $field). $operator, $value);
                }
            } else {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No filter value found, skipping operator: ' . $operator);
            }
        }
    }
    
    /**
     *
     * @return type
     */
    public function getFilterImap()
    {
        $format = "d-M-Y";
        
        
        // prepare value
        $value = (array) $this->_getDateValues($this->_operator, $this->_value);
        $timezone = array_value('timezone', $this->_options);
        $timezone = $timezone ? $timezone : Tinebase_Core::get('userTimeZone');
        foreach ($value as &$date)
        {
            $date = new Tinebase_DateTime($date); // should be in user timezone
            $date->setTimezone(new DateTimeZone($timezone));
        }
                
        switch ($this->_operator)
        {
            case 'within' :
            case 'inweek' :
                $value[1]->add(new DateInterval('P1D')); // before is not inclusive, so we have to add a day
                $return = "SINCE {$value[0]->format($format)} BEFORE {$value[1]->format($format)}";
                break;
            case 'before' :
                $return = "BEFORE {$value[0]->format($format)}";
                break;
            case 'after' :
                $return = "SINCE {$value[0]->format($format)}";
                break;
            case 'equals' :
                $return = "ON {$value[0]->format($format)}";
        }
        
        return $return;
    }
    
    /**
     * calculates the date filter values
     *
     * @param string $_operator
     * @param string $_value
     * @return array|string date value
     */
    protected function _getDateValues($_operator, $_value)
    {
        if ($_operator === 'within') {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Setting "within" filter: ' . $_value);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Timezone: ' . date_default_timezone_get());
            
            $date = new Tinebase_DateTime();
            
            // special values like this week, ...
            switch($_value) {
                /******* week *********/
                case 'weekNext':
                    $date->add(21, Tinebase_DateTime::MODIFIER_DAY);
                case 'weekBeforeLast':    
                    $date->sub(7, Tinebase_DateTime::MODIFIER_DAY);
                case 'weekLast':    
                    $date->sub(7, Tinebase_DateTime::MODIFIER_DAY);
                case 'weekThis':
                    $value = $this->_getFirstAndLastDayOfWeek($date);
                    break;
                /******* month *********/
                case 'monthNext':
                    $date->add(2, Tinebase_DateTime::MODIFIER_MONTH);
                case 'monthLast':
                    $month = $date->get('m');
                    if ($month > 1) {
                        $date = $date->setDate($date->get('Y'), $month - 1, 1);
                    } else {
                        $date->subMonth(1);
                    }
                case 'monthThis':
                    $dayOfMonth = $date->get('j');
                    $monthDays = $date->get('t');
                    
                    $first = $date->toString('Y-m') . '-01';
                    $date->add($monthDays-$dayOfMonth, Tinebase_DateTime::MODIFIER_DAY);
                    $last = $date->toString($this->_dateFormat);
    
                    $value = array(
                        $first, 
                        $last,
                    );
                    break;
                /******* year *********/
                case 'yearNext':
                    $date->add(2, Tinebase_DateTime::MODIFIER_YEAR);
                case 'yearLast':
                    $date->sub(1, Tinebase_DateTime::MODIFIER_YEAR);
                case 'yearThis':
                    $value = array(
                        $date->toString('Y') . '-01-01', 
                        $date->toString('Y') . '-12-31',
                    );                
                    break;
                /******* quarter *********/
                case 'quarterNext':
                    $date->add(6, Tinebase_DateTime::MODIFIER_MONTH);
                case 'quarterLast':
                    $date->sub(3, Tinebase_DateTime::MODIFIER_MONTH);
                case 'quarterThis':
                    $month = $date->get('m');
                    if ($month < 4) {
                        $first = $date->toString('Y' . '-01-01');
                        $last = $date->toString('Y' . '-03-31');
                    } elseif ($month < 7) {
                        $first = $date->toString('Y' . '-04-01');
                        $last = $date->toString('Y' . '-06-30');
                    } elseif ($month < 10) {
                        $first = $date->toString('Y' . '-07-01');
                        $last = $date->toString('Y' . '-09-30');
                    } else {
                        $first = $date->toString('Y' . '-10-01');
                        $last = $date->toString('Y' . '-12-31');
                    }
                    $value = array(
                        $first, 
                        $last
                    );                
                    break;
                /******* day *********/
                case 'dayNext':
                    $date->add(2, Tinebase_DateTime::MODIFIER_DAY);
                case 'dayLast':
                    $date->sub(1, Tinebase_DateTime::MODIFIER_DAY);
                case 'dayThis':
                    $value = array(
                        $date->toString($this->_dateFormat), 
                        $date->toString($this->_dateFormat), 
                    );
                    
                    break;
                /******* error *********/
                default:
                    Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' value unknown: ' . $_value);
                    $value = '';
            }        
        } elseif ($_operator === 'inweek') {
            $date = new Tinebase_DateTime();
            
            if ($_value > 52) {
                $_value = 52;
            } elseif ($_value < 1) {
                $_value = $date->get('W');
            }
            $value = $this->_getFirstAndLastDayOfWeek($date, $_value);
            
        } else  {
            $value = substr($_value, 0, 10);
        }
        
        return $value;
    }
    
    /**
     * get string representation of first and last days of the week defined by date/week number
     * 
     * @param Tinebase_DateTime $_date
     * @param integer $_weekNumber optional
     * @return array
     */
    protected function _getFirstAndLastDayOfWeek(Tinebase_DateTime $_date, $_weekNumber = NULL)
    {
        $firstDayOfWeek = $this->_getFirstDayOfWeek();
        
        if ($_weekNumber !== NULL) {
            $_date->setWeek($_weekNumber);
        } 
        
        $dayOfWeek = $_date->get('w');
        // in some locales sunday is last day of the week -> we need to init dayOfWeek with 7
        $dayOfWeek = ($firstDayOfWeek == 1 && $dayOfWeek == 0) ? 7 : $dayOfWeek;
        $_date->sub($dayOfWeek - $firstDayOfWeek, Tinebase_DateTime::MODIFIER_DAY);
        
        $firstDay = $_date->toString($this->_dateFormat);
        $_date->add(6, Tinebase_DateTime::MODIFIER_DAY);
        $lastDay = $_date->toString($this->_dateFormat);
            
        $result = array(
            $firstDay,
            $lastDay, 
        );
        
        return $result;
    }
    
    /**
     * returns number of the first day of the week (0 = sunday or 1 = monday) depending on locale
     * 
     * @return integer
     */
    protected function _getFirstDayOfWeek()
    {
        $locale = Tinebase_Core::getLocale();
        $weekInfo = Zend_Locale_Data::getList($locale, 'week');
        
        $result = ($weekInfo['firstDay'] == 'sun') ? 0 : 1;
        
        return $result;
    }
}
