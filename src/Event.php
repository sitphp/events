<?php

namespace SitPHP\Events;

use InvalidArgumentException;

class Event
{

    // User properties
    private $name;
    private $is_propagation_stopped = false;
    private $params = [];
    /**
     * @var EventManager
     */
    private $manager;

    /**
     * Event constructor.
     *
     * @param string $name
     */
    function __construct(string $name)
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

    /**
     * Add an event parameter
     *
     * @param $value
     */
    function addParam($value)
    {
        $this->params[] = $value;
    }

    /**
     * Set event parameter
     *
     * @param string|int $name
     * @param $value
     */
    function setParam($name, $value)
    {
        if (is_string($name) || is_int($name)) {
            $this->params[$name] = $value;
        } else {
            throw new InvalidArgumentException('Invalid $label argument type : expected string or array of strings');
        }
    }


    /**
     * Return event parameter
     *
     * @param $name
     * @return array|string|null
     */
    function getParam($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Remove a parameter
     *
     * @param $name
     */
    function removeParam($name)
    {
        unset($this->params[$name]);
    }

    function hasParam(string $name): bool
    {
        return isset($this->params[$name]);
    }

    /**
     * Remove all event parameters
     */
    function removeAllParams()
    {
        $this->params = [];
    }

    /**
     * Return all event params
     *
     * @return array
     */
    function getAllParams(): array
    {
        return $this->params;
    }

    function setManager(EventManager $manager){
        $this->manager = $manager;
    }

    function getManager(): EventManager
    {
        return $this->manager;
    }

    function stopPropagation(){
        $this->is_propagation_stopped = true;
    }

    function isPropagationStopped(): bool
    {
        return $this->is_propagation_stopped;
    }

    function getFireCount(): int
    {
        return $this->manager->getFireCount($this->name);
    }
}