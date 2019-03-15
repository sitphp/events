<?php

namespace SitPHP\Events\Tests;

use SitPHP\Events\Event;
use SitPHP\Events\EventManager;
use SitPHP\Events\Listener;
use SitPHP\Events\Subscriber;
use SitPHP\Helpers\Collection;

class EventManagerTest extends \Doublit\TestCase
{

    /*
     * Test getEvent
     */
    function testGetEventShouldReturnInstanceOfEvent()
    {
        $my_event = EventManager::getEvent('my_event');
        $this->assertInstanceOf(Event::class, $my_event);
    }

    function testEventClassOfNonChildOfEventClassShouldThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);
        EventManager::getEvent('my_event', \stdClass::class);
    }

    function testGetEventWithClassShouldReturnInstanceOfClass()
    {
        $event = EventManager::mockService('event');
        $event::_method('getInstance')->count(1);
        EventManager::getEvent('my_new_event', $event);
    }


    /*
    * Test fire
    */
    function testFire(){
        $event = EventManager::mockService('event');
        $event::_method('fire')->dummy()->count(1);
        EventManager::fire('fire_event');
    }
    function testFireWithInvalidEventArgumentShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        EventManager::fire(new \stdClass());
    }

    /*
     * Test add listener
     */
    function testAddListener(){

        $my_listener = function(Event $event){

        };
        EventManager::addListener('my_event', \stdClass::class);
        EventManager::addListener('my_event', $my_listener, 2);
        EventManager::addListener('my_event', \stdClass::class, 3);
        EventManager::addListener('my_other_event' , [\stdClass::class, 'my_method'], 4);
        EventManager::addListener('my_other_event' , [\stdClass::class, 'my_other_method']);

        $this->assertEquals(new Collection([
            [
                'call' => \stdClass::class,
                'method' => null,
                'priority' => 3
            ],
            [
                'call' => $my_listener,
                'method' => null,
                'priority' => 2
            ],
            [
                'call' => \stdClass::class,
                'method' => null,
                'priority' => null
            ]
        ]), EventManager::getListeners('my_event'));
        $this->assertEquals(new Collection([
            [
                'call' => \stdClass::class,
                'method' => 'my_method',
                'priority' => 4
            ],
            [
                'call' => \stdClass::class,
                'method' => 'my_other_method',
                'priority' => null
            ],
        ]),EventManager::getListeners('my_other_event'));
    }

    function testAddInvalidListenerTypeShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        EventManager::addListener('my_event', new \stdClass());
    }

    function testAddInvalidListenerCallTypeShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        EventManager::addListener('my_event', ['call' => new \stdClass(), 'method' => 'myMethod']);
    }
    function testAddInvalidListenerMethodTypeShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        EventManager::addListener('my_event', ['call' => \stdClass::class, 'method' => new \stdClass()]);
    }

    /*
     * Test remove listeners
     */
    function testRemoveListeners(){
        EventManager::addListener('my_event', ['call' => \stdClass::class, 'method' => 'myMethod']);
        EventManager::removeListeners('my_event');
        $this->assertEquals(new Collection(), EventManager::getListeners('my_event'));
    }

    /*
     * Test add subscriber
     */
    function testAddSubscriberShouldAddItsListeners(){
        EventManager::addSubscriber(MySubscriber::class);

        $this->assertEquals(new Collection([
            [
                'call' => MySubscriber::class,
                'method' => 'listener3',
                'priority' => 3
            ],
            [
                'call' => MySubscriber::class,
                'method' => 'listener2',
                'priority' => 2
            ],
            [
                'call' => MySubscriber::class,
                'method' => 'listener1',
                'priority' => null
            ],
        ]), EventManager::getListeners('my_subscriber_event'));
    }
    function testSubscribeWithInvalidSubscriberShouldFail(){
        $this->expectException(\InvalidArgumentException::class);
        EventManager::addSubscriber(\stdClass::class);
    }
}

class MyEvent extends Event
{

}

class MyListener extends Listener
{
    function handle(Event $event)
    {
        $called = $event->getParam('called');
        $called();
    }
}
class MyCustomListener extends Listener{

    static function myStaticHandleMethod(Event $event){
        $called = $event->getParam('called');
        $called();
    }

    function myHandleMethod(Event $event){
        $called = $event->getParam('called');
        $called();
    }
}

class MySubscriber extends Subscriber {
    static function getEventListeners(): array
    {
        return [
            'my_subscriber_event' => [
                'listener1',
                ['listener2', 2],
                ['listener3', 3]
            ]
        ];
    }

    function listener1(Event $event){
        if($event->getParam('prioritized')){
            $called = $event->getParam('called');
            $called();
        }
    }

    function listener2(Event $event){
        $event->setParam('prioritized', true);
        $called = $event->getParam('called');
        $called();
    }

    function listener3(Event $event){
        $event->setParam('prioritized', false);
        $called = $event->getParam('called');
        $called();
    }
}