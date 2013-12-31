<?php

require 'autoload.php';

$dirname = './src/Apricot/Component';

$compiler = new Apricot\Compiler\Compiler($dirname);

$compiler->compile();