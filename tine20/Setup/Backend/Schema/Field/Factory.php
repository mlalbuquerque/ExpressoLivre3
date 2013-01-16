<?php
/**
 * backend factory class for the Setup
 * 
 * @package     Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Matthias Greiling <m.greiling@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * backend factory class for the Setup
 * 
 * an instance of the Setup backendclass should be created using this class
 * 
 * $contacts = Setup_Backend::factory(Setup_Backend::$type);
 * 
 * currently implemented backend classes: Setup_Backend::MySql
 * 
 * @package     Setup
 */
class Setup_Backend_Schema_Field_Factory
{

    /**
     * factory function to return a selected contacts backend class
     *
     * @param string $type
     * @return object
     */
    static public function factory($_type, $_definition)
    {
        $className = 'Setup_Backend_Schema_Field_' . ucfirst($_type);
        $instance = new $className($_definition);

        return $instance;
    }
}    
