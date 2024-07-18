<?php
namespace mitcs\zendeskfd;

Use yii\log\FileTarget;

class ZendeskfdFiletarget extends \yii\log\FileTarget{

    // an array of categories that should be logged
    // note: your target receives all `Yii::log()` messages
    // but you can filter them with this array
    // if you want to track all messages of type 'application' as well
    // just include it in the array
    public $categories = ['zendeskfd']; //<- usually your plugin handle or something

    // set the file path
    public function setLogFile($path = __DIR__){
        // I'm lazy so I just use the current location since it's in my 'src/' folder anyway 
        // you can use Crafts default folders or whatever you like
        $this->logFile = $path . '/zendeskfd.log';
        return $this;
    }

    // Optional -> a callback that formats your messages
    // remove this function if there is
    // no formatting needed or if you want to use the default
    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        //$level = 'ZendeskFD';

        if ($category == 'zendeskfd') {
            return date('Y-m-d H:i:s', $timestamp) . " [$level][$category] $text";
        }
    }
}
