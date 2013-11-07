#REST routing

Apricot provide some useful methods to define REST resources with associated routes (i.e index, create, show...).
The main method used to define a resource is... ```resource```:

```php
App::resource('posts', function () {
    
    App::index(function () {
        // retrieve all the posts
    });

    App::show(function ($id) {
        // retrieve post using the $id
    });

});
```

The other methods available are ```create```, ```edit```,```update``` and ```delete```.
These three last, as well as ```show```, takes the resource ID as argument for the given closure.