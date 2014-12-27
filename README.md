# Simple History 2 – a simple, lightweight, extendable logger for WordPress

Simple History is a WordPress plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.

Download from WordPress.org:  
http://wordpress.org/extend/plugins/simple-history/

# Screenshots

## Viewing history events

This screenshot show the log view + it also shows the filter function in use: the log only shows event that
are of type post and pages and media (i.e. images & other uploads), and only events
initiated by a specific user.

![Simple History screenshot](https://raw.githubusercontent.com/bonny/WordPress-Simple-History/master/screenshot-1.png)

## Events with different severity

Simple History uses the log levels specified in the [PHP PSR-3 standard](http://www.php-fig.org/psr/psr-3/).

![Simple History screenshot](https://raw.githubusercontent.com/bonny/WordPress-Simple-History/master/screenshot-2.png)

## Events have context with extra details

Each logged event can include useful rich formatted extra information. For example: a plugin install can contain author info and a the url to the plugin, and an uploaded image can contain a thumbnail of the image.

![Simple History screenshot](https://raw.githubusercontent.com/bonny/WordPress-Simple-History/master/screenshot-3.png)

# Plugin API

Developers can easily log their own things using a simple API:

```php
<?php
// Add events to the log
SimpleLogger()->info("This is a message sent to the log");

// Add events of different severity
SimpleLogger()->info("User admin edited page 'About our company'");
SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
SimpleLogger()->debug("Ok, cron job is running!");

```

See more examples at  [simple-history.com/docs](http://simple-history.com/docs).
