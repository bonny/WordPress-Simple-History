<style>

	.simple-history-logitems {
		background: #fff;
		border: 1px solid rgb(229, 229, 229);
	}

	.simple-history-logitem {
		margin: 0;
		padding: 1em 1em;
		border-bottom: 1px solid rgb(229, 229, 229);
	}

	.simple-history-logitem:hover {
		background: rgb(245, 248, 250); /* same bg color as twitter uses on hover */
	}

	/*
	.simple-history-logitem:nth-child(odd) {
		background: rgb(249, 249, 249);
	}
	*/

	.simple-history-logitem__firstcol {
		float: left;
	}

	.simple-history-logitem__senderImage {
		-webkit-border-radius: 5px;
		-moz-border-radius: 5px;
		border-radius: 5px;
		overflow: hidden;
	}
	.simple-history-logitem__senderImage img {
		display: block;
	}

	.simple-history-logitem__secondcol {
		margin-left: 42px;
	}

	.simple-history-logitem__header {
		line-height: 1;
	}

	.simple-history-logitem__header time {
		color: rgb(137, 143, 156);
	}

	.simple-history-logitem__text {
		/*font-size: 1.5em;*/
	}

</style>
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

echo "<ul class='simple-history-logitems'>";
foreach ($logRows as $oneLogRow) {
	
	$header_html = $this->getLogRowHeaderOutput($oneLogRow);	
	$plain_text_html = $this->getLogRowPlainTextOutput($oneLogRow);
	$sender_image_html = $this->getLogRowSenderImageOutput($oneLogRow);
	
	printf(
		'
			<li class="simple-history-logitem">
				<div class="simple-history-logitem__firstcol">
					<div class="simple-history-logitem__senderImage">%3$s</div>
				</div>
				<div class="simple-history-logitem__secondcol">
					<div class="simple-history-logitem__header">%1$s</div>
					<div class="simple-history-logitem__text">%2$s</div>
				</div>
			</li>
		',
		$header_html, // 1
		$plain_text_html, // 2
		$sender_image_html // 3
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
