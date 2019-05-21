<?php

defined('ABSPATH') or die();

/**
 * Logs things related to comments
 */
class SimpleCommentsLogger extends SimpleLogger
{


    public $slug = __CLASS__;

    function __construct($sh)
    {

        parent::__construct($sh);

        // Add option to not show spam comments, because to much things getting logged
        // add_filter("simple_history/log_query_sql_where", array($this, "maybe_modify_log_query_sql_where"));
        add_filter('simple_history/log_query_inner_where', array( $this, 'maybe_modify_log_query_sql_where' ));
        add_filter('simple_history/quick_stats_where', array( $this, 'maybe_modify_log_query_sql_where' ));
    }

    /**
     * Modify sql query to exclude comments of type spam
     *
     * @param string $where sql query where
     */
    function maybe_modify_log_query_sql_where($where)
    {

        // since 19 sept 2016 we do include spam, to skip the subquery
        // spam comments should not be logged anyway since some time
        $include_spam = true;

        /**
         * Filter option to include spam or not in the gui
         * By default spam is not included, because it can fill the log
         * with too much events
         *
         * @since 2.0
         *
         * @param bool $include_spam Default false
         */
        $include_spam = apply_filters('simple_history/comments_logger/include_spam', $include_spam);

        if ($include_spam) {
            return $where;
        }

        $where .= sprintf('
			AND id NOT IN (

				SELECT id
					# , c1.history_id, c2.history_id
				FROM %1$s AS h

				INNER JOIN %2$s AS c1
					ON c1.history_id = h.id
					AND c1.key = "_message_key"
					AND c1.value IN (
						"comment_deleted",
						"pingback_deleted",
						"trackback_deleted",
						"anon_comment_added",
						"anon_pingback_added",
						"anon_trackback_added"
					)

				INNER JOIN %2$s AS c2
					ON c2.history_id = h.id
					AND c2.key = "comment_approved"
					AND c2.value = "spam"

				WHERE logger = "%3$s"

			)
		', $this->db_table, $this->db_table_contexts, $this->slug);

        // echo $where;
        return $where;
    }

    /**
     * Get array with information about this logger
     *
     * @return array
     */
    function getInfo()
    {

        $arr_info = array(
            'name' => 'Comments Logger',
            'description' => 'Logs comments, and modifications to them',
            'capability' => 'moderate_comments',
            'messages' => array(

                // Comments
                'anon_comment_added' => _x(
                    'Added a comment to {comment_post_type} "{comment_post_title}"',
                    'A comment was added to the database by a non-logged in internet user',
                    'simple-history'
                ),

                'user_comment_added' => _x(
                    'Added a comment to {comment_post_type} "{comment_post_title}"',
                    'A comment was added to the database by a logged in user',
                    'simple-history'
                ),

                'comment_status_approve' => _x(
                    'Approved a comment to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A comment was approved',
                    'simple-history'
                ),

                'comment_status_hold' => _x(
                    'Unapproved a comment to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A comment was was unapproved',
                    'simple-history'
                ),

                'comment_status_spam' => _x(
                    'Marked a comment to post "{comment_post_title}" as spam',
                    'A comment was marked as spam',
                    'simple-history'
                ),

                'comment_status_trash' => _x(
                    'Trashed a comment to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A comment was marked moved to the trash',
                    'simple-history'
                ),

                'comment_untrashed' => _x(
                    'Restored a comment to "{comment_post_title}" by {comment_author} ({comment_author_email}) from the trash',
                    'A comment was restored from the trash',
                    'simple-history'
                ),

                'comment_deleted' => _x(
                    'Deleted a comment to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A comment was deleted',
                    'simple-history'
                ),

                'comment_edited' => _x(
                    'Edited a comment to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A comment was edited',
                    'simple-history'
                ),

                // Trackbacks
                'anon_trackback_added' => _x(
                    'Added a trackback to {comment_post_type} "{comment_post_title}"',
                    'A trackback was added to the database by a non-logged in internet user',
                    'simple-history'
                ),

                'user_trackback_added' => _x(
                    'Added a trackback to {comment_post_type} "{comment_post_title}"',
                    'A trackback was added to the database by a logged in user',
                    'simple-history'
                ),

                'trackback_status_approve' => _x(
                    'Approved a trackback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A trackback was approved',
                    'simple-history'
                ),

                'trackback_status_hold' => _x(
                    'Unapproved a trackback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A trackback was was unapproved',
                    'simple-history'
                ),

                'trackback_status_spam' => _x(
                    'Marked a trackback to post "{comment_post_title}" as spam',
                    'A trackback was marked as spam',
                    'simple-history'
                ),

                'trackback_status_trash' => _x(
                    'Trashed a trackback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A trackback was marked moved to the trash',
                    'simple-history'
                ),

                'trackback_untrashed' => _x(
                    'Restored a trackback to "{comment_post_title}" by {comment_author} ({comment_author_email}) from the trash',
                    'A trackback was restored from the trash',
                    'simple-history'
                ),

                'trackback_deleted' => _x(
                    'Deleted a trackback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A trackback was deleted',
                    'simple-history'
                ),

                'trackback_edited' => _x(
                    'Edited a trackback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A trackback was edited',
                    'simple-history'
                ),

                // Pingbacks
                'anon_pingback_added' => _x(
                    'Added a pingback to {comment_post_type} "{comment_post_title}"',
                    'A trackback was added to the database by a non-logged in internet user',
                    'simple-history'
                ),

                'user_pingback_added' => _x(
                    'Added a pingback to {comment_post_type} "{comment_post_title}"',
                    'A pingback was added to the database by a logged in user',
                    'simple-history'
                ),

                'pingback_status_approve' => _x(
                    'Approved a pingback to "{comment_post_title}" by "{comment_author}"" ({comment_author_email})',
                    'A pingback was approved',
                    'simple-history'
                ),

                'pingback_status_hold' => _x(
                    'Unapproved a pingback to "{comment_post_title}" by "{comment_author}" ({comment_author_email})',
                    'A pingback was was unapproved',
                    'simple-history'
                ),

                'pingback_status_spam' => _x(
                    'Marked a pingback to post "{comment_post_title}" as spam',
                    'A pingback was marked as spam',
                    'simple-history'
                ),

                'pingback_status_trash' => _x(
                    'Trashed a pingback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A pingback was marked moved to the trash',
                    'simple-history'
                ),

                'pingback_untrashed' => _x(
                    'Restored a pingback to "{comment_post_title}" by {comment_author} ({comment_author_email}) from the trash',
                    'A pingback was restored from the trash',
                    'simple-history'
                ),

                'pingback_deleted' => _x(
                    'Deleted a pingback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A pingback was deleted',
                    'simple-history'
                ),

                'pingback_edited' => _x(
                    'Edited a pingback to "{comment_post_title}" by {comment_author} ({comment_author_email})',
                    'A pingback was edited',
                    'simple-history'
                ),

            ), // end messages

            'labels' => array(

                'search' => array(
                    'label' => _x('Comments', 'Comments logger: search', 'simple-history'),
                    'label_all' => _x('All comments activity', 'Comments logger: search', 'simple-history'),
                    'options' => array(
                        _x('Added comments', 'Comments logger: search', 'simple-history') => array(
                            'anon_comment_added',
                            'user_comment_added',
                            'anon_trackback_added',
                            'user_trackback_added',
                            'anon_pingback_added',
                            'user_pingback_added',
                        ),
                        _x('Edited comments', 'Comments logger: search', 'simple-history') => array(
                            'comment_edited',
                            'trackback_edited',
                            'pingback_edited',
                        ),
                        _x('Approved comments', 'Comments logger: search', 'simple-history') => array(
                            'comment_status_approve',
                            'trackback_status_approve',
                            'pingback_status_approve',
                        ),
                        _x('Held comments', 'Comments logger: search', 'simple-history') => array(
                            'comment_status_hold',
                            'trackback_status_hold',
                            'pingback_status_hold',
                        ),
                        _x('Comments status changed to spam', 'Comments logger: search', 'simple-history') => array(
                            'comment_status_spam',
                            'trackback_status_spam',
                            'pingback_status_spam',
                        ),
                        _x('Trashed comments', 'Comments logger: search', 'simple-history') => array(
                            'comment_status_trash',
                            'trackback_status_trash',
                            'pingback_status_trash',
                        ),
                        _x('Untrashed comments', 'Comments logger: search', 'simple-history') => array(
                            'comment_untrashed',
                            'trackback_untrashed',
                            'pingback_untrashed',
                        ),
                        _x('Deleted comments', 'Comments logger: search', 'simple-history') => array(
                            'comment_deleted',
                            'trackback_deleted',
                            'pingback_deleted',
                        ),
                    ),
                ),// end search

            ),// labels

        );

        return $arr_info;
    }

    public function loaded()
    {

        /**
         * Fires immediately after a comment is inserted into the database.
         */
        add_action('comment_post', array( $this, 'on_comment_post' ), 10, 2);

        /**
         * Fires after a comment status has been updated in the database.
         * The hook also fires immediately before comment status transition hooks are fired.
         */
        add_action('wp_set_comment_status', array( $this, 'on_wp_set_comment_status' ), 10, 2);

        /**
         *Fires immediately after a comment is restored from the Trash.
         */
        add_action('untrashed_comment', array( $this, 'on_untrashed_comment' ), 10, 1);

         /**
          * Fires immediately before a comment is deleted from the database.
          */
        add_action('delete_comment', array( $this, 'on_delete_comment' ), 10, 1);

        /**
         * Fires immediately after a comment is updated in the database.
          * The hook also fires immediately before comment status transition hooks are fired.
          */
        add_action('edit_comment', array( $this, 'on_edit_comment' ), 10, 1);
    }

    /**
     * Get comments context
     *
     * @param int $comment_ID
     * @return mixed array with context if comment found, false if comment not found
     */
    public function get_context_for_comment($comment_ID)
    {

        // get_comment passes comment_ID by reference, so it can be unset by that function
        $comment_ID_original = $comment_ID;
        $comment_data = get_comment($comment_ID);

        if (is_null($comment_data)) {
            return false;
        }

        $comment_parent_post = get_post($comment_data->comment_post_ID);

        $context = array(
            'comment_ID' => $comment_ID_original,
            'comment_author' => $comment_data->comment_author,
            'comment_author_email' => $comment_data->comment_author_email,
            'comment_author_url' => $comment_data->comment_author_url,
            'comment_author_IP' => $comment_data->comment_author_IP,
            'comment_content' => $comment_data->comment_content,
            'comment_approved' => $comment_data->comment_approved,
            'comment_agent' => $comment_data->comment_agent,
            'comment_type' => $comment_data->comment_type,
            'comment_parent' => $comment_data->comment_parent,
            'comment_post_ID' => $comment_data->comment_post_ID,
            'comment_post_title' => $comment_parent_post->post_title,
            'comment_post_type' => $comment_parent_post->post_type,
        );

        // Note: comment type is empty for normal comments
        if (empty($context['comment_type'])) {
            $context['comment_type'] = 'comment';
        }

        return $context;
    }

    public function on_edit_comment($comment_ID)
    {

        $context = $this->get_context_for_comment($comment_ID);
        if (! $context) {
            return;
        }

        $this->infoMessage(
            "{$context["comment_type"]}_edited",
            $context
        );
    }

    public function on_delete_comment($comment_ID)
    {

        $context = $this->get_context_for_comment($comment_ID);

        if (! $context) {
            return;
        }

        $comment_data = get_comment($comment_ID);

        // add occasions if comment was considered spam
        // if not added, spam comments can easily flood the log
        // Deletions of spam easiy flood log
        if (isset($comment_data->comment_approved) && 'spam' === $comment_data->comment_approved) {
            // since 2.5.5: don't log deletion of spam comments
            return;
            // $context["_occasionsID"] = __CLASS__  . '/' . __FUNCTION__ . "/anon_{$context["comment_type"]}_deleted/type:spam";
        }

        $this->infoMessage(
            "{$context["comment_type"]}_deleted",
            $context
        );
    }

    public function on_untrashed_comment($comment_ID)
    {

        $context = $this->get_context_for_comment($comment_ID);
        if (! $context) {
            return;
        }

        $this->infoMessage(
            "{$context["comment_type"]}_untrashed",
            $context
        );
    }

    /**
     * Fires after a comment status has been updated in the database.
     * The hook also fires immediately before comment status transition hooks are fired.
     *
     * @param int         $comment_id     The comment ID.
     * @param string|bool $comment_status The comment status. Possible values include 'hold',
     *                                    'approve', 'spam', 'trash', or false.
     * do_action( 'wp_set_comment_status', $comment_id, $comment_status );
     */
    public function on_wp_set_comment_status($comment_ID, $comment_status)
    {

        $context = $this->get_context_for_comment($comment_ID);

        if (! $context) {
            return;
        }

        /*
        $comment_status:
            approve
                comment was approved
            spam
                comment was marked as spam
            trash
                comment was trashed
            hold
                comment was un-approved
        */
        $message = "{$context["comment_type"]}_status_{$comment_status}";

        $this->infoMessage(
            $message,
            $context
        );
    }

    /**
     * Fires immediately after a comment is inserted into the database.
     */
    public function on_comment_post($comment_ID, $comment_approved)
    {

        $context = $this->get_context_for_comment($comment_ID);

        if (! $context) {
            return;
        }

        // since 2.5.5: no more logging of spam comments
        if (isset($comment_approved) && 'spam' === $comment_approved) {
            return;
        }

        $comment_data = get_comment($comment_ID);

        $message = '';

        if ($comment_data->user_id) {
            // comment was from a logged in user
            $message = "user_{$context["comment_type"]}_added";
        } else {
            // comment was from a non-logged in user
            $message = "anon_{$context["comment_type"]}_added";
            $context['_initiator'] = SimpleLoggerLogInitiators::WEB_USER;

            // add occasions if comment is considered spam
            // if not added, spam comments can easily flood the log
            if (isset($comment_data->comment_approved) && 'spam' === $comment_data->comment_approved) {
                $context['_occasionsID'] = __CLASS__ . '/' . __FUNCTION__ . "/anon_{$context["comment_type"]}_added/type:spam";
            }
        }

        $this->infoMessage(
            $message,
            $context
        );
    }


    /**
     * Modify plain output to inlcude link to post
     * and link to comment
     */
    public function getLogRowPlainTextOutput($row)
    {

        $message = $row->message;
        $context = $row->context;
        $message_key = $context['_message_key'];

        // Message is untranslated here, so get translated text
        // Can't call parent __FUNCTION__ because it will interpolate too, which we don't want
        if (! empty($message_key)) {
            $message = $this->messages[ $message_key ]['translated_text'];
        }

        // Wrap links around {comment_post_title}
        $comment_post_ID = isset($context['comment_post_ID']) ? (int) $context['comment_post_ID'] : null;
        if ($comment_post_ID && $comment_post = get_post($comment_post_ID)) {
            $edit_post_link = get_edit_post_link($comment_post_ID);

            if ($edit_post_link) {
                $message = str_replace(
                    '"{comment_post_title}"',
                    "<a href='{$edit_post_link}'>\"{comment_post_title}\"</a>",
                    $message
                );
            }
        }

        return $this->interpolate($message, $context, $row);
    }


    /**
     * Get output for detailed log section
     */
    function getLogRowDetailsOutput($row)
    {

        $context = $row->context;
        $message_key = $context['_message_key'];
        $output = '';
        // print_r($row);exit;
        /*
        if ( 'spam' !== $commentdata['comment_approved'] ) { // If it's spam save it silently for later crunching
                if ( '0' == $commentdata['comment_approved'] ) { // comment not spam, but not auto-approved
                    wp_notify_moderator( $comment_ID );
        */
        /*
        if ( isset( $context["comment_approved"] ) && $context["comment_approved"] == '0' ) {
            $output .= "<br>comment was automatically approved";
        } else {
            $output .= "<br>comment was not automatically approved";
        }*/

        $comment_text = '';
        if (isset($context['comment_content']) && $context['comment_content']) {
            $comment_text = $context['comment_content'];
            $comment_text = wp_trim_words($comment_text, 20);
            $comment_text = wpautop($comment_text);
        }

        // Keys to show
        $arr_plugin_keys = array();
        $comment_type = isset($context['comment_type']) ? $context['comment_type'] : '';

        switch ($comment_type) {
            case 'trackback';

                $arr_plugin_keys = array(
                    'trackback_status' => _x('Status', 'comments logger - detailed output comment status', 'simple-history'),
                    // "trackback_type" => _x("Trackback type", "comments logger - detailed output comment type", "simple-history"),
                    'trackback_author' => _x('Name', 'comments logger - detailed output author', 'simple-history'),
                    'trackback_author_email' => _x('Email', 'comments logger - detailed output email', 'simple-history'),
                    'trackback_content' => _x('Content', 'comments logger - detailed output content', 'simple-history'),
                );

                break;

            case 'pingback';

                $arr_plugin_keys = array(

                    'pingback_status' => _x('Status', 'comments logger - detailed output comment status', 'simple-history'),
                    // "pingback_type" => _x("Pingback type", "comments logger - detailed output comment type", "simple-history"),
                    'pingback_author' => _x('Name', 'comments logger - detailed output author', 'simple-history'),
                    'pingback_author_email' => _x('Email', 'comments logger - detailed output email', 'simple-history'),
                    'pingback_content' => _x('Content', 'comments logger - detailed output content', 'simple-history'),

                );

                break;

            case 'comment';
            default;

                $arr_plugin_keys = array(
                    'comment_status' => _x('Status', 'comments logger - detailed output comment status', 'simple-history'),
                    // "comment_type" => _x("Comment type", "comments logger - detailed output comment type", "simple-history"),
                    'comment_author' => _x('Name', 'comments logger - detailed output author', 'simple-history'),
                    'comment_author_email' => _x('Email', 'comments logger - detailed output email', 'simple-history'),
                    'comment_content' => _x('Comment', 'comments logger - detailed output content', 'simple-history'),
                );

                break;

                // "comment_author_url" => _x("Author URL", "comments logger - detailed output author", "simple-history"),
                // "comment_author_IP" => _x("IP number", "comments logger - detailed output IP", "simple-history"),
        }// End switch().

        $arr_plugin_keys = apply_filters('simple_history/comments_logger/row_details_plugin_info_keys', $arr_plugin_keys);

        // Start output of plugin meta data table
        $output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

        foreach ($arr_plugin_keys as $key => $desc) {
            switch ($key) {
                case 'comment_content':
                case 'trackback_content':
                case 'pingback_content':
                    $desc_output = $comment_text;
                    break;

                case 'comment_author':
                case 'trackback_author':
                case 'pingback_author':
                    $desc_output = '';

                    if (isset($context[ $key ])) {
                        $desc_output .= esc_html($context[ $key ]);
                    }

                    /*
                    if ( isset( $context["comment_author_email"] ) ) {

                        $gravatar_email = $context["comment_author_email"];
                        $avatar = $this->simpleHistory->get_avatar( $gravatar_email, 14, "blank" );
                        $desc_output .= "<span class='SimpleCommentsLogger__gravatar'>{$avatar}</span>";

                    }
                    */

                    break;

                case 'comment_status':
                case 'trackback_status':
                case 'pingback_status':
                    if (isset($context['comment_approved'])) {
                        if ($context['comment_approved'] === 'spam') {
                            $desc_output = __('Spam', 'simple-history');
                        } elseif ($context['comment_approved'] == 1) {
                            $desc_output = __('Approved', 'simple-history');
                        } elseif ($context['comment_approved'] == 0) {
                            $desc_output = __('Pending', 'simple-history');
                        }
                    }

                    break;

                case 'comment_type':
                case 'trackback_type':
                case 'pingback_type':
                    if (isset($context['comment_type'])) {
                        if ($context['comment_type'] === 'trackback') {
                            $desc_output = __('Trackback', 'simple-history');
                        } elseif ($context['comment_type'] === 'pingback') {
                            $desc_output = __('Pingback', 'simple-history');
                        } elseif ($context['comment_type'] === 'comment') {
                            $desc_output = __('Comment', 'simple-history');
                        } else {
                            $desc_output = '';
                        }
                    }

                    break;

                default;

                    if (isset($context[ $key ])) {
                        $desc_output = esc_html($context[ $key ]);
                    }

                    break;
            }// End switch().

            // Skip empty rows
            if (empty($desc_output)) {
                continue;
            }

            $output .= sprintf(
                '
				<tr>
					<td>%1$s</td>
					<td>%2$s</td>
				</tr>
				',
                esc_html($desc),
                $desc_output
            );
        }// End foreach().

        // Add link to edit comment
        $comment_ID = isset($context['comment_ID']) && is_numeric($context['comment_ID']) ? (int) $context['comment_ID'] : false;

        if ($comment_ID) {
            $comment = get_comment($comment_ID);

            if ($comment) {
                // http://site.local/wp/wp-admin/comment.php?action=editcomment&c=
                $edit_comment_link = get_edit_comment_link($comment_ID);

                // Edit link sometimes does not contain comment ID
                // Probably because comment has been removed or something
                // So only continue if link does not end with "=""
                if ($edit_comment_link && $edit_comment_link[ strlen($edit_comment_link) -1 ] !== '=') {
                    $output .= sprintf(
                        '
						<tr>
							<td></td>
							<td><a href="%2$s">%1$s</a></td>
						</tr>
						',
                        _x('View/Edit', 'comments logger - edit comment', 'simple-history'),
                        $edit_comment_link
                    );
                }
            }
        } // End if().

        // End table
        $output .= '</table>';

        return $output;
    }

    function adminCSS()
    {
        ?>
        <style>
            .SimpleCommentsLogger__gravatar {
                line-height: 1;
                border-radius: 50%;
                overflow: hidden;
                margin-right: .5em;
                margin-left: .5em;
                display: inline-block;
            }
            .SimpleCommentsLogger__gravatar img {
                display: block;
            }
        </style>
        <?php
    }
}
