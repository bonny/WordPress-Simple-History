<?php

#SimpleLegacyLogger::info("Hey hey!");

/**
 * Logger for events stored earlier than v2
 * and for events added via simple_history_add
 *
 * @since 2.0
 */
class SimpleLegacyLogger extends SimpleLogger
{

	/**
	 * Unique slug for this logger
	 * Will be saved in DB and used to associate each log row with its logger
	 */
	public $slug = "SimpleLegacyLogger";

}

