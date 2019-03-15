<?php

namespace SitPHP\Events;


abstract class Subscriber extends Listener
{
    abstract static function getEventListeners() : array;
}