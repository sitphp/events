<?php

namespace SitPHP\Events\Tests;

use Doublit\TestCase;
use InvalidArgumentException;
use SitPHP\Events\Event;
use SitPHP\Events\EventManager;
use SitPHP\Events\Listener;
use SitPHP\Events\Subscriber;
use stdClass;

class EventManagerTest extends TestCase
{

    /*
    * Test fire
    */
    function testFire()
    {
        $event_manager = new EventManager();
        $event = $event_manager->fire('my_event');

        $this->assertEquals(1, $event_manager->getFireCount('my_event'));
        $this->assertInstanceOf(Event::class, $event);
    }
    function testFireWithParams(){
        $event_manager = new EventManager();
        $event = new Event('my_event');
        $event_manager->fire($event , ['param1', 'key2' => 'param2']);

        $this->assertEquals('param1', $event->getParam(0));
        $this->assertEquals('param2', $event->getParam('key2'));

    }
    function testFireWithInvalidEventShouldFail(){
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->fire(new stdClass());
    }

    /*
     * Test listeners
     */
    function testClassListener()
    {
        $event_manager = new EventManager();
        $event_manager->addListener('my_event',MyEventListener::class);
        $event = $event_manager->fire('my_event');
        $this->assertTrue($event->getParam('handle'));

    }

    function testArrayListener()
    {
        $event_manager = new EventManager();
        $arg = new stdClass();
        $event_manager->addListener('my_event', [MyEventListener::class, 'myMethod', $arg]);
        $event = $event_manager->fire('my_event');
        $this->assertTrue($event->getParam('myMethod'));
        $this->assertEquals($arg, $event->getParam('arg1'));
    }

    function testAssociativeArrayListener()
    {
        $event_manager = new EventManager();
        $args = ['arg1', 'arg2'];
        $event_manager->addListener('my_event', ['call' => MyEventListener::class, 'method' => 'myMethod', 'args' => $args]);
        $event = $event_manager->fire('my_event');
        $this->assertTrue($event->getParam('myMethod'));
        $this->assertEquals('arg1', $event->getParam('arg1'));
        $this->assertEquals('arg2', $event->getParam('arg2'));
    }


    function testCallbackListener(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', function (Event $event){
            $event->setParam('fired', true);
        });
        $event = $event_manager->fire('my_event');
        $this->assertTrue($event->getParam('fired'));
    }

    function testListenerInstanceListener(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', new MyEventListener());
        $event = $event_manager->fire('my_event');
        $this->assertTrue($event->getParam('handle'));
    }

    function testListenersPriority(){
        $called_listeners = [];
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', function() use (&$called_listeners){
            $called_listeners[] = 'listener2';
        }, 2);
        $event_manager->addListener('my_event', function() use (&$called_listeners) {
            $called_listeners[] = 'listener3';
        }, 3);
        $event_manager->addListener('my_event', function() use (&$called_listeners) {
            $called_listeners[] = 'listener1';
        }, 1);

        $event_manager->fire('my_event');
        $this->assertEquals(['listener3', 'listener2', 'listener1'], $called_listeners);
    }

    function testListenersGroup(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', function(Event $event){
            $event->setParam('my_event', true);
        });
        $event_manager->addListener('my_event.sub', function(Event $event) {
            $event->setParam('my_event.sub', true);
        });
        $event_manager->addListener('my_event.sub.sub', function(Event $event) {
            $event->setParam('my_event.sub.sub', true);
        });

        $event = $event_manager->fire('my_event.sub');
        $this->assertTrue($event->getParam('my_event'));
        $this->assertTrue($event->getParam('my_event.sub'));
        $this->assertNull($event->getParam('my_event.sub.sub'));
    }

    function testAddListenerWithInvalidArgumentShouldFail(){
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', new stdClass());
    }

    function testAddAssociativeArrayListenerWithoutCallKeyShouldFail(){
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', ['undefined' => MyEventListener::class]);
    }

    function testAddAssociativeArrayListenerWithInvalidMethodShouldFail(){
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', ['call' => MyEventListener::class, 'method' => new stdClass()]);
    }

    function testFireWithInvalidListenerShouldFail()
    {
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', 'invalid');
    }

    function testFireWithInvalidCallListenerShouldFail()
    {
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', stdClass::class);
        $event_manager->fire('my_event');
    }

    function testFireWithUndefinedMethodListenerShouldFail()
    {
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', [MyEventListener::class, 'undefined']);
        $event_manager->fire('my_event');
    }

    /*
     * Test remove listeners
     */
    function testRemoveListeners(){

        $event_manager = new EventManager();
        $event_manager->addListener('my_event', function (Event $event){
            $event->setParam('fired', true);
        });
        $event_manager->addListener('my_event', function (Event $event) {
            $event->setParam('fired', true);
        });
        $event_manager->removeEventListeners('my_event.sub');
        $event = $event_manager->fire('my_event');
        $this->assertNull($event->getParam('fired'));
    }


    /*
     * Test stop propagation
     */
    function testStopPropagation(){

        $propagation = [];
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', function (Event $event) use (&$propagation){
            $propagation[] = 'listener1';
            $event->stopPropagation();
        });
        $event_manager->addListener('my_event', function () use (&$propagation){
            $propagation[] = 'listener2';
        });
        $event_manager->fire('my_event');
        $this->assertEquals(['listener1'], $propagation);
    }
    function testStopReturningFalse(){

        $propagation = [];
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', function (Event $event) use (&$propagation){
            $propagation[] = 'listener1';
            return false;
        });
        $event_manager->addListener('my_event', function () use (&$propagation){
            $propagation[] = 'listener2';
        });
        $event_manager->fire('my_event');
        $this->assertEquals(['listener1'], $propagation);
    }


    /*
     * Test subscriber
     */
    function testSubscriberClass(){
        $event_manager = new EventManager();
        $event_manager->addSubscriber(MySubscriber::class);
        $event = $event_manager->fire('my_subscriber_event');

        $this->assertTrue($event->getParam('listener1'));
        $this->assertTrue($event->getParam('listener2'));
        $this->assertTrue($event->getParam('listener3'));
    }
    function testAddSubscriberInstance(){
        $event_manager = new EventManager();
        $event_manager->addSubscriber(new MySubscriber());
        $event = $event_manager->fire('my_subscriber_event');

        $this->assertTrue($event->getParam('listener1'));
        $this->assertTrue($event->getParam('listener2'));
        $this->assertTrue($event->getParam('listener3'));
    }
    function testSubscriberArray(){
        $args = ['arg1', 'arg2'];
        $event_manager = new EventManager();
        $event_manager->addSubscriber([MySubscriber::class, $args]);
        $event = $event_manager->fire('my_subscriber_event');

        $this->assertTrue($event->getParam('listener1'));
        $this->assertTrue($event->getParam('listener2'));
        $this->assertTrue($event->getParam('listener3'));
        $this->assertEquals($args, $event->getParam('args'));
    }

    function testInvalidSubscribeShouldFail(){
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addSubscriber(stdClass::class);
    }
    function testAddSubscriberWithoutCallShouldFail()
    {
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addSubscriber(['undefined' => MyEventListener::class]);
    }
    function testAddSubscriberWithInvalidTypeShouldFail(){
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addSubscriber(new stdClass());
    }
    function testAddSubscriberWithInvalidListenerShouldFail(){
        $this->expectException(InvalidArgumentException::class);
        $event_manager = new EventManager();
        $event_manager->addSubscriber(MyInvalidSubscriber::class);
    }

    /*
     * Test fire count
     */
    function testFireCount(){
        $event_manager = new EventManager();
        $event_manager->fire('my_event');
        $event_manager->fire('my_event');

        $this->assertEquals(2, $event_manager->getFireCount('my_event'));
        $this->assertEquals(0, $event_manager->getFireCount('undefined'));
    }

    /*
     * Test disable events
     */
    function testDisableEvents(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', MyEventListener::class);
        $event_manager->disableEvent('my_event');
        $event = $event_manager->fire('my_event');
        $this->assertNull($event->getParam('handle'));
    }
    function testDisableAllEvents(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', MyEventListener::class);
        $event_manager->disableAllEvents();
        $event = $event_manager->fire('my_event');
        $this->assertNull($event->getParam('handle'));
    }
    function testEnableEvents(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', MyEventListener::class);
        $event_manager->disableEvent('my_event');
        $event_manager->enableEvent('my_event');
        $event = $event_manager->fire('my_event');
        $this->assertTrue($event->getParam('handle'));
    }
    function testEnableAllEvents(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', MyEventListener::class);
        $event_manager->disableEvent('my_event');
        $event_manager->enableAllEvents();
        $event = $event_manager->fire('my_event');
        $this->assertTrue($event->getParam('handle'));
    }

    /*
     * Test reporting
     */
    function testGetInfos(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', [MyEventListener::class, 'myMethod'],10);
        $event_manager->addListener('my_event', new MyEventListener(),0);
        $event_manager->addListener('my_event', function (){
            return false;
        },5);
        $event_manager->addListener('my_other_event', function (){
            return 'return';
        },5);

        $event_manager->fire('my_event');

        $this->assertEquals([
            [
                'event' => 'my_event',
                'call' => MyEventListener::class.'::myMethod',
                'priority' => 10,
                'count' => 1
            ],
            [
                'event' => 'my_event',
                'call' => MyEventListener::class.'::handle',
                'priority' => 0,
                'count' => 0
            ],
            [
                'event' => 'my_event',
                'call' => 'closure',
                'priority' => 5,
                'count' => 1
            ]
        ], $event_manager->getListenersInfos('my_event.sub')->toArray());
    }

    function testGetAllInfos(){
        $event_manager = new EventManager();
        $event_manager->addListener('my_event', MyEventListener::class,10);
        $event_manager->addListener('my_event', function (){
            return 'return';
        },5);
        $event_manager->addListener('my_other_event', function (){
            return 'return';
        },5);

        $this->assertEquals([
            [
                'event' => 'my_event',
                'call' => MyEventListener::class.'::handle',
                'priority' => 10,
                'count' => 0
            ],
            [
                'event' => 'my_event',
                'call' => 'closure',
                'priority' => 5,
                'count' => 0
            ],
            [
                'event' => 'my_other_event',
                'call' => 'closure',
                'priority' => 5,
                'count' => 0
            ]
        ], $event_manager->getAllListenersInfos()->toArray());
    }
}

class MyEventListener extends Listener
{
    private $arg1;
    private $arg2;

    function __construct()
    {
        $args = func_get_args();
        $this->arg1 = $args[0] ?? null;
        $this->arg2 = $args[1] ?? null;
    }

    function handle(Event $event)
    {
        $event->setParam('arg1', $this->arg1);
        $event->setParam('arg2', $this->arg2);
        $event->setParam('handle', true);
    }

    function myMethod(Event $event)
    {
        $event->setParam('arg1', $this->arg1);
        $event->setParam('arg2', $this->arg2);
        $event->setParam('myMethod', true);
    }
}

class MySubscriber extends Subscriber {

    private $args;

    function __construct()
    {
        $this->args = func_get_args();
    }

    function getEventListeners(): array
    {
        return [
            'my_subscriber_event' => [
                'listener1',
                ['method' => 'listener2', 'priority' => 2],
                ['listener3', 3]
            ]
        ];
    }

    function listener1(Event $event){
        $event->setParam('args', $this->args);
        $event->setParam('listener1', true);
    }

    function listener2(Event $event){
        $event->setParam('listener2', true);
    }

    function listener3(Event $event){
        $event->setParam('listener3', true);
    }
}

class MyInvalidSubscriber extends Subscriber {
    function getEventListeners(): array
    {
        return [
            'my_subscriber_event' => [
                new stdClass()
            ]
        ];
    }
}