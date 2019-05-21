<?php

defined('ABSPATH') or die();

/**
 * Logs WordPress menu edits
 */
class SimpleMenuLogger extends SimpleLogger
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
            'name' => 'Menu Logger',
            'description' => 'Logs menu edits',
            'capability' => 'edit_theme_options',
            'messages' => array(
                'created_menu' => __('Created menu "{menu_name}"', 'simple-history'),
                'edited_menu' => __('Edited menu "{menu_name}"', 'simple-history'),
                'deleted_menu' => __('Deleted menu "{menu_name}"', 'simple-history'),
                'edited_menu_item' => __('Edited a menu item', 'simple-history'),
                'edited_menu_locations' => __('Updated menu locations', 'simple-history'),
            ),
            'labels' => array(
                'search' => array(
                    'label' => _x('Menus', 'Menu logger: search', 'simple-history'),
                    'label_all' => _x('All menu activity', 'Menu updates logger: search', 'simple-history'),
                    'options' => array(
                        _x('Created menus', 'Menu updates logger: search', 'simple-history') => array(
                            'created_menu'
                        ),
                        _x('Edited menus', 'Menu updates logger: search', 'simple-history') => array(
                            'edited_menu',
                            'edited_menu_item',
                            'edited_menu_locations',
                        ),
                        _x('Deleted menus', 'Menu updates logger: search', 'simple-history') => array(
                            'deleted_menu'
                        ),
                    ),
                ),// end search array
            ),// end labels
        );

        return $arr_info;
    }

    function loaded()
    {

        /*
         * Fires after a navigation menu has been successfully deleted.
         *
         * @since 3.0.0
         *
         * @param int $term_id ID of the deleted menu.
        do_action( 'wp_delete_nav_menu', $menu->term_id );
        */
        // add_action("wp_delete_nav_menu", array($this, "on_wp_delete_nav_menu"), 10, 1 );
        add_action('load-nav-menus.php', array( $this, 'on_load_nav_menus_page_detect_delete' ));

        /*
         * Fires after a navigation menu is successfully created.
         *
         * @since 3.0.0
         *
         * @param int   $term_id   ID of the new menu.
         * @param array $menu_data An array of menu data.
        do_action( 'wp_create_nav_menu', $_menu['term_id'], $menu_data );
        */
        add_action('wp_create_nav_menu', array( $this, 'on_wp_create_nav_menu' ), 10, 2);

        /*
         * Fires after a navigation menu item has been updated.
         *
         * @since 3.0.0
         *
         * @see wp_update_nav_menu_items()
         *
         * @param int   $menu_id         ID of the updated menu.
         * @param int   $menu_item_db_id ID of the updated menu item.
         * @param array $args            An array of arguments used to update a menu item.
        do_action( 'wp_update_nav_menu_item', $menu_id, $menu_item_db_id, $args );
        */

        // This is fired when adding nav items in the editor, not at save, so not
        // good to log because user might not end up saving the changes
        // add_action("wp_update_nav_menu_item", array($this, "on_wp_update_nav_menu_item"), 10, 3 );
        // Fired before "wp_update_nav_menu" below, to remember menu layput before it's updated
        // so we can't detect changes
        add_action('load-nav-menus.php', array( $this, 'on_load_nav_menus_page_detect_update' ));

        /*
         * Fires after a navigation menu has been successfully updated.
         *
         * @since 3.0.0
         *
         * @param int   $menu_id   ID of the updated menu.
         * @param array $menu_data An array of menu data.
        do_action( 'wp_update_nav_menu', $menu_id, $menu_data );
        */
        // add_action("wp_update_nav_menu", array($this, "on_wp_update_nav_menu"), 10, 2 );
        // Detect meny location change in "manage locations"
        add_action('load-nav-menus.php', array( $this, 'on_load_nav_menus_page_detect_locations_update' ));
    }

    /**
     * Can't use action "wp_delete_nav_menu" beacuse
     * it's fired after menu is deleted, so we don't have the name in this action
     */
    function on_load_nav_menus_page_detect_delete()
    {

        /*
        http://playground-root.ep/wp-admin/nav-menus.php?menu=22&action=delete&0=http%3A%2F%2Fplayground-root.ep%2Fwp-admin%2F&_wpnonce=f52e8a31ba
        $_REQUEST:
        Array
        (
            [menu] => 22
            [action] => delete
            [0] => http://playground-root.ep/wp-admin/
            [_wpnonce] => f52e8a31ba
        )
        */

        // Check that needed vars are set
        if (! isset($_REQUEST['menu'], $_REQUEST['action'])) {
            return;
        }

        if ('delete' !== $_REQUEST['action']) {
            return;
        }

        $menu_id = $_REQUEST['menu'];
        if (! is_nav_menu($menu_id)) {
            return;
        }

        $menu = wp_get_nav_menu_object($menu_id);

        $this->infoMessage(
            'deleted_menu',
            array(
                'menu_term_id' => $menu_id,
                'menu_name' => $menu->name,
            )
        );
    }

    /**
     * Fired after menu is deleted, so we don't have the name in this action
     * So that's why we can't use this only
     */
    /*
    function on_wp_delete_nav_menu($menu_term_id) {

        $this->infoMessage(
            "deleted_menu",
            array(
                "menu_term_id" => $menu_term_id,
                "menu" => print_r($menu, true),
                "request" => print_r($_REQUEST, true),
            )
        );

    }
    */

    function on_wp_create_nav_menu($term_id, $menu_data)
    {

        $menu = wp_get_nav_menu_object($term_id);

        if (! $menu) {
            return;
        }

        $this->infoMessage(
            'created_menu',
            array(
                'term_id' => $term_id,
                'menu_name' => $menu->name,
            )
        );
    }

    /*
    function on_wp_update_nav_menu_item($menu_id, $menu_item_db_id, $args) {

        $this->infoMessage(
            "edited_menu_item",
            array(
                "menu_id" => $menu_id,
                "menu_item_db_id" => $menu_item_db_id,
                "args" => $this->simpleHistory->json_encode($args),
                "request" => $this->simpleHistory->json_encode($_REQUEST)
            )
        );

    }
    */

    /**
     * Detect menu being saved
     */
    function on_load_nav_menus_page_detect_update()
    {

        /*
        This is the data to be saved
        $_REQUEST:
        Array
        (
            [action] => update
            [menu] => 25
            [menu-name] => Main menu edit
            [menu-item-title] => Array
                (
                    [25243] => My new page edited
                    [25244] => My new page
                    [25245] => This is my new page. How does it look in the logs? <h1>Hej!</h1>
                    [25264] => This page have revisions
                    [25265] => Lorem ipsum dolor sit amet
                )
            [menu-locations] => Array
                (
                    [primary] => 25
                )
        )
        */

        // Check that needed vars are set
        if (! isset($_REQUEST['menu'], $_REQUEST['action'], $_REQUEST['menu-name'])) {
            return;
        }

        // Only go on for update action
        if ('update' !== $_REQUEST['action']) {
            return;
        }

        // Make sure we got the id of a menu
        $menu_id = $_REQUEST['menu'];
        if (! is_nav_menu($menu_id)) {
            return;
        }

        // Get saved menu. May be empty if this is the first time we save the menu
        $arr_prev_menu_items = wp_get_nav_menu_items($menu_id);

        // Compare new items to be saved with old version
        $old_ids = wp_list_pluck($arr_prev_menu_items, 'db_id');
        $new_ids = array_values(isset($_POST['menu-item-db-id']) ? $_POST['menu-item-db-id'] : array());

        // Get ids of added and removed post ids
        $arr_removed = array_diff($old_ids, $new_ids);
        $arr_added = array_diff($new_ids, $old_ids);

        // Get old version location
        // $prev_menu = wp_get_nav_menu_object( $menu_id );
        // $locations = get_registered_nav_menus();
        // $menu_locations = get_nav_menu_locations();
        $this->infoMessage(
            'edited_menu',
            array(
                'menu_id' => $menu_id,
                'menu_name' => $_POST['menu-name'],
                'menu_items_added' => sizeof($arr_added),
                'menu_items_removed' => sizeof($arr_removed),
                // "request" => $this->simpleHistory->json_encode($_REQUEST)
            )
        );
    }

    /**
     * This seems to get called twice
     * one time with menu_data, a second without
     */
    /*
    function on_wp_update_nav_menu($menu_id, $menu_data = array()) {

        if (empty($menu_data)) {
            return;
        }

        $this->infoMessage(
            "edited_menu",
            array(
                "menu_id" => $menu_id,
                "menu_name" => $menu_data["menu-name"],
                "menu_data" => $this->simpleHistory->json_encode($menu_data),
                "request" => $this->simpleHistory->json_encode($_REQUEST)
            )
        );

    }
    */

    /**
     * Get detailed output
     */
    function getLogRowDetailsOutput($row)
    {

        $context = $row->context;
        $message_key = $context['_message_key'];
        $output = '';

        if ('edited_menu' == $message_key) {
            if (! empty($context['menu_items_added']) || ! empty($context['menu_items_removed'])) {
                $output .= '<p>';

                $output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
                $output .= sprintf(
                    _nx('%1$s menu item added', '%1$s menu items added', $context['menu_items_added'], 'menu logger', 'simple-history'),
                    esc_attr($context['menu_items_added'])
                );
                $output .= '</span> ';

                $output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
                $output .= sprintf(
                    _nx('%1$s menu item removed', '%1$s menu items removed', $context['menu_items_removed'], 'menu logger', 'simple-history'),
                    esc_attr($context['menu_items_removed'])
                );
                $output .= '</span> ';

                $output .= '</p>';
            }
        }

        return $output;
    }

    /**
     * Log updates to theme menu locations
     */
    function on_load_nav_menus_page_detect_locations_update()
    {

        // Check that needed vars are set
        if (! isset($_REQUEST['menu'], $_REQUEST['action'])) {
            return;
        }

        if ('locations' !== $_REQUEST['action']) {
            return;
        }

        /*
        Array
        (
            [menu-locations] => Array
                (
                    [primary] => 25
                )
        )
        */
        $menu_locations = $_POST['menu-locations'];

        $this->infoMessage(
            'edited_menu_locations',
            array(
                'menu_locations' => $this->simpleHistory->json_encode($menu_locations),
            )
        );
    }
}
