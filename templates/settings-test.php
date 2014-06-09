<?php

$logQuery = new SimpleHistoryLogQuery();
$logRows = $logQuery->query(array(
	"posts_per_page" => 100
));

/*
Each row looks like:
stdClass Object
(
    [id] => 6004
    [logger] => SimpleLogger
    [message] => User {username} edited page {pagename}
    [occasionsID] => 35afb82ac2eafcced80ed16ea83234c0
    [subsequentOccasions] => 5286
    [date] => 2014-06-01 18:08:57
    [rep] => 1
    [repeated] => 1
    [type] => 35afb82ac2eafcced80ed16ea83234c0
    [context] => Array
        (
            [username] => admin
            [pagename] => My test page
        )
)
*/

echo "<ul class='simple-history-logitems'>";
foreach ($logRows as $oneLogRow) {
	
	$header_html = $this->getLogRowHeaderOutput($oneLogRow);	
	$plain_text_html = $this->getLogRowPlainTextOutput($oneLogRow);
	$sender_image_html = $this->getLogRowSenderImageOutput($oneLogRow);
	
	$details_html = trim( $this->getLogRowDetailsOutput($oneLogRow) );

	if ($details_html) {

		$details_html = sprintf(
			'<div class="simple-history-logitem__details">%1$s</div>',
			$details_html
		);

	}

	// subsequentOccasions = including the current one
	$occasions_count = $oneLogRow->subsequentOccasions - 1;
	$occasions_html = "";
	if ($occasions_count > 0) {
		$occasions_html = sprintf(
			'
			<div class="simple-history-logitem__occasions">
				%1$s more occasions
			</div>
			',
			$occasions_count
		);
	}

	printf(
		'
			<li class="simple-history-logitem simple-history-logitem--loglevel-%5$s simple-history-logitem--logger-%7$s">
				<div class="simple-history-logitem__firstcol">
					<div class="simple-history-logitem__senderImage">%3$s</div>
				</div>
				<div class="simple-history-logitem__secondcol">
					<div class="simple-history-logitem__header">%1$s</div>
					<div class="simple-history-logitem__text">%2$s</div>
					%4$s
					%6$s
				</div>
			</li>
		',
		$header_html, // 1
		$plain_text_html, // 2
		$sender_image_html, // 3
		$occasions_html, // 4
		$oneLogRow->level, // 5
		$details_html, // 6
		$oneLogRow->logger // 7
	);

	// Get the main message row.
	// Should be as plain as possible, like plain text 
	// but with links to for example users and posts
	#SimpleLoggerFormatter::getRowTextOutput($oneLogRow);

	// Get detailed HTML-based output
	// May include images, lists, any cool stuff needed to view
	#SimpleLoggerFormatter::getRowHTMLOutput($oneLogRow);

}
echo "</ul>";
