<?php
/**
 * Tine 2.0
 * 
 * @package     Felamimail
 * @subpackage  Exception
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      SERPRO
 *
 */

/**
 * IMAP Folder Duplicated
 * 
 * @package     Felamimail
 * @subpackage  Exception
 */
class Felamimail_Exception_IMAPFolderDuplicated extends Felamimail_Exception_IMAP
{
    const CODE = 933;
    const MSG = 'Perhaps, the IMAP Folder that you want to create already exists.';
    /**
     * construct
     * 
     * @param string $_message
     * @param integer $_code
     * @return void
     */
    public function __construct($_message = self::MSG, $_code = self::CODE) {
        parent::__construct($_message, $_code);
    }
}
