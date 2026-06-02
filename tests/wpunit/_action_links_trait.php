<?php
/**
 * Shared helpers for the *ActionLinksTest suites.
 *
 * Action-link tests all build the same stdClass row stub and look links up
 * by label. Concrete tests set $logger_slug (e.g. 'SimplePostLogger') so the
 * stub row carries the right logger identifier.
 */

trait ActionLinksTestTrait {
	/** @var string Logger slug, e.g. 'SimplePostLogger'. Subclass must set. */
	protected $logger_slug = '';

	/**
	 * Build a minimal $row->context stub the way the events table emits.
	 *
	 * @param array<string,mixed> $context Context keys.
	 * @return object
	 */
	protected function build_row( array $context ): object {
		$row          = new stdClass();
		$row->logger  = $this->logger_slug;
		$row->context = $context;
		return $row;
	}

	/**
	 * Find the first link with a matching label.
	 *
	 * @param array<int,array<string,mixed>> $links
	 * @param string                          $label
	 * @return array<string,mixed>|null
	 */
	protected function find_by_label( array $links, string $label ): ?array {
		foreach ( $links as $link ) {
			if ( ( $link['label'] ?? '' ) === $label ) {
				return $link;
			}
		}

		return null;
	}
}
