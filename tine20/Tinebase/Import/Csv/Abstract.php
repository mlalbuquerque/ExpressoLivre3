<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * @todo        add tests for notes
 * @todo        add more documentation
 */

/**
 * abstract csv import class
 * 
 * @package     Tinebase
 * @subpackage  Import
 * 
 * some documentation for the xml import definition:
 * 
 * <delimiter>TAB</delimiter>:           use tab as delimiter
 * <config> main tags
 * <container_id>34</container_id>:     container id for imported records (required)
 * <encoding>UTF-8</encoding>:          encoding of input file
 * <duplicates>1<duplicates>:           check for duplicates
 * <use_headline>0</use_headline>:      just remove the headline/first line but do not use it for mapping
 * 
 * <mapping><field> special tags:
 * <append>glue</append>:               value is appended to destination field with 'glue' as glue
 * <replace>\n</replace>:               replace \r\n with \n
 * <fixed>fixed</fixed>:                the field has a fixed value ('fixed' in this example)
 * 
 */
abstract class Tinebase_Import_Csv_Abstract extends Tinebase_Import_Abstract
{
    /**
     * csv headline
     * 
     * @var array
     */
    protected $_headline = array();
    
    /**
     * special delimiters
     * 
     * @var array
     */
    protected $_specialDelimiter = array(
        'TAB'   => "\t"
    );
    
    /**
     * constructs a new importer from given config
     * 
     * @param array $_options
     */
    public function __construct(array $_options = array())
    {
        $this->_options = array_merge($this->_options, array(
            'maxLineLength'     => 8000,
            'delimiter'         => ',',
            'enclosure'         => '"',
            'escape'            => '\\',
            'encoding'          => 'UTF-8',
            'encodingTo'        => 'UTF-8',
            'mapping'           => '',
            'headline'          => 0,
            'use_headline'      => 1,
        ));
        
        parent::__construct($_options);
        
        if (empty($this->_options['model'])) {
            throw new Tinebase_Exception_InvalidArgument(get_class($this) . ' needs model in config.');
        }
        
        $this->_setController();
    }
    
    /**
     * get raw data of a single record
     * 
     * @param  resource $_resource
     * @return array
     */
    protected function _getRawData($_resource) 
    {
        $delimiter = (array_key_exists($this->_options['delimiter'], $this->_specialDelimiter)) ? $this->_specialDelimiter[$this->_options['delimiter']] : $this->_options['delimiter'];
        
        $lineData = fgetcsv(
            $_resource, 
            $this->_options['maxLineLength'], 
            $delimiter,
            $this->_options['enclosure'] 
            // escape param is only available in PHP >= 5.3.0
            // $this->_options['escape']
        );
        
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($lineData, TRUE));
        if (is_array($lineData) && count($lineData) == 1) {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Only got 1 field in line. Wrong delimiter?');
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Raw data: ' . print_r($lineData, true));
        
        return $lineData;
    }
    
    /**
     * do something before the import
     * 
     * @param resource $_resource
     */
    protected function _beforeImport($_resource = NULL)
    {
        // get headline
        if (isset($this->_options['headline']) && $this->_options['headline']) {
            $this->_headline = $this->_getRawData($_resource);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                . ' Got headline: ' . implode(', ', $this->_headline));
            if (! $this->_options['use_headline']) {
                // just read headline but do not use it
                $this->_headline = array();
            }
        }        
    }
    
    /**
     * do the mapping
     *
     * @param array $_data
     * @return array
     */
    protected function _doMapping($_data)
    {
        $data = array();
        $_data_indexed = array();

        if (! empty($this->_headline) && sizeof($this->_headline) == sizeof($_data)) {
            $_data_indexed = array_combine($this->_headline, $_data);
        }
        
        foreach ($this->_options['mapping']['field'] as $index => $field) {
            if (empty($_data_indexed)) {
                // use import definition order
                if (! array_key_exists('destination', $field) || $field['destination'] == '' || ! isset($_data[$index])) {
                    continue;
                }
                $data[$field['destination']] = $_data[$index];
            } else {
                // use order defined by headline
                if ($field['destination'] == '' || ! isset($field['source']) || ! isset($_data_indexed[$field['source']])) {
                    continue;
                }
                $data[$field['destination']] = $_data_indexed[$field['source']];
            }
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Mapped data: ' . print_r($data, true));
        
        return $data;
    }
}
