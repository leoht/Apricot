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

##Installation
You can install Apricot using Composer:
```json
{
    "require": {
        "leoht/apricot"
    }
}
```

Or download the single file Apricot.php at the root of this repository.

You can now include it into your application entry point (e.g index.php):
```php

require 'Apricot.php';

use Apricot\Apricot as App;

// code...
```