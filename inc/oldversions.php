<?php
/**
 * Show an admin message if old PHP version.
 */
function simple_history_old_version_admin_notice() {
    $ok_wp_version = version_compare( $GLOBALS['wp_version'], '5.4', '>=' );
    $ok_php_version = version_compare( phpversion(), '7.4', '>=' );
    ?>
    <div class="updated error">
        <?php
        if ( ! $ok_php_version ) {
            echo '<p>';
            printf(
                /* translators: 1: PHP version */
                esc_html(
                    __(
                        'Simple History is a great plugin, but to use it your server must have at least PHP 7.4 installed (you have version %s).',
                        'simple-history'
                    )
                ),
                esc_html( phpversion() ) // 1
            );
            echo '</p>';
        }

        if ( ! $ok_wp_version ) {
            echo '<p>';
            printf(
                /* translators: 1: WordPress version */
                esc_html(
                    __(
                        'Simple History requires WordPress version 6.1 or higher (you have version %s).',
                        'simple-history'
                    )
                ),
                esc_html( $GLOBALS['wp_version'] ) // 1
            );
            echo '</p>';
        }
        ?>
    </div>
    <?php
}
