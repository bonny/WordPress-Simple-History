<h2 align="center">
	<img width="20" height="20" src="https://raw.githubusercontent.com/bonny/WordPress-Simple-History/master/assets-wp-repo/icon.svg" alt="">
	Simple History
</h1>

<p align="center">A lightweight activity feed plugin for WordPress</p>

<p align="center">

<img src="https://img.shields.io/wordpress/plugin/r/simple-history.svg?style=for-the-badge" alt="Plugin rating: 5 stars" />

<img src="https://img.shields.io/wordpress/plugin/installs/simple-history?style=for-the-badge" alt="Number of active installs: over 100K">

<img src="https://img.shields.io/wordpress/plugin/dm/simple-history?style=for-the-badge" alt="Number of monthly downloads">

</p>

<p align="center">
Simple History is a WordPress audit log plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI. It's great way to view user activity and keep an eye on what the admin users of a website are doing.
</p>

<p align="center">
Download from WordPress.org: 
<a href="https://wordpress.org/plugins/simple-history/">
<br />
https://wordpress.org/plugins/simple-history/
</a>
</p>

# Screenshots

## Viewing history events

This screenshot show the user activity feed:

- It has an active **filter/search in use**:
  - only show changes performed by a specific user
  - it only shows event that are of type post and pages and media (i.e. images & other uploads)
- A thumbnail is shown for the image that is uploaded

![Simple History screenshot](https://ps.w.org/simple-history/assets/screenshot-1.png?rev=1)

## Events with different severity

Simple History uses the log levels specified in the [PHP PSR-3 standard](https://www.php-fig.org/psr/psr-3/).

## Quick diff lets you see what's changed

![Simple History screenshot](https://ps.w.org/simple-history/assets/screenshot-2.png?rev=1096689)

## Events have context with extra details

Each logged event can include useful rich formatted extra information. For example: a plugin install can contain author info and a the url to the plugin, and an uploaded image can contain a thumbnail of the image.

![Simple History screenshot](https://ps.w.org/simple-history/assets/screenshot-3.png?rev=1096689)

## Plugin API

Developers can easily log their own things using a simple API:

```php
<?php

// This is the easiest and safest way to add messages to the log
// If the plugin is disabled this way will not generate in any error
apply_filters("simple_history_log", "This is a logged message");

// Or with some context and with log level debug:
apply_filters(
	'simple_history_log',
	'My message about something',
	[
		'debugThing' => $myThingThatIWantIncludedInTheLoggedEvent,
		'anotherThing' => $anotherThing
	],
	'debug'
);

// Or just debug a message quickly
apply_filters('simple_history_log_debug', 'My debug message');

// You can also use functions/methods to add events to the log
SimpleLogger()->info("This is a message sent to the log");

// Add events of different severity
SimpleLogger()->info("User admin edited page 'About our company'");
SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
SimpleLogger()->debug("Ok, cron job is running!");
```

You will find more examples in the [examples.php](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php) file.

## Running tests

Tests are located in the `tests`-folder. See [./tests/readme.md](./tests/readme.md).
