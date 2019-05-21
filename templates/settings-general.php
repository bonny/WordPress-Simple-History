<?php
defined('ABSPATH') or die();
?>

<form method="post" action="options.php">

    <?php
    // Prints out all settings sections added to a particular settings page
    do_settings_sections(SimpleHistory::SETTINGS_MENU_SLUG);
    ?>

    <?php
    // Output nonce, action, and option_page fields
    settings_fields('simple_history_settings_group');
    ?>

    <?php submit_button(); ?>

</form>
