<?php

exit;

/**
 * Some examples of filter usage and so on
 */


// Never clear the log (default is 60 days)
add_filter("simple_history/db_purge_days_interval", function($days) {

	return "0";

});

