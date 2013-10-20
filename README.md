#Apricot

Apricot is a micro PHP framework that stands in only one file.

Apricot makes PHP development easy and pleasant, without imposing you any structure or patterns for your application.
You're free to develop as you want, since you can use Apricot components anywhere in your code.

##Basic example

```php
<?php

use Apricot\Apricot as App;

App::when('/', function ()
{
    echo "Hello World!";
});

App::run();

```

##TODO:

- Write more tests
- Write some doc
