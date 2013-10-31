#Dependency Injection

Apricot provides a generic and very simple DI container that can provide objects previously registered or created on-the-fly using definitions. You can also use the container to store configuration parameters or arbitrary values.

##Registering parameters or objects

```php
use Apricot\Apricot as App;

App::set('user.name', 'Frank');

echo App::get('user.name'); // Frank
```

You can also register instantiated objects:
```php
$user = new MyUser('Frank');

App::set('user', $user);

echo App::get('user')->getUsername(); // ...whatever you want to do with your object

```

##Telling Apricot how to create objects

```php
App::provide('spam_detector', 'Your\Full\ClassName\To\SpamDetector');

$detector = App::get('spam_detector'); // our SpamDetector object has just been created
```

A third optional argument is an array where you tell which parameters Apricot has to give to the class
constructor when instantiating the object. For example, if the constructor of our class SpamDetector takes
an argument which is an array "whiteList" of addresses to trust, here's how we would did this:

```php
App::provide('spam_detector', 'Your\Full\ClassName\To\SpamDetector', array($whiteList));

$detector = App::get('spam_detector'); // our SpamDetector object has just been created
```

You can also register multiple definitions at the same time:

```php
App::provide(array(
    'spam_detector' => array(
        'class' => 'Your\Full\ClassName\To\SpamDetector',
        'arguments' => array($whiteList),
    ),
    'mailer' => array(
        'class' => 'Your\Full\ClassName\To\Mailer',
        'arguments' => array($transport, $deliverTo),
    ),
));

```

##Scopes

A scope is an independent container that can be accessed using the ```scope``` method:
```php

$users = somehowGetUsersFromDatabase();

App::scope('users', function ($scope) use ($users)
{
    $scope['users'] = $users;
});
```

Doing this, we created the scope "users", which now contains a variable "users" that holds an array we just retrieved somehow, containing 
our users information (this is an example).
We can now re-open this scope later in our code:
```php
App::when('/users', function ()
{
    App::scope('users', function ($scope)
    {
        $users = $scope['users'];

        echo json_encode($users); // a json API for example
    });
});

```

Re-opening the scope everytime you want to inject a variable in it can be boring, that's why the ```inject``` method is here:
```php
$users = somehowGetUsersFromDatabase();

// this will do exactly the same thing as above
App::inject('users', array(
    'users' => $users,
));
```

To avoid further modifications on a scope, just ```freeze``` it:

```php
// We don't want it to be modified anymore
App::freeze('users');
```

Now trying to re-open the scope will throw a ```LogicException```.

