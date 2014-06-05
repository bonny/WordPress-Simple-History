<?php

$logQuery = new SimpleHistoryLogQuery();
$logRows = $logQuery->query(array(
	"posts_per_page" => 20
));

/*
Each row looks like:
stdClass Object
(
    [id] => 6004
    [logger] => SimpleLogger
    [message] => User {username} edited page {pagename}
    [occasionsID] => 35afb82ac2eafcced80ed16ea83234c0
    [subsequentOccations] => 5286
    [date] => 2014-06-01 18:08:57
    [rep] => 1
    [repeated] => 1
    [type] => 35afb82ac2eafcced80ed16ea83234c0
    [contexts] => Array
        (
            [username] => admin
            [pagename] => My test page
        )
)
*/

foreach ($logRows as $oneLogRow) {
	
	$header = $this->getLogRowHeaderOutput($oneLogRow);	
	$sender_image = $this->getLogRowSenderImageOutput($oneLogRow);
	$plain_text_html = $this->getLogRowPlainTextOutput($oneLogRow);
	
	printf(
		'
		<hr>
		%3$s
		<div>%1$s</div>
		<p>%2$s</p>
		',
		$header,
		$plain_text_html,
		$sender_image
	);

	// Get the main message row.
	// Should be as plain as possible, like plain text 
	// but with links to for example users and posts
	#SimpleLoggerFormatter::getRowTextOutput($oneLogRow);

	// Get detailed HTML-based output
	// May include images, lists, any cool stuff needed to view
	#SimpleLoggerFormatter::getRowHTMLOutput($oneLogRow);

}
