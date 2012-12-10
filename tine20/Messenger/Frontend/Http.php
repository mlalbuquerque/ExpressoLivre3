<?php

class Messenger_Frontend_Http extends Tinebase_Frontend_Http_Abstract
{

    protected $_applicationName = 'Messenger';

    /**
     * Returns all JS files which must be included for this app
     *
     * @return array Array of filenames
     */
    public function getJsFilesToInclude()
    {
        return array(
            'Messenger/js/Application.js',
            'Messenger/js/jquery-1.7.1.min.js'
        );
    }

    public function getFile($name, $tmpfile, $downloadOption)
    {
        if ($downloadOption == 'yes') {
            header('Cache-Control: private, max-age=0');
            header("Expires: -1");
            // overwrite Pragma header from session
            header("Pragma: cache");
            header('Content-Disposition: attachment; filename="' . $name . '"');
            readfile($tmpfile);
        }
        unlink($tmpfile);
    }

}