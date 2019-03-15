<?php

namespace SitPHP\Events;


abstract class Listener
{
    private static $instances = [];
    private $executed = 0;

    static function getInstance(){
        if(!isset(self::$instances[static::class])){
            self::$instances[static::class] = new static();
        }
        return self::$instances[static::class];
    }

    function execute(Event $event, string $handle_method = 'handle'){
        $this->executed++;
        if(!method_exists($this, $handle_method)){
            throw new \InvalidArgumentException('Listener handle method "'.$handle_method.'" doesnt exist in '.self::class);
        }
        $this->$handle_method($event);
    }

}