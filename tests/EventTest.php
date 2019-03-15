<?php

namespace SitPHP\Events\Tests;

use Doublit\Lib\DoubleStub;
use SitPHP\Events\Event;
use SitPHP\Events\EventManager;
use SitPHP\Events\Listener;

class EventTest extends \Doublit\TestCase
{

    /*
     * Test instance
     */
    function testGetInstance()
    {
        $event = Event::getInstance('my_event');
        $this->assertInstanceOf(Event::class, $event);
    }

    function testGetInstanceOfSameEventMultipleTimesShouldReturnSameInstance()
    {
        $event1 = Event::getInstance('my_event');
        $event2 = Event::getInstance('my_event');
        $this->assertEquals($event1, $event2);
    }
    function testInstanceOfChild(){
        /** @var MyExtendedEvent $event */
        $event = MyExtendedEvent::getInstance('my_extended_event');
        $this->assertInstanceOf(MyExtendedEvent::class, $event);
        $this->assertTrue($event->constructed);
    }
    function testEventInstanceShouldHaveGivenName()
    {
        $event = Event::getInstance('my_event');
        $this->assertEquals('my_event', $event->getName());
    }

    /*
     * Test params
     */
    function testAddParam()
    {
        $event = Event::getInstance('my_event');
        $event->addParam('param');
        $this->assertEquals('param', $event->getParam(0));
    }

    function testSetParamWithInvalidNameShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        $event = Event::getInstance('my_event');
        $event->setParam(new \stdClass(), 'value');
    }

    function testResetParams()
    {
        $event = Event::getInstance('my_event');
        $event->resetParams();
        $event->addParam('param 1');
        $event->addParam('param 2');

        $this->assertEquals(['param 1', 'param 2'], $event->getAllParams());
        $event->resetParams();
        $this->assertEquals([], $event->getAllParams());
    }

    function testRemoveParam()
    {
        $event = Event::getInstance('my_event');
        $event->resetParams();
        $event->setParam('param_1', 'param 1');
        $event->removeParam('param_1');
        $this->assertNull($event->getParam('param_1'));
        $this->assertNull($event->getParam('undefined'));
    }

    /*
     * Test fire
     */
    function testFire()
    {
        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => function (Event $event) {
                    if ($event->getParam('param') == 'param') {
                        $this->addToAssertionCount(1);
                    }
                },
                'method' => null]
        ]);

        $event = Event::getInstance('my_event');
        $event->fire(['param' => 'param']);
        $this->assertEquals(1, $event->getFireCount());
        $this->assertTrue($event->isFired());
    }

    function testFireWithListenerClass()
    {
        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => MyEventListener::class,
                'method' => null]
        ]);
        $event = Event::getInstance('my_event');
        $event->fire(['called' => function () {
            $this->addToAssertionCount(1);
        }]);

    }

    function testFireWithListenerInstance()
    {
        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => MyEventListener::getInstance(),
                'method' => null]
        ]);
        $event = Event::getInstance('my_event');
        $event->fire(['called' => function () {
            $this->addToAssertionCount(1);
        }]);
    }

    function testFireWithMethodListener()
    {
        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => MyEventListener::class,
                'method' => 'myMethod']
        ]);
        $event = Event::getInstance('my_event');
        $event->fire(['called' => function () {
            $this->addToAssertionCount(1);
        }]);

    }

    function testFireWithStaticMethodListener()
    {
        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => MyEventListener::class,
                'method' => 'myStaticMethod']
        ]);
        $event = Event::getInstance('my_event');
        $event->fire(['called' => function () {
            $this->addToAssertionCount(1);
        }]);
    }

    function testListenerReturningFalseShouldStopEventPropagation(){
        $propagated = false;
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => function (Event $event){
                    return false;
                },
                'method' => 'myStaticMethod'
            ],
            [
                'call' => function (Event $event) use (&$propagated){
                    $propagated = true;
                },
                'method' => 'myStaticMethod'
            ]
        ]);
        $event = Event::getInstance('my_event');
        $event->removeListeners();
        $event->fire();

        $this->assertFalse($propagated);
    }

    function testFireWithInvalidCallListenerShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);

        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => new \stdClass(),
                'method' => 'myStaticMethod']
        ]);
        $event = Event::getInstance('my_event');
        $event->fire();
    }

    function testNonExistentMethodListenerShouldFail()
    {

        $this->expectException(\InvalidArgumentException::class);

        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');
        $event_manager::_method('getListeners')->stub([
            [
                'call' => MyEventListener::class,
                'method' => 'undefined'
            ]
        ]);
        $event = Event::getInstance('my_event');
        $event->fire();

    }

    /*
     * Test remove listeners
     */
    function testRemoveListeners()
    {
        /** @var EventManager & DoubleStub $event_manager */
        $event_manager = Event::dummyService('event_manager');

        $event_manager::_method('removeListeners')->count(1);
        Event::getInstance('my_event')->removeListeners();
    }


}

class MyExtendedEvent extends Event{
    public $constructed = false;

    function __construct($name)
    {
        $this->constructed = true;
        parent::__construct($name);
    }
}

class MyEventListener extends Listener
{
    function handle(Event $event)
    {
        $called = $event->getParam('called');
        $called();
    }

    function myMethod(Event $event)
    {
        $called = $event->getParam('called');
        $called();
    }

    function myStaticMethod(Event $event)
    {
        $called = $event->getParam('called');
        $called();
    }
}