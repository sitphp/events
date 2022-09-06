<?php

namespace SitPHP\Events\Tests;

use SitPHP\Doubles\Double;
use SitPHP\Doubles\TestCase;
use SitPHP\Events\Event;
use SitPHP\Events\EventManager;

class EventTest extends TestCase
{

    /*
     * Test name
     */
    function testGetName()
    {
        $event = new Event('my_event');
        $this->assertEquals('my_event', $event->getName());
    }

    /*
     * Test params
     */
    function testAddParam()
    {
        $event = new Event('my_event');
        $event->addParam('param');
        $this->assertEquals('param', $event->getParam(0));
    }

    function testSetParamWithInvalidNameShouldFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        $event = new Event('my_event');
        $event->setParam(new \stdClass(), 'value');
    }

    function testRemoveParam()
    {
        $event = new Event('my_event');
        $event->removeAllParams();
        $event->setParam('param_1', 'param 1');
        $event->removeParam('param_1');
        $this->assertNull($event->getParam('param_1'));
        $this->assertNull($event->getParam('undefined'));
    }

    function testRemoveAllParams()
    {
        $event = new Event('my_event');
        $event->addParam('param 1');
        $event->addParam('param 2');

        $this->assertEquals(['param 1', 'param 2'], $event->getAllParams());
        $event->removeAllParams();
        $this->assertEquals([], $event->getAllParams());
    }

    function testHasParam(){
        $event = new Event('my_event');
        $event->setParam('param_1','param 1');

        $this->assertTrue($event->hasParam('param_1'));
        $this->assertFalse($event->hasParam('undefined'));
    }

    /*
     * Test propagation
     */
    function testPropagation(){
        $event = new Event('my_event');
        $this->assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }

    /*
     * Test manager
     */
    function testManager(){
        $event = new Event('my_event');
        $event_manager = new EventManager();
        $event->setManager($event_manager);
        $this->assertSame($event_manager, $event->getManager());
    }


    /*
     * Test fire count
     */
    function testFireCount(){
        $event = new Event('my_event');

        /** @var EventManager $event_manager */
        $event_manager = Double::dummy(EventManager::class)->getInstance();
        $event_manager::_method('getFireCount')->return(3);
        $event->setManager($event_manager);
        $this->assertEquals(3, $event->getFireCount());
    }
}