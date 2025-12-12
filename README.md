# <img height="50"  src="./css/simple-history-logo.png" alt="Simple History logo">

[![Run in Smithery](https://smithery.ai/badge/skills/bonny)](https://smithery.ai/skills?ns=bonny&utm_source=github&utm_medium=badge)


<img src="https://img.shields.io/wordpress/plugin/r/simple-history.svg?style=for-the-badge" alt="Plugin rating: 5 stars"> <img src="https://img.shields.io/wordpress/plugin/installs/simple-history?style=for-the-badge" alt="Number of active installs: over 100K"> <img src="https://img.shields.io/wordpress/plugin/dm/simple-history?style=for-the-badge" alt="Number of monthly downloads">

**A WordPress activity log for what matters.**

Simple History is a WordPress audit log plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.  
It's great way to view user activity and keep an eye on what the admin users of a website are doing.

## Installation

Download from [WordPress.org](https://wordpress.org/plugins/simple-history/) and activate.

## Usage

### Viewing history events

This screenshot show the user activity feed:

- It has an active **filter/search in use**:
  - only show changes performed by a specific user
  - it only shows event that are of type post and pages and media (i.e. images & other uploads)
- A thumbnail is shown for the image that is uploaded

![Simple History screenshot](.wordpress-org/screenshot-1.png)

### Events with different severity

Simple History uses the log levels specified in the [PHP PSR-3 standard](https://www.php-fig.org/psr/psr-3/).

### Quick diff lets you see what's changed

![Simple History screenshot](.wordpress-org/screenshot-2.png)

### Events have context with extra details

Each logged event can include useful rich formatted extra information. For example: a plugin install can contain author info and a the url to the plugin, and an uploaded image can contain a thumbnail of the image.

![Simple History screenshot](.wordpress-org/screenshot-3.png)

## Premium Add-on

[Simple History Premium](https://simple-history.com/add-ons/premium) adds:

- **Log Retention** – Set retention policies (30d to forever)
- **Export** – CSV/JSON export of filtered results
- **Stats Dashboard** – Visual summaries of activity trends
- **Custom Events** – Manually log important changes via GUI
- **Stealth Mode GUI** – Control visibility per user (code-free)
- **Sticky Events** – Pin important events to top
- **Ad-Free** – Remove promotional content

[View details](https://simple-history.com/add-ons/premium)

*The free version is fully functional and will remain free. Premium exists to fund ongoing development and provide pro features for agencies/enterprises.*

## Plugin API

Developers can easily log their own things using a simple API:

```php
<?php

// This is the easiest and safest way to add messages to the log
// If the plugin is disabled this way will not generate in any error
do_action('simple_history_log', 'This is a logged message');

// Or with some context and with log level debug:
do_action(
	'simple_history_log',
	'My message about something',
	[
		'debugThing' => $myThingThatIWantIncludedInTheLoggedEvent,
		'anotherThing' => $anotherThing
	],
	'debug'
);

// Or just debug a message quickly
do_action('simple_history_log_debug', 'My debug message');

// You can also use functions/methods to add events to the log
SimpleLogger()->info("This is a message sent to the log");

// Add events of different severity
SimpleLogger()->info("User admin edited page 'About our company'");
SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
SimpleLogger()->debug("Ok, cron job is running!");
```

You will find more examples in the [examples.php](./examples/examples.php) file.

**Note:** Premium users can also add custom events via the GUI at **WordPress Admin > Simple History > Add Custom Event** without writing code.

## Development

### Running tests

See the [README](./tests/readme.md) in `tests` directory.

## Sponsors

### Hosting Sponsor

<a href="https://www.oderland.se" style="float: right; margin-left: 20px;">
  <img src="https://www.oderland.se/wp-content/uploads/2021/11/oderland-1024x576.jpg" alt="" width="150">
</a>

The [Simple History website](https://simple-history.com) is proudly hosted by [Oderland](https://www.oderland.com), a Swedish web hosting provider known for their reliable hosting and excellent support.

### Support Development

Support the free version of Simple History by becoming a sponsor.
You can sponsor using [PayPal](https://www.paypal.com/paypalme/eskapism) or [becoming a GitHub Sponsor](https://github.com/sponsors/bonny).
