<?php

namespace Simple_History;

/**
 * Reads the changelog section of a plugin's readme.txt.
 *
 * Core and Premium write their changelogs the same way: a `## Changelog`
 * section with `### X.Y.Z (Month Year)` headings, an optional summary
 * paragraph with a "Read more … release post" link, and keepachangelog
 * sections (`**Added**`, `**Fixed**` …) with one `-   ` bullet per entry.
 *
 * Plain PHP with no WordPress calls, so it can be tested on the real files.
 */
class Changelog_Parser {
	/**
	 * Parse changelog text into versions.
	 *
	 * Entries starting with "Experimental —" are left out of both the lists and
	 * the counts: most sites never turn experimental features on, so counting
	 * them would promise things the reader can't see.
	 *
	 * @param string $text Full readme.txt contents.
	 * @return array<string,array{added:array<int,array{text:string,name:string}>,fixed:int,other:int,release_url:string}> Keyed by version, newest first as in the file.
	 */
	public static function parse( $text ) {
		if ( ! is_string( $text ) ) {
			return [];
		}

		$changelog_start = strpos( $text, '## Changelog' );

		if ( $changelog_start === false ) {
			return [];
		}

		// Version headings with or without a date: "### 5.35.0 (October 2026)" or "### 1.5.0".
		$parts = preg_split( '/^###\s+(\d+\.\d+(?:\.\d+)?)\b[^\n]*$/m', substr( $text, $changelog_start ), -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) ) {
			return [];
		}

		$versions    = [];
		$parts_count = count( $parts );

		for ( $i = 1; $i + 1 < $parts_count; $i += 2 ) {
			$versions[ $parts[ $i ] ] = self::parse_version_body( $parts[ $i + 1 ] );
		}

		return $versions;
	}

	/**
	 * Parse the text below one version heading.
	 *
	 * @param string $body Text between this version heading and the next.
	 * @return array{added:array<int,array{text:string,name:string}>,fixed:int,other:int,release_url:string}
	 */
	private static function parse_version_body( $body ) {
		$version = [
			'added'       => [],
			'fixed'       => 0,
			'other'       => 0,
			'release_url' => '',
		];

		if ( preg_match( '/\[Read more[^\]]*\]\((https:\/\/simple-history\.com\/[^)\s]+)\)/', $body, $matches ) ) {
			$version['release_url'] = $matches[1];
		}

		$section = '';

		foreach ( explode( "\n", $body ) as $line ) {
			if ( preg_match( '/^\*\*(Added|Changed|Fixed|Security|Deprecated|Removed)\*\*/', $line, $matches ) ) {
				$section = $matches[1];
				continue;
			}

			if ( $section === '' || ! preg_match( '/^-\s+(.+)$/', $line, $matches ) ) {
				continue;
			}

			$entry = trim( $matches[1] );

			if ( strpos( $entry, 'Experimental' ) === 0 ) {
				continue;
			}

			if ( $section === 'Added' ) {
				$version['added'][] = [
					'text' => $entry,
					'name' => self::get_entry_name( $entry ),
				];
			} elseif ( $section === 'Fixed' ) {
				++$version['fixed'];
			} else {
				++$version['other'];
			}
		}

		return $version;
	}

	/**
	 * Get the short bold name an entry starts with, like "Table view" in
	 * "**Table view** for the event log.". Empty when the entry has none.
	 *
	 * @param string $entry Entry text without the bullet.
	 * @return string
	 */
	private static function get_entry_name( $entry ) {
		if ( preg_match( '/^\*\*([^*]{1,60})\*\*/', $entry, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Sum the counts of every version after one version, up to and including another.
	 *
	 * @param array<string,array<string,mixed>> $versions Parsed versions from parse().
	 * @param string                            $after_version Versions newer than this are counted.
	 * @param string                            $up_to_version Versions up to and including this are counted.
	 * @return array{features:int,fixes:int,other:int,versions:array<int,string>,release_url:string} versions are newest first; release_url is the newest one found.
	 */
	public static function summarize( $versions, $after_version, $up_to_version ) {
		$summary = [
			'features'    => 0,
			'fixes'       => 0,
			'other'       => 0,
			'versions'    => [],
			'release_url' => '',
		];

		foreach ( $versions as $version => $data ) {
			$version = (string) $version;

			if ( version_compare( $version, $after_version, '<=' ) || version_compare( $version, $up_to_version, '>' ) ) {
				continue;
			}

			$summary['features']  += count( $data['added'] );
			$summary['fixes']     += $data['fixed'];
			$summary['other']     += $data['other'];
			$summary['versions'][] = $version;

		}

		usort( $summary['versions'], static fn( $a, $b ) => version_compare( $b, $a ) );

		// The newest version that links a release post.
		foreach ( $summary['versions'] as $version ) {
			if ( $versions[ $version ]['release_url'] !== '' ) {
				$summary['release_url'] = $versions[ $version ]['release_url'];
				break;
			}
		}

		return $summary;
	}
}
