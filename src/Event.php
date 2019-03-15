<?php

namespace SitPHP\Events;

use SitPHP\Services\ServiceTrait;

class Event
{
    use ServiceTrait;

    protected static $services = [
        'event_manager' => EventManager::class
    ];
    protected static $instances = [];

    // User properties
    protected $name;
    protected $params = [];
    protected $fire_count = 0;


    /**
     * @param string $name
     * @return self
     */
    static function getInstance(string $name)
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }
        self::$instances[$name] = new static($name);
        return self::$instances[$name];
    }

    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Return event name
     *
     * @return string
     */
    function getName(): string
    {
        return $this->name;
    }

    function addParam($value){
        $this->params[] = $value;
    }

    /**
     * Set event param
     *
     * @param string|int $name
     * @param $value
     */
    function setParam($name, $value)
    {
        if (is_string($name) || is_int($name)) {
            $this->params[$name] = $value;
        } else {
            throw new \InvalidArgumentException('Invalid $label argument type : expected string or array of strings');
        }
    }


    /**
     * Return event param
     *
     * @param $name
     * @return array|string|null
     */
    function getParam($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Reset params
     */
    function resetParams(){
        $this->params = [];
    }

    function removeParam($name){
        unset($this->params[$name]);
    }

    /**
     * Return all event params
     *
     * @return array
     */
    function getAllParams(){
        return $this->params;
    }

    /**
     * Fire event with given params
     *
     * @param array|null $params
     */
    function fire(array $params = [])
    {
        foreach ($params as $key => $value){
            $this->setParam($key, $value);
        }
        $this->fire_count++;

        $listeners = $this->getListeners();

        foreach ($listeners as $listener) {
            $call = $listener['call'];
            $method = $listener['method'];
            if (is_subclass_of($call, Listener::class)) {
                /** @var Listener $call */
                if(!is_object($call)) {
                    $call = $call::getInstance();
                }
                if($method !== null) {
                    $response = $call->execute($this, $method);
                } else {
                    $response = $call->execute($this);
                }
            } else if ($call instanceof \Closure) {
                $response = $call($this);
            }
            else {
                throw new \InvalidArgumentException('Invalid listener call type : expected instance of '.\Closure::class.' or subclass of ' . Listener::class . '. Type ' . gettype($call) . ' found');
            }
            if ($response === false) {
                break;
            }
        }
        $this->params = [];
    }

    /**
     * Check is event was fired
     *
     * @return bool
     */
    function isFired()
    {
        return $this->fire_count > 0;
    }

    /**
     * Return fire count
     *
     * @return int
     */
    function getFireCount()
    {
        return $this->fire_count;
    }

    /**
     * Get event listeners
     *
     * @return \SitPHP\Helpers\Collection
     */
    function getListeners()
    {
        /** @var EventManager $event_manager */
        $event_manager = self::getServiceClass('event_manager');
        return $event_manager::getListeners($this->getName());
    }

    function removeListeners(){
        /** @var EventManager $event_manager */
        $event_manager = self::getServiceClass('event_manager');
        return $event_manager::removeListeners($this->getName());
    }
}