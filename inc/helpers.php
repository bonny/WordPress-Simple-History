<?php

/**
 * Helper function with same name as the SimpleLogger-class
 *
 * Makes call like this possible:
 * SimpleLogger()->info("This is a message sent to the log");
 */
function SimpleLogger()
{
    return new SimpleLogger(SimpleHistory::get_instance());
}

/**
 * Add event to history table
 * This is here for backwards compatibility
 * If you use this please consider using
 * SimpleHistory()->info();
 * instead
 */
function simple_history_add($args)
{
    $defaults = array(
        'action' => null,
        'object_type' => null,
        'object_subtype' => null,
        'object_id' => null,
        'object_name' => null,
        'user_id' => null,
        'description' => null
    );

    $context = wp_parse_args($args, $defaults);

    $message = "{$context["object_type"]} {$context["object_name"]} {$context["action"]}";

    SimpleLogger()->info($message, $context);
} // simple_history_add

/**
 * Pretty much same as wp_text_diff() but with this you can set leading and trailing context lines
 *
 * @since 2.0.29
 *
 *
 * Original description from wp_text_diff():
 *
 * Displays a human readable HTML representation of the difference between two strings.
 *
 * The Diff is available for getting the changes between versions. The output is
 * HTML, so the primary use is for displaying the changes. If the two strings
 * are equivalent, then an empty string will be returned.
 *
 * The arguments supported and can be changed are listed below.
 *
 * 'title' : Default is an empty string. Titles the diff in a manner compatible
 *      with the output.
 * 'title_left' : Default is an empty string. Change the HTML to the left of the
 *      title.
 * 'title_right' : Default is an empty string. Change the HTML to the right of
 *      the title.
 *
 * @see wp_parse_args() Used to change defaults to user defined settings.
 * @uses Text_Diff
 * @uses WP_Text_Diff_Renderer_Table
 *
 * @param string       $left_string "old" (left) version of string
 * @param string       $right_string "new" (right) version of string
 * @param string|array $args Optional. Change 'title', 'title_left', and 'title_right' defaults. And leading_context_lines and trailing_context_lines.
 * @return string Empty string if strings are equivalent or HTML with differences.
 */
function simple_history_text_diff($left_string, $right_string, $args = null)
{
    $defaults = array(
        'title' => '',
        'title_left' => '',
        'title_right' => '',
        'leading_context_lines' => 1,
        'trailing_context_lines' => 1
    );

    $args = wp_parse_args($args, $defaults);

    if (!class_exists('WP_Text_Diff_Renderer_Table')) {
        require ABSPATH . WPINC . '/wp-diff.php';
    }

    $left_string = normalize_whitespace($left_string);
    $right_string = normalize_whitespace($right_string);

    $left_lines = explode("\n", $left_string);
    $right_lines = explode("\n", $right_string);
    $text_diff = new Text_Diff($left_lines, $right_lines);

    $renderer = new WP_Text_Diff_Renderer_Table($args);
    $renderer->_leading_context_lines = $args['leading_context_lines'];
    $renderer->_trailing_context_lines = $args['trailing_context_lines'];

    $diff = $renderer->render($text_diff);

    if (!$diff) {
        return '';
    }

    $r = '';

    $r .= "<div class='SimpleHistory__diff__contents' tabindex='0'>";
    $r .= "<div class='SimpleHistory__diff__contentsInner'>";

    $r .= "<table class='diff SimpleHistory__diff'>\n";

    if (!empty($args['show_split_view'])) {
        $r .=
            "<col class='content diffsplit left' /><col class='content diffsplit middle' /><col class='content diffsplit right' />";
    } else {
        $r .= "<col class='content' />";
    }

    if ($args['title'] || $args['title_left'] || $args['title_right']) {
        $r .= '<thead>';
    }
    if ($args['title']) {
        $r .= "<tr class='diff-title'><th colspan='4'>$args[title]</th></tr>\n";
    }
    if ($args['title_left'] || $args['title_right']) {
        $r .= "<tr class='diff-sub-title'>\n";
        $r .= "\t<td></td><th>$args[title_left]</th>\n";
        $r .= "\t<td></td><th>$args[title_right]</th>\n";
        $r .= "</tr>\n";
    }
    if ($args['title'] || $args['title_left'] || $args['title_right']) {
        $r .= "</thead>\n";
    }

    $r .= "<tbody>\n$diff</div>\n</tbody>\n";
    $r .= '</table>';

    $r .= '</div>';
    $r .= '</div>';

    return $r;
}

/**
 * Log variable(s) to error log.
 * Any number of variables can be passed and each variable is print_r'ed to the error log.
 *
 * Example usage:
 * sh_error_log(
 *   'rest_request_after_callbacks:',
 *   $handler,
 *   $handler['callback'][0],
 *   $handler['callback'][1]
 * );
 */
function sh_error_log()
{
    foreach (func_get_args() as $var) {
        if (is_bool($var)) {
            $bool_string = true === $var ? 'true' : 'false';
            error_log("$bool_string (boolean value)");
        } elseif (is_null($var)) {
            error_log('null (null value)');
        } else {
            error_log(print_r($var, true));
        }
    }
}

/**
 * Return a name for a callable.
 *
 * Examples of return values:
 * - WP_REST_Posts_Controller::get_items
 * - WP_REST_Users_Controller::get_items"
 * - WP_REST_Server::get_index
 * - Redirection_Api_Redirect::route_bulk
 * - wpcf7_rest_create_feedback
 * - closure
 *
 * Function based on code found on stack overflow:
 * https://stackoverflow.com/questions/34324576/print-name-or-definition-of-callable-in-php
 *
 * @param callable $callable The callable thing to check.
 * @return string Name of callable.
 */
function sh_get_callable_name($callable)
{
    if (is_string($callable)) {
        return trim($callable);
    } elseif (is_array($callable)) {
        if (is_object($callable[0])) {
            return sprintf('%s::%s', get_class($callable[0]), trim($callable[1]));
        } else {
            return sprintf('%s::%s', trim($callable[0]), trim($callable[1]));
        }
    } elseif ($callable instanceof Closure) {
        return 'closure';
    } else {
        return 'unknown';
    }
}

/**
 * PHP 5.3 compatible version of ucwords with second argument.
 * Taken from http://php.net/manual/en/function.ucwords.php#105249.
 *
 * @param string $str String.
 * @param string $separator String.
 *
 * @return string with words uppercased.
 */
function sh_ucwords($str, $separator = ' ')
{
    $str = str_replace($separator, ' ', $str);
    $str = ucwords(strtolower($str));
    $str = str_replace(' ', $separator, $str);
    return $str;
}
