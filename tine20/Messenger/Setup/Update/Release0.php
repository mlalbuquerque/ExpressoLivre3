<?php

/**
 * Tine 2.0
 *
 * @package     Messenger
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Marcio Albuquerque <marcio.albuquerque@serpro.gov.br>
 */

class Messenger_Setup_Update_Release0 extends Setup_Update_Abstract
{
    /**
     * update to 1.0
     * @return void
     */
    public function update_1()
    {
        $this->setApplicationVersion('Messenger', '1.0');
    }
}