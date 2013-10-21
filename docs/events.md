#Events

You can add listeners to and fire events into Apricot. It is pretty straightforward:
```php
App::on('my_event', function () {
    echo "my_event fired !"; 
});

App::when('/', function () {
    App::emit('my_event');
});

App::run();
```
A third optional argument of ```on``` is an integer which represents the priority of each listener. Apricot will use this value to determine
which listener to call before another for a same event. The default value of this argument is 0 (lowest priority).
```
App::on('my_event', function () {
    echo "Last!";
}, 20);

App::on('my_event', function () {
    echo "First!";
}, 40);
```

Listeners are simple closures, that can accept arguments which are sent when firing the event:
```php
App::on('login_attempt', function($username) {
    echo "$username is trying to log in !"; 
});

App::when('/login/:username', function ($username) {
    App::emit('login_attempt', array($username));
    // code...
});
```

Sometimes you might want to delete all the listeners attached to an event. You can do this using the ```clear``` method:
```
App::on('my_event', function () {
    echo "my_event fired !"; 
});

App::clear('my_event');

App::when('/', function () {
    App::emit('my_event'); // no listener will be called
});

App::run();
```