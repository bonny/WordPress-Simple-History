<?php

defined( 'ABSPATH' ) || die();

$pager_size = $this->get_pager_size();

/**
 * Filter the pager size setting for the settings page
 *
 * @since 2.0
 *
 * @param int $pager_size
 */
$pager_size = apply_filters( 'simple_history/settings_page_pager_size', $pager_size );

?>
<div class="SimpleHistoryGui"
	 data-pager-size='<?php esc_attr( $pager_size ); ?>'
	 ></div>
<?php

global $wpdb;
$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
