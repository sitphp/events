<?php


namespace SitPHP\Events;

use Closure;
use Exception;
use InvalidArgumentException;
use SitPHP\Helpers\Collection;
use SitPHP\Helpers\Text;

class EventManager{

    const PRIORITY_VERY_LOW = 10;
    const PRIORITY_LOW = 30;
    const PRIORITY_MEDIUM = 50;
    const PRIORITY_HIGH = 70;
    const PRIORITY_VERY_HIGH = 90;

    private $listeners = [];
    private $disabled = false;
    private $disabled_events = [];
    private $fired_events = [];
    private $called_listeners_count = [];

    /**
     * Add event listener
     *
     * @param $event_name
     * @param $listener
     * @param int $priority
     */
    function addListener($event_name, $listener, int $priority = null){

        if($priority === null){
            $priority = self::PRIORITY_MEDIUM;
        }

        // Parse listener
        $method = null;
        $args = null;
        if(is_string($listener)){
            $call = $listener;
            $method = 'handle';
        } else if(is_array($listener)){
            $call = $listener['call'] ?? $listener[0] ?? null;
            $method = $listener['method'] ??  $listener[1] ?? 'handle';
            $args = $listener['args'] ?? $listener[2] ?? null;
            if($args !== null && !is_array($args)){
                $args = [$args];
            }

        } else if($listener instanceof Closure){
            $call = $listener;
        } else if($listener instanceof Listener) {
            $call = $listener;
            $method = 'handle';
        } else {
            throw new InvalidArgumentException('Invalid $listener argument type : expected class (string), array, instance of '. Closure::class.' or instance of '.Listener::class);
        }

        // Validate call
        if($call === null){
            throw new InvalidArgumentException('Invalid $listener argument : undefined call');
        } else if(is_string($call) && !class_exists($call, false)){
            throw new InvalidArgumentException('Invalid $listener call : class '.$call.' does not exist');
        }

        // Validate method
        if(isset($method) && !is_string($method)){
            throw new InvalidArgumentException('Invalid $listener method type : expected string');
        }

        $this->listeners[] = [
            'event'=> $event_name,
            'call' => $call,
            'method' => $method,
            'args' => $args,
            'priority' => $priority,
        ];
    }


    /**
     * Remove event listeners
     *
     * @param string $event_name
     */
    function removeEventListeners(string $event_name){
        $listeners = $this->resolveEventListenersDef($event_name);
        foreach($listeners as $key => $listener){
            unset($this->listeners[$key]);
        }
    }

    /**
     * Add event subscriber(s)
     *
     * @param $subscriber
     * @throws Exception
     */
    function addSubscriber($subscriber){

        if(is_array($subscriber)){
            $call = $subscriber['call'] ?? $subscriber[0] ?? null;
            $args =  $subscriber['args'] ?? $subscriber[1] ?? null;
            if($args !== null && !is_array($args)){
                $args = [$args];
            }
        } else if(is_string($subscriber)) {
            $call = $subscriber;
            $args = null;
        } else if($subscriber instanceof  Subscriber){
            $call = $subscriber;
            $args = null;
        } else {
            throw new InvalidArgumentException('Invalid $subscriber argument type : expected class (string), array or instance of '.Subscriber::class);
        }

        // Validate call
        if($call === null){
            throw new InvalidArgumentException('Invalid $subscriber argument : undefined call');
        } else if(is_string($call)){
            if(!is_subclass_of($call, Subscriber::class)){
                throw new InvalidArgumentException('Invalid subscriber class '.$call.' : expected subclass of '.Subscriber::class);
            }
            $subscriber = $args !== null ? new $call(...$args) : new $call();
        } else {
            $subscriber = $call;
        }

        /** @var Subscriber $subscriber */
        foreach($subscriber->getEventListeners() as $event_name => $listeners){
            foreach($listeners as $listener){
                if(is_string($listener)){
                    $method = $listener;
                    $priority = null;
                } else if(is_array($listener)) {
                    $method = $listener['method'] ?? $listener[0];
                    $priority = $listener['priority'] ?? $listener[1] ?? null;
                } else {
                    throw new InvalidArgumentException('Invalid subscriber listener : expected string or array');
                }
                $this->addListener($event_name, [$subscriber, $method], $priority);
            }
        }
    }

    /**
     * Fire event
     *
     * @param $event
     * @param array $params
     * @return Event
     */
    function fire($event, array $params = []){

        if(is_string($event)){
            $event = new Event($event);
        } else if (!$event instanceof Event){
            throw new InvalidArgumentException('Invalid $event argument : expected string or instance of '.Event::class);
        }

        // Set event params
        foreach ($params as $event_name => $value) {
            $event->setParam($event_name, $value);
        }
        $this->notifyEventFired($event);
        $event->setManager($this);

        // Check if event has been disabled
        if(!$this->isEventEnabled($event->getName())){
            return $event;
        }

        $listeners_def = $this->resolveEventListenersDef($event->getName());
        $listeners_def = $listeners_def->sortBy('priority', true);

        foreach ($listeners_def as $listener_key => $listener_def) {
            // Call listener
            $call = $this->resolveListenerCall($listener_def);
            $response = call_user_func_array($call, [$event]);

            // Listener call
            $this->notifyListenerExecuted($listener_key);

            // Stop propagation
            if ($event->isPropagationStopped() || $response === false) {
                break;
            }
        }
        return $event;
    }

    /**
     * Return how many times an event was fired
     *
     * @param string $event_name
     * @return int
     */
    function getFireCount(string $event_name){
        if(isset($this->fired_events[$event_name])){
            return count($this->fired_events[$event_name]);
        }
        return 0;
    }


    /**
     * Disable execution of listeners of all events
     */
    function disableAllEvents(){
        $this->disabled = true;
    }

    /**
     *  Enable execution of listeners of all events
     */
    function enableAllEvents(){
        $this->disabled_events = [];
        $this->disabled = false;
    }

    /**
     * Disable execution of listeners of event(s)
     *
     * @param $event_names
     */
    function disableEvent($event_names){
        foreach((array) $event_names as $event_name){
            $this->disabled_events[$event_name] = true;
        }
    }

    /**
     * Enable execution of listeners of event(s)
     *
     * @param $event_names
     */
    function enableEvent($event_names){
        foreach((array) $event_names as $event_name) {
            $this->disabled_events[$event_name] = false;
        }
    }

    /**
     * Check if event is enabled
     *
     * @param string $event_name
     * @return bool
     */
    function isEventEnabled(string $event_name){
        $enabled = true;
        if($this->disabled || isset($this->disabled_events[$event_name]) && $this->disabled_events[$event_name]){
            $enabled = false;
        }
        return $enabled;
    }


    /**
     * Return all event listeners infos
     *
     * @return array|Collection
     */
    function getAllListenersInfos(){
        $listeners_infos = new Collection();
        foreach($this->listeners as $key => $listener_def){
            $listeners_info = $this->resolveListenerInfo($listener_def);
            $listeners_info['count'] = $this->called_listeners_count[$key] ?? 0;
            $listeners_infos[] = $listeners_info;
        }
        return $listeners_infos;
    }

    /**
     * Return event listeners infos
     *
     * @param string $event_name
     * @return array|Collection
     */
    function getListenersInfos(string $event_name){
        $listeners_infos = new Collection();
        $listeners = $this->resolveEventListenersDef($event_name);
        foreach($listeners as $key => $listener_def){
            $listeners_info = $this->resolveListenerInfo($listener_def);
            $listeners_info['count'] = $this->called_listeners_count[$key] ?? 0;
            $listeners_infos[] = $listeners_info;
        }
        return $listeners_infos;
    }

    /**
     * Save executed listener infos
     *
     * @param int $listener_key
     */
    protected function notifyListenerExecuted(int $listener_key){
        if(!isset($this->called_listeners_count[$listener_key])){
            $this->called_listeners_count[$listener_key] = 0;
        }
        $this->called_listeners_count[$listener_key]++;
    }

    /**
     * Save fired event infos
     *
     * @param Event $event
     */
    protected function notifyEventFired(Event $event){
        $event_name = $event->getName();
        if(!isset($this->fired_events[$event_name])){
            $this->fired_events[$event_name] = [];
        }
        $this->fired_events[$event_name][] = $event;
    }

    /**
     * Return event listeners' definitions
     *
     * @param $event_name
     * @return Collection
     */
    protected function resolveEventListenersDef(string $event_name){
        $listeners = new Collection();
        foreach($this->listeners as $key => $listener) {
            if ($event_name !== $listener['event'] && !Text::startsWith($event_name, $listener['event'] . '.')) {
                continue;
            }
            $listeners->set($key, $listener);
        }
        return $listeners;
    }


    /**
     * Return listener info from listener definition
     *
     * @param array $listener_def
     * @return array
     */
    protected function resolveListenerInfo(array $listener_def){
        if(is_string($listener_def['call'])){
            $call = $listener_def['call'].'::'.$listener_def['method'];
        } else if($listener_def['call'] instanceof Listener){
            $call = get_class($listener_def['call']).'::'.$listener_def['method'];
        } else {
            $call = 'closure';
        }
        return [
            'event' => $listener_def['event'],
            'call' => $call,
            'priority' => $listener_def['priority']
        ];
    }

    /**
     * Return listener call for call_user_func() method from listener definition
     *
     * @param array $listener_def
     * @return array|mixed
     */
    protected function resolveListenerCall(array $listener_def){
        $listener = $listener_def['call'];
        $method = $listener_def['method'];
        if(is_string($listener)){
            if(!is_subclass_of($listener, Listener::class)){
                throw new InvalidArgumentException('Invalid listener '.$listener.' : expected subclass of '.Listener::class);
            }
            $listener = isset($listener_def['args']) ? new $listener(...$listener_def['args']) : new $listener();
        }
        if($method !== null && !method_exists($listener, $method)){
            throw new InvalidArgumentException('Method '.$method.' doesnt exist in listener '.get_class($listener));
        }
        // Call listener

        $call = isset($listener_def['method']) ? [$listener, $listener_def['method']] : $listener;
        return $call;
    }
}