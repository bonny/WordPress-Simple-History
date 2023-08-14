<?php

namespace Simple_History\Services;

class Scripts_And_Templates extends Service {
	public function loaded() {
		add_action( 'admin_footer', array( $this, 'add_js_templates' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Output JS templated into footer
	 */
	public function add_js_templates( $hook ) {
		if ( $this->simple_history->is_on_our_own_pages() ) {
			?>
			<script type="text/html" id="tmpl-simple-history-base">

				<div class="SimpleHistory__waitingForFirstLoad">
					<img src="<?php echo esc_url( admin_url( '/images/spinner.gif' ) ); ?>" alt="" width="20" height="20">
					<?php
					echo esc_html_x(
						'Loading history...',
						'Message visible while waiting for log to load from server the first time',
						'simple-history'
					);
					?>
				</div>

				<div class="SimpleHistoryLogitemsWrap">
					<div class="SimpleHistoryLogitems__beforeTopPagination"></div>
					<div class="SimpleHistoryLogitems__above"></div>
					<ul class="SimpleHistoryLogitems"></ul>
					<div class="SimpleHistoryLogitems__below"></div>
					<div class="SimpleHistoryLogitems__pagination"></div>
					<div class="SimpleHistoryLogitems__afterBottomPagination"></div>
				</div>

				<div class="SimpleHistoryLogitems__debug"></div>

			</script>

			<script type="text/html" id="tmpl-simple-history-logitems-pagination">

				<!-- this uses the (almost) the same html as WP does -->
				<div class="SimpleHistoryPaginationPages">
					<!--
					{{ data.page_rows_from }}–{{ data.page_rows_to }}
					<span class="SimpleHistoryPaginationDisplayNum"> of {{ data.total_row_count }} </span>
					-->
					<span class="SimpleHistoryPaginationLinks">
						<a
							data-direction="first"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--firstPage <# if ( data.api_args.paged <= 1 ) { #> disabled <# } #>"
							title="{{ data.strings.goToTheFirstPage }}"
							href="#">«</a>
						<a
							data-direction="prev"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--prevPage <# if ( data.api_args.paged <= 1 ) { #> disabled <# } #>"
							title="{{ data.strings.goToThePrevPage }}"
							href="#">‹</a>
						<span class="SimpleHistoryPaginationInput">
							<input class="SimpleHistoryPaginationCurrentPage" title="{{ data.strings.currentPage }}" type="text" name="paged" value="{{ data.api_args.paged }}" size="4">
							<?php _x( 'of', 'page n of n', 'simple-history' ); ?>
							<span class="total-pages">{{ data.pages_count }}</span>
						</span>
						<a
							data-direction="next"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--nextPage <# if ( data.api_args.paged >= data.pages_count ) { #> disabled <# } #>"
							title="{{ data.strings.goToTheNextPage }}"
							href="#">›</a>
						<a
							data-direction="last"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--lastPage <# if ( data.api_args.paged >= data.pages_count ) { #> disabled <# } #>"
							title="{{ data.strings.goToTheLastPage }}"
							href="#">»</a>
					</span>
				</div>

			</script>

			<script type="text/html" id="tmpl-simple-history-logitems-modal">

				<div class="SimpleHistory-modal">
					<div class="SimpleHistory-modal__background"></div>
					<div class="SimpleHistory-modal__content">
						<div class="SimpleHistory-modal__contentInner">
							<img class="SimpleHistory-modal__contentSpinner" src="<?php echo esc_url( admin_url( '/images/spinner.gif' ) ); ?>" alt="">
						</div>
						<div class="SimpleHistory-modal__contentClose">
							<button class="button">✕</button>
						</div>
					</div>
				</div>

			</script>

			<script type="text/html" id="tmpl-simple-history-occasions-too-many">
				<li
					class="SimpleHistoryLogitem
						   SimpleHistoryLogitem--occasion
						   SimpleHistoryLogitem--occasion-tooMany
						   ">
					<div class="SimpleHistoryLogitem__firstcol"></div>
					<div class="SimpleHistoryLogitem__secondcol">
						<div class="SimpleHistoryLogitem__text">
							<?php esc_html_e( 'Sorry, but there are too many similar events to show.', 'simple-history' ); ?>
						</div>
					</div>
				</li>
			</script>

			<?php
			// Call plugins so they can add their js.
			foreach ( $this->simple_history->get_instantiated_loggers() as $one_logger ) {
				$one_logger['instance']->admin_js();
			}
		}
	}

	/**
	 * Enqueue styles and scripts for Simple History but only to our own pages.
	 *
	 * Only adds scripts to pages where the log is shown or the settings page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $this->simple_history->is_on_our_own_pages() ) {
			add_thickbox();

			wp_enqueue_style(
				'simple_history_styles',
				SIMPLE_HISTORY_DIR_URL . 'css/styles.css',
				false,
				SIMPLE_HISTORY_VERSION
			);

			wp_enqueue_script(
				'simple_history_script',
				SIMPLE_HISTORY_DIR_URL . 'js/scripts.js',
				array( 'jquery', 'backbone', 'wp-util' ),
				SIMPLE_HISTORY_VERSION,
				true
			);

			wp_enqueue_script( 'select2', SIMPLE_HISTORY_DIR_URL . 'js/select2/select2.full.min.js', array( 'jquery' ), SIMPLE_HISTORY_VERSION );
			wp_enqueue_style( 'select2', SIMPLE_HISTORY_DIR_URL . 'js/select2/select2.min.css', array(), SIMPLE_HISTORY_VERSION );

			// Translations that we use in JavaScript
			wp_localize_script(
				'simple_history_script',
				'simple_history_script_vars',
				array(
					'settingsConfirmClearLog' => __( 'Remove all log items?', 'simple-history' ),
					'pagination' => array(
						'goToTheFirstPage' => __( 'Go to the first page', 'simple-history' ),
						'goToThePrevPage' => __( 'Go to the previous page', 'simple-history' ),
						'goToTheNextPage' => __( 'Go to the next page', 'simple-history' ),
						'goToTheLastPage' => __( 'Go to the last page', 'simple-history' ),
						'currentPage' => __( 'Current page', 'simple-history' ),
					),
					'loadLogAPIError' => __( 'Oups, the log could not be loaded right now.', 'simple-history' ),
					'ajaxLoadError' => __(
						'Hm, the log could not be loaded right now. Perhaps another plugin is giving some errors. Anyway, below is the output I got from the server.',
						'simple-history'
					),
					'logNoHits' => __( 'Your search did not match any history events.', 'simple-history' ),
				)
			);

			// Call plugins admin_css-method, so they can add CSS.
			foreach ( $this->simple_history->get_instantiated_loggers() as $one_logger ) {
				$one_logger['instance']->admin_css();
			}

			// Add timeago.js
			wp_enqueue_script(
				'timeago',
				SIMPLE_HISTORY_DIR_URL . 'js/timeago/jquery.timeago.js',
				array( 'jquery' ),
				'1.5.2',
				true
			);

			// Determine current locale to load timeago and Select 2locale.
			$user_locale = strtolower( substr( get_user_locale(), 0, 2 ) ); // en_US

			$locale_url_path = SIMPLE_HISTORY_DIR_URL . 'js/timeago/locales/jquery.timeago.%s.js';
			$locale_dir_path = SIMPLE_HISTORY_PATH . 'js/timeago/locales/jquery.timeago.%s.js';

			// Only enqueue if locale-file exists on file system
			if ( file_exists( sprintf( $locale_dir_path, $user_locale ) ) ) {
				wp_enqueue_script( 'timeago-locale', sprintf( $locale_url_path, $user_locale ), array( 'jquery' ), '1.5.2', true );
			} else {
				wp_enqueue_script( 'timeago-locale', sprintf( $locale_url_path, 'en' ), array( 'jquery' ), '1.5.2', true );
			}

			// end add timeago
			// Load Select2 locale
			$locale_url_path = SIMPLE_HISTORY_DIR_URL . 'js/select2/i18n/%s.js';
			$locale_dir_path = SIMPLE_HISTORY_PATH . 'js/select2/i18n/%s.js';

			if ( file_exists( sprintf( $locale_dir_path, $user_locale ) ) ) {
				wp_enqueue_script( 'select2-locale', sprintf( $locale_url_path, $user_locale ), array( 'jquery' ), '3.5.1', true );
			}

			/**
			 * Fires when the admin scripts have been enqueued.
			 * Only fires on any of the pages where Simple History is used.
			 *
			 * @since 2.0
			 *
			 * @param Simple_History $instance The Simple_History instance.
			 */
			do_action( 'simple_history/enqueue_admin_scripts', $this->simple_history );
		} // End if().
	}
}
