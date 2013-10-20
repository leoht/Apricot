<?php

function autoload($class)
{
    require 'src/'.str_replace('\\', '/', $class).'.php';
}

spl_autoload_register('autoload');
