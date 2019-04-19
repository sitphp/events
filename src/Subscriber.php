<?php

namespace SitPHP\Events;


abstract class Subscriber
{
    abstract function getEventListeners();
}