#Basic routing
An important step into the request lifecycle is the routing of it to an appropriate executable code that will treat it to send a response to the web client.
In Apricot, routes are drawn this way:

```php

require 'Apricot.php';

use Apricot\Apricot as App;

App::when('/', function () {
    echo "Hello!";
});

App::run();

```

What we did here it's easy to understand: we bould the path "/" to a PHP closure that will be executed when this path will be requested.
We could also use the ```home``` method, which is a simple shortcut for using ```when``` with the "/" path:

```php
App::home(function () {
    echo "Hello!";
});
```

###Using routes with parameters

```php

require 'Apricot.php';

use Apricot\Apricot as App;

App::when('/hello/:name', function ($name) {
    echo "Hello $name !";
});

App::run();

```

###Adding requirements to routes

```php

App::when('/posts/:id', App::with(array('id' => '\d+'), function ($id) {
    echo "You're looking at post #$id";
}));

```

###Using prefixes

```php

App::prefix('/posts', function () {

    App::when('/:id', function ($id) {
        echo "You're looking at post #$id";
    });

});

```

###Handling 404 errors
If no route matches the requested path, a 404 error will be triggered inside Apricot. The default behavior is to display a simple message "404 Not Found".
You can change this behavior by passing a callback to the ```notFound``` method of Apricot.
```php
App::notFound(function ($path) {
   die ("Whoops, couldn't find path $path"); 
});
```
