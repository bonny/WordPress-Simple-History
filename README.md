# Simple History 2 – a simple, lightweight, extendable logger for WordPress

Simple History is a WordPress plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.

Download from WordPress.org:  
https://wordpress.org/plugins/simple-history/

[![Build Status](https://travis-ci.org/bonny/WordPress-Simple-History.svg?branch=master)](https://travis-ci.org/bonny/WordPress-Simple-History)
![Rating at wordpress.org](https://img.shields.io/wordpress/plugin/r/simple-history.svg)
![Number of downloads](https://img.shields.io/wordpress/plugin/dt/simple-history.svg)

# Screenshots

## Viewing history events

This screenshot show the log view + it also shows the filter function in use: the log only shows event that
are of type post and pages and media (i.e. images & other uploads), and only events
initiated by a specific user.

![Simple History screenshot](https://ps.w.org/simple-history/assets/screenshot-1.png?rev=1)

## Events with different severity

Simple History uses the log levels specified in the [PHP PSR-3 standard](http://www.php-fig.org/psr/psr-3/).

![Simple History screenshot](https://ps.w.org/simple-history/assets/screenshot-2.png?rev=1096689)

## Events have context with extra details

Each logged event can include useful rich formatted extra information. For example: a plugin install can contain author info and a the url to the plugin, and an uploaded image can contain a thumbnail of the image.

![Simple History screenshot](http://ps.w.org/simple-history/assets/screenshot-3.png?rev=1096689)

# Plugin API

Developers can easily log their own things using a simple API:

```php
<?php

// This is the easiest and safest way to add messages to the log
// If the plugin is disabled this way will not generate in any error
apply_filters("simple_history_log", "This is a logged message");
apply_filters("simple_history_log", "This is a message with some context added", ["isATestMessage" => "yup", "debugRequestData" => $_REQUEST]);
apply_filters("simple_history_log", "This is another logged message, with another severity level", null, "debug");

// You can olsy use functions/methods to add events to the log
SimpleLogger()->info("This is a message sent to the log");

// Add events of different severity
SimpleLogger()->info("User admin edited page 'About our company'");
SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
SimpleLogger()->debug("Ok, cron job is running!");

```

You will find more examples in the [examples.php](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php) file.
