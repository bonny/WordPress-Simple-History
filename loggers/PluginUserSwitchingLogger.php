<?php

defined('ABSPATH') or die();

/**
 * Logs user switching from the great User Switching plugin
 * Plugin URL: https://wordpress.org/plugins/user-switching/
 *
 * @since 2.2
 */
class PluginUserSwitchingLogger extends SimpleLogger
{

    public $slug = __CLASS__;

    /**
     * Get array with information about this logger
     *
     * @return array
     */
    function getInfo()
    {

        $arr_info = array(
            'name' => _x('User Switching Logger', 'PluginUserSwitchingLogger', 'simple-history'),
            'description' => _x('Logs user switches', 'PluginUserSwitchingLogger', 'simple-history'),
            // Definition of via: by way of, through the medium or agency of; also :  by means of
            'name_via' => _x('Using plugin User Switching', 'PluginUserSwitchingLogger', 'simple-history'),
            'capability' => 'edit_users',
            'messages' => array(
                'switched_to_user' => _x('Switched to user "{user_login_to}" from user "{user_login_from}"', 'PluginUserSwitchingLogger', 'simple-history'),
                'switched_back_user' => _x('Switched back to user "{user_login_to}" from user "{user_login_from}"', 'PluginUserSwitchingLogger', 'simple-history'),
                'switched_back_themself' => _x('Switched back to user "{user_login_to}"', 'PluginUserSwitchingLogger', 'simple-history'),
                'switched_off_user' => _x('Switched off user "{user_login}"', 'PluginUserSwitchingLogger', 'simple-history'),
            ),
        );

        return $arr_info;
    }

    function loaded()
    {

        add_action('switch_to_user', array( $this, 'on_switch_to_user' ), 10, 2);
        add_action('switch_back_user', array( $this, 'on_switch_back_user' ), 10, 2);
        add_action('switch_off_user', array( $this, 'on_switch_off_user' ), 10, 1);
    }

    function on_switch_to_user($user_id, $old_user_id)
    {

        $user_to = get_user_by('id', $user_id);
        $user_from = get_user_by('id', $old_user_id);

        if (! is_a($user_to, 'WP_User') || ! is_a($user_from, 'WP_User')) {
            return;
        }

        $this->infoMessage(
            'switched_to_user',
            array(
                // It is the old user who initiates the switching
                '_initiator' => SimpleLoggerLogInitiators::WP_USER,
                '_user_id' => $old_user_id,
                'user_id' => $user_id,
                'old_user_id' => $old_user_id,
                'user_login_to' => $user_to->user_login,
                'user_login_from' => $user_from->user_login,
            )
        );
    }

    /**
     * Function is called when a user switches back to their originating account.
     * When you switch back after being logged off the
     *
     * Note: $old_user_id parameter is boolean false because there is no old user.
     *
     * @param int       $user_id     The ID of the user being switched back to.
     * @param int|false $old_user_id The ID of the user being switched from, or false if the user is switching back
     *                               after having been switched off.
     */
    function on_switch_back_user($user_id, $old_user_id)
    {

        $user_to = get_user_by('id', $user_id);

        $user_from = $old_user_id == false ? null : get_user_by('id', $old_user_id);

        if (! is_a($user_to, 'WP_User')) {
            return;
        }

        if ($user_from) {
            // User switched back from another user
            $this->infoMessage(
                'switched_back_user',
                array(
                    '_initiator' => SimpleLoggerLogInitiators::WP_USER,
                    '_user_id' => $old_user_id,
                    'user_id' => $user_id,
                    'old_user_id' => $old_user_id,
                    'user_login_to' => $user_to->user_login,
                    'user_login_from' => $user_from->user_login,
                )
            );
        } else {
            // User switched back to themself (no prev user)
            $this->infoMessage(
                'switched_back_themself',
                array(
                    '_initiator' => SimpleLoggerLogInitiators::WP_USER,
                    '_user_id' => $user_id,
                    'user_login_to' => $user_to->user_login,
                )
            );
        }
    }

    function on_switch_off_user($user_id)
    {

        $user = get_user_by('id', $user_id);

        if (! is_a($user, 'WP_User')) {
            return;
        }

        $this->infoMessage(
            'switched_off_user',
            array(
                '_initiator' => SimpleLoggerLogInitiators::WP_USER,
                '_user_id' => $user_id,

                'user_id' => $user_id,
                'user_login' => $user->user_login,
            )
        );
    }
}
