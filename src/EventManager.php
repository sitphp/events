<?php


namespace SitPHP\Events;

use Closure;
use Exception;
use InvalidArgumentException;
use SitPHP\Benchmarks\Bench;
use SitPHP\Benchmarks\BenchManager;
use SitPHP\Helpers\Collection;
use SitPHP\Helpers\Text;

class EventManager{

    const PRIORITY_VERY_LOW = 10;
    const PRIORITY_LOW = 30;
    const PRIORITY_MEDIUM = 50;
    const PRIORITY_HIGH = 70;
    const PRIORITY_VERY_HIGH = 90;

    private $listeners_def = [];
    private $disabled = false;
    private $disabled_events = [];
    private $event_log = [];
    private $listener_log = [];
    /**
     * @var BenchManager
     */
    private $bench_manager;
    /**
     * @var bool
     */
    private $is_log_active = false;
    private $fire_count = [];


    /**
     * Add event listener
     *
     * @param $event_name
     * @param $listener
     * @param int|null $priority
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

        $this->listeners_def[] = [
            'event'=> $event_name,
            'call' => $call,
            'method' => $method,
            'args' => $args,
            'priority' => $priority,
            'count' => 0
        ];
    }


    /**
     * Remove event listeners
     *
     * @param string $event_name
     */
    function removeEventListeners(string $event_name){
        $listeners = $this->getEventListenersDef($event_name);
        foreach($listeners as $key => $listener){
            unset($this->listeners_def[$key]);
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
    function fire($event, array $params = []): Event
    {

        // Validate event
        if(is_string($event)){
            $event = new Event($event);
        } else if (!$event instanceof Event){
            throw new InvalidArgumentException('Invalid $event argument : expected string or instance of '.Event::class);
        }

        // Check if event has been disabled
        if(!$this->isEventEnabled($event->getName())){
            return $event;
        }

        // Prepare event
        foreach ($params as $event_name => $value) {
            $event->setParam($event_name, $value);
        }
        $event->setManager($this);

        // Get event listeners definitions
        $listeners_def = $this->getEventListenersDef($event->getName());
        $listeners_def = $listeners_def->sortBy('priority', true);

        // Run event
        $event_bench = $this->getBenchManager()->benchmark();
        $event_bench->start();
        foreach ($listeners_def as $listener_key => $listener_def) {

            // Execute listener
            $listener_call = $this->resolveListenerCall($listener_def);

            $listener_bench = $this->getBenchManager()->benchmark();
            $listener_bench->start();
            $response = call_user_func_array($listener_call, [$event]);
            $listener_bench->stop();

            // Log listener
            $this->logListener($listener_key, $listener_bench ,$event);

            // Stop propagation
            if ($event->isPropagationStopped() || $response === false) {
                break;
            }
        }
        $event_bench->stop();
        // Log event
        $this->logEvent($event, $event_bench);

        return $event;
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
    function isEventEnabled(string $event_name): bool
    {
        $enabled = true;
        if($this->disabled || isset($this->disabled_events[$event_name]) && $this->disabled_events[$event_name]){
            $enabled = false;
        }
        return $enabled;
    }

    /*
     * Log methods
     */
    function enableLog(){
        $this->is_log_active = true;
    }

    function disableLog(){
        $this->is_log_active = false;
    }

    function isLogActive(): bool
    {
        return $this->is_log_active;
    }

    function getEventLog(): ?array
    {
        if(!$this->is_log_active){
            return null;
        }
        return $this->event_log;
    }

    function getListenerLog(): ?array
    {
        if(!$this->is_log_active){
            return null;
        }
        return $this->listener_log;
    }


    /**
     * Return how many times an event was fired
     *
     * @param string $event_name
     * @return int
     */
    function getFireCount(string $event_name): int
    {
        return $this->fire_count[$event_name] ?? 0;
    }


    function getEventListenersReport(string $event): Collection
    {

        $listeners_def = $this->getEventListenersDef($event);
        $report = new Collection();
        foreach($listeners_def as $listener_def){
            $report[] = [
                'event' => $listener_def['event'],
                'priority' => $listener_def['priority'],
                'call' => $this->resolveListenerCallInfo($listener_def),
                'count' => $listener_def['count']
            ];
        }
        return $report;
    }

    function getAllListenersReport(): Collection
    {
        $report = new Collection();
        foreach($this->listeners_def as $listener_def){
            $report[] = [
                'event' => $listener_def['event'],
                'priority' => $listener_def['priority'],
                'call' => $this->resolveListenerCallInfo($listener_def),
                'count' => $listener_def['count']
            ];
        }
        return $report;
    }

    /**
     * Save fired event infos
     *
     * @param Event $event
     * @param Bench $bench
     */
    protected function logEvent(Event $event, Bench $bench){
        if(!isset($this->fire_count[$event->getName()])){
            $this->fire_count[$event->getName()] = 0;
        }
        $this->fire_count[$event->getName()]++;

        if(!$this->is_log_active){
            return;
        }

        $this->event_log[] = [
            'event' => $event,
            'bench' => $bench
        ];
    }

    /**
     * Save executed listener infos
     *
     * @param int $listener_key
     * @param Bench $bench
     * @param Event $event
     */
    protected function logListener(int $listener_key, Bench $bench, Event $event){
        $this->listeners_def[$listener_key]['count']++;

        if(!$this->is_log_active){
            return;
        }

        $listener_def = $this->listeners_def[$listener_key];

        $listener_log = [
            'event' => $event,
            'priority' => $listener_def['priority'],
            'call' => $this->resolveListenerCallInfo($listener_def),
            'bench' => $bench
        ];

        $this->listener_log[] = $listener_log;
    }

    /**
     * Return event listeners' definitions
     *
     * @param string $event_name
     * @return Collection
     */
    protected function getEventListenersDef(string $event_name): Collection
    {
        $listeners = new Collection();
        foreach($this->listeners_def as $key => $listener) {
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
     * @return string
     */
    protected function resolveListenerCallInfo(array $listener_def): string
    {
        if(is_string($listener_def['call'])){
            $call = $listener_def['call'].'::'.$listener_def['method'];
        } else if($listener_def['call'] instanceof Listener){
            $call = get_class($listener_def['call']).'::'.$listener_def['method'];
        } else {
            $call = 'closure';
        }
        return $call;
    }

    /**
     * Return listener call from listener definition
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

        return isset($listener_def['method']) ? [$listener, $listener_def['method']] : $listener;
    }


    /**
     * Set bench manager
     *
     * @param BenchManager $bench_manager
     */
    function setBenchManager(BenchManager $bench_manager){
        $this->bench_manager = $bench_manager;
    }

    /**
     * Return bench manager
     *
     * @return BenchManager
     */
    protected function getBenchManager(): BenchManager
    {
        if(!isset($this->bench_manager)){
            $this->bench_manager = new BenchManager();
        }
        return $this->bench_manager;
    }
}