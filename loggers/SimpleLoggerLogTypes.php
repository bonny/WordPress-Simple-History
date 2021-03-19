<?php

// phpcs:disable PSR12.Properties.ConstantVisibility.NotFound

/**
 * Describes log event type
 * Based on the CRUD-types
 * http://en.wikipedia.org/wiki/Create,_read,_update_and_delete
 * More may be added later on if needed
 * Note: not in use at the moment
 */
class SimpleLoggerLogTypes {

	const CREATE = 'create';
	const READ = 'read';
	const UPDATE = 'update';
	const DELETE = 'delete';
	const OTHER = 'other';
}
