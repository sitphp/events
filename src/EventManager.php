<?php

namespace SitPHP\Events;

use SitPHP\Helpers\Collection;
use SitPHP\Helpers\Text;
use SitPHP\Services\ServiceTrait;

class EventManager{

    use ServiceTrait;

    private static $services = [
        'text' => Text::class,
        'event' => Event::class,
        'collection' => Collection::class
    ];

    protected static $events = [];

    // User static properties
    protected static $listeners = [];
    protected static $subscribers = [];
    protected static $event_classes;

    /**
     * Return new or already created event
     *
     * @param string $name
     * @param string $event_class
     * @return Event
     */
    static function getEvent(string $name, string $event_class = null){
        /** @var Event $event_class */
        if(isset($event_class)){
            if(!is_subclass_of($event_class,Event::class)){
                throw new \InvalidArgumentException('Invalid $event_class argument : expected subclass of class '.Event::class);
            }
            return $event_class::getInstance($name);
        }
        $event = self::getServiceClass('event');
        return $event::getInstance($name);
    }

    /**
     * Add event listener
     *
     * @param $event_name
     * @param $listener
     * @param int $priority
     */
    static function addListener($event_name, $listener, int $priority = 0){

        if(!is_string($listener) && !$listener instanceof \Closure && !is_array($listener)){
            throw new \InvalidArgumentException('Invalid $listener argument type : expected string, array or instance of '.\Closure::class);
        }

        if(is_array($listener)){
            $call = $listener[0] ?? $listener['call'];
            $method = $listener[1] ?? $listener['method'];
            if(!is_string($call)){
                throw new \InvalidArgumentException('Invalid array listener call type : expected string');
            }
            if(!is_string($method)){
                throw new \InvalidArgumentException('Invalid array listener method type : expected string');
            }
        } else {
            $call = $listener;
            $method = null;
        }

        if(!isset(self::$listeners [$event_name])){
            self::$listeners[$event_name] = [];
        }
        self::$listeners[$event_name][] = [
            'call' => $call,
            'method' => $method,
            'priority' => $priority
        ];
    }

    /**
     * Add event subscriber(s)
     *
     * @param string $subscriber
     * @throws \Exception
     */
    static function addSubscriber(string $subscriber){
        if(!is_subclass_of($subscriber, Subscriber::class)){
            throw new \InvalidArgumentException('Invalid $subscriber argument type : expected subclass of '.Subscriber::class);
        }
        /** @var Subscriber $subscriber */
        foreach($subscriber::getEventListeners() as $event_name => $listeners){
            foreach($listeners as $listener){
                if(is_array($listener)){
                    $priority = $listener[1] ?? $listener['priority'];
                    $method = $listener[0] ?? $listener['method'];
                    self::addListener($event_name, [$subscriber,$method], $priority);
                } else if(is_string($listener)) {
                    self::addListener($event_name, [$subscriber,$listener]);
                } else {
                    throw new \Exception('Invalid event subscriber listener type : expected array or string');
                }
            }
        }
    }

    /**
     * Fire event
     *
     * @param $event
     * @param array|null $params
     * @throws \Exception
     */
    static function fire($event, array $params = []){
        if(is_string($event)){
            $event = self::getEvent($event);
        } else if(!is_a($event, Event::class)){
            throw new \InvalidArgumentException('Invalid event type : expected event name (string) or instance of '.Event::class);
        }
        /** @var Event $event */
        $event->fire($params);
    }

    /**
     * Return event listeners
     *
     * @param $event_name
     * @return Collection
     */
    static function getListeners(string $event_name){
        /** @var Text $text */
        $text = self::getServiceClass('text');

        /** @var Collection $listeners */
        $listeners = self::getServiceInstance('collection');
        foreach(self::$listeners as $listener_event_name => $event_listeners){
            if($event_name !== $listener_event_name && !$text::startsWith($event_name, $listener_event_name.'.')){
                continue;
            }
            foreach($event_listeners as $listener){
                $listeners->add($listener);
            }
        }
        $listeners->sortBy('priority', true);
        return $listeners;
    }

    static function removeListeners(string $event_name){
        /** @var Text $text */
        $text = self::getServiceClass('text');
        foreach(self::$listeners as $listener_event_name => $event_listeners){
            if($event_name !== $listener_event_name && !$text::startsWith($event_name, $listener_event_name.'.')){
                continue;
            }
            self::$listeners[$listener_event_name] = [];
        }
    }
}