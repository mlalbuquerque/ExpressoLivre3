<?php
/**
 * Tine 2.0
 * 
 * @package     Felamimail
 * @subpackage  Exception
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 *
 */

/**
 * Message Not Found Exception
 * 
 * @package     Felamimail
 * @subpackage  Exception
 */
class Felamimail_Exception_IMAPMessageNotFound extends Felamimail_Exception_IMAP
{
    /**
     * construct
     * 
     * @param string $_message
     * @param integer $_code
     * @return void
     */
    public function __construct($_message = 'Message not found on IMAP server.', $_code = 914) {
        parent::__construct($_message, $_code);
    }
}
