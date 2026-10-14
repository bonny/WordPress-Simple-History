<?php

namespace Simple_History;

/**
 * Turns the HTML that loggers produce for event details into plain text.
 *
 * Used for text-only surfaces: log files and other log channels, plain-text
 * alerts and emails. The rules:
 *
 * - A key-value pair (<dt>key</dt><dd>value</dd>, or the pre-5.33 form
 *   <tr><td>key</td><td>value</td></tr>) becomes "key: value" on its own line.
 * - <ins>new</ins> next to <del>old</del>, in either order, becomes "old → new".
 * - A diff table row (td.diff-deletedline / td.diff-addedline, or td.old /
 *   td.new in the side-by-side diff of post content, table.diff-side-by-side) becomes "old → new";
 *   unchanged context rows are dropped. Word-level <ins>/<del> inside a diff
 *   row only mark what changed within that row.
 * - Screen-reader-only text is dropped.
 * - Line breaks and block elements end a line; blank lines are removed.
 *
 * A change from nothing shows only the new value, and a change to nothing
 * shows "old → (removed)".
 *
 * With $one_line_per_pair, each key-value pair stays on one line, for log
 * files and other line-based channels: the lines inside a value are joined
 * with "; ".
 *
 * Parsing is done with WP_HTML_Processor, which understands nesting, so a diff
 * table inside a value cell cannot be confused with the value's own markup.
 * WordPress 6.3 has no processor, and versions before 6.7 refuse some of the
 * elements used here, so a small regex converter covers those installs.
 */
class Details_Text {
	/** @var string Finished text, one entry per line. */
	private $out = '';

	/** @var string|null Key of the pair being read. Null when not inside one. */
	private $key = null;

	/** @var string|null Value of the pair being read. Null when not inside one. */
	private $value = null;

	/** @var string|null Text of an <ins> being read. */
	private $ins_text = null;

	/** @var string|null Text of a <del> being read. */
	private $del_text = null;

	/** @var string|null Finished <del> text waiting for its <ins> counterpart. */
	private $old = null;

	/** @var string|null Finished <ins> text waiting for its <del> counterpart. */
	private $new = null;

	/** @var string|null Which diff cell is being read: 'deleted' or 'added'. */
	private $diff_cell = null;

	/** @var string|null Text of the diff cell being read. */
	private $diff_text = null;

	/** @var string|null Deleted side of a diff row waiting for the added side. */
	private $diff_old = null;

	/** @var string[] Lines of deleted-only diff rows, waiting for added-only rows that replace them. */
	private $removed_lines = [];

	/** @var int|null Depth of an element whose text is dropped (screen-reader text, diff context). */
	private $skip_depth = null;

	/** @var int|null Depth of the open diff table, so its cells are not read as legacy key-value cells. */
	private $diff_table_depth = null;

	/** @var bool Whether the open diff table is the side-by-side diff of post content, whose cells use the classes "old" and "new". */
	private $side_by_side = false;

	/** @var int Which cell of a legacy <tr><td>key</td><td>value</td></tr> row comes next. */
	private $legacy_cell = 0;

	/** @var bool Whether each key-value pair is kept on one line. */
	private $one_line_per_pair = false;

	/** @var string What goes between the key and the value of a pair. */
	private $pair_separator = ': ';

	/** Pair separator for pairs(): a control character that HTML text does not contain. */
	const PAIRS_SEPARATOR = "\x1F";

	/**
	 * Convert details HTML to plain text.
	 *
	 * The output has entities decoded, so it can contain "<" and other
	 * characters that are markup again. Escape it before putting it in HTML.
	 *
	 * @param string $html              Details HTML from a logger.
	 * @param bool   $one_line_per_pair Keep each key-value pair on one line.
	 * @return string Plain text, not safe for HTML output. Empty when there is nothing to show.
	 */
	public static function from_html( $html, $one_line_per_pair = false ) {
		return self::convert( $html, $one_line_per_pair, ': ' );
	}

	/**
	 * Convert details HTML to lines, one per key-value pair or other entry.
	 *
	 * Like from_html() with $one_line_per_pair, but the key of each pair is
	 * kept apart, so a value that itself contains ": " is not mistaken for one.
	 *
	 * @param string $html Details HTML from a logger.
	 * @return array<int, array{key: string, value: string}> Key is '' for lines that are not a pair. Plain text, not safe for HTML output.
	 */
	public static function pairs( $html ) {
		$html  = str_replace( self::PAIRS_SEPARATOR, '', (string) $html );
		$text  = self::convert( $html, true, self::PAIRS_SEPARATOR );
		$pairs = [];

		foreach ( explode( "\n", $text ) as $line ) {
			if ( $line === '' ) {
				continue;
			}

			$parts = explode( self::PAIRS_SEPARATOR, $line, 2 );

			$pairs[] = count( $parts ) === 2
				? [
					'key'   => $parts[0],
					'value' => $parts[1],
				]
				: [
					'key'   => '',
					'value' => $line,
				];
		}

		return $pairs;
	}

	/**
	 * Convert with the HTML API, or with regular expressions when it can't.
	 *
	 * @param string $html              Details HTML.
	 * @param bool   $one_line_per_pair Keep each key-value pair on one line.
	 * @param string $pair_separator    What goes between key and value.
	 * @return string
	 */
	private static function convert( $html, $one_line_per_pair, $pair_separator ) {
		$html = (string) $html;

		if ( trim( $html ) === '' ) {
			return '';
		}

		$text = self::convert_with_html_api( $html, $one_line_per_pair, $pair_separator );

		if ( $text === null ) {
			$text = self::convert_with_regex( $html, $one_line_per_pair, $pair_separator );
		}

		return self::tidy( $text );
	}

	/**
	 * Convert using WP_HTML_Processor.
	 *
	 * @param string $html              Details HTML.
	 * @param bool   $one_line_per_pair Keep each key-value pair on one line.
	 * @param string $pair_separator    What goes between key and value.
	 * @return string|null Text with one entry per line, or null when the
	 *                     processor is unavailable or cannot parse the markup.
	 */
	public static function convert_with_html_api( $html, $one_line_per_pair = false, $pair_separator = ': ' ) {
		// The processor exists since 6.4, but get_current_depth() and the
		// other methods used here were complete first in 6.6.
		if ( ! class_exists( '\WP_HTML_Processor' ) || version_compare( get_bloginfo( 'version' ), '6.6', '<' ) ) {
			return null;
		}

		$processor = \WP_HTML_Processor::create_fragment( (string) $html );

		if ( $processor === null ) {
			return null;
		}

		$converter = new self();

		$converter->one_line_per_pair = (bool) $one_line_per_pair;
		$converter->pair_separator    = (string) $pair_separator;

		while ( $processor->next_token() ) {
			$converter->handle_token( $processor );
		}

		// The processor stops on markup it does not support; then the regex
		// converter takes over rather than returning a partial text.
		if ( $processor->get_last_error() !== null ) {
			return null;
		}

		if ( $converter->key !== null ) {
			$converter->flush_pair();
		}

		$converter->flush_change();

		return $converter->out;
	}

	/**
	 * Read one token from the processor into the text buffers.
	 *
	 * @param \WP_HTML_Processor $processor Processor positioned on a token.
	 * @return void
	 */
	private function handle_token( $processor ) {
		$type  = $processor->get_token_type();
		$depth = $processor->get_current_depth();

		if ( $this->skip_depth !== null && $depth < $this->skip_depth ) {
			$this->skip_depth = null;
		}

		if ( $type === '#text' ) {
			$text = $processor->get_modifiable_text();

			// Blank text between the cells of a diff table, like the &nbsp; of
			// an empty cell, would end up in front of the next change.
			$is_blank_between_diff_cells = $this->diff_table_depth !== null && $this->diff_text === null && trim( $text, " \t\n\r\0\x0B\u{00A0}" ) === '';

			if ( $this->skip_depth === null && ! $is_blank_between_diff_cells ) {
				$this->append( $text );
			}

			return;
		}

		if ( $type !== '#tag' ) {
			return;
		}

		$name      = (string) $processor->get_token_name();
		$is_closer = $processor->is_tag_closer();
		$class     = $is_closer ? '' : (string) $processor->get_attribute( 'class' );

		// Screen-reader-only text carries nothing for a text reader.
		if ( ! $is_closer && $this->skip_depth === null && self::has_class( $class, 'screen-reader-text' ) ) {
			$this->skip_depth = $depth;
			return;
		}

		if ( $this->skip_depth !== null ) {
			return;
		}

		switch ( $name ) {
			case 'DT':
				if ( ! $is_closer ) {
					// A <dt> without a following <dd> is a lone key.
					if ( $this->key !== null ) {
						$this->flush_pair();
					}

					$this->key = '';
				}
				break;

			case 'DD':
				if ( $is_closer ) {
					$this->flush_pair();
				} else {
					$this->value = '';
				}
				break;

			case 'INS':
				// Inside a diff row, <ins> only marks the changed words of the added side.
				if ( $this->diff_cell !== null ) {
					break;
				}

				if ( $is_closer ) {
					$this->new      = trim( (string) $this->ins_text );
					$this->ins_text = null;

					if ( $this->old !== null ) {
						$this->flush_change();
					}
				} else {
					// A second <ins> without a <del> in between: the first stood alone.
					if ( $this->new !== null ) {
						$this->flush_change();
					}

					$this->ins_text = '';
				}
				break;

			case 'DEL':
				// Inside a diff row, <del> only marks the changed words of the deleted side.
				if ( $this->diff_cell !== null ) {
					break;
				}

				if ( $is_closer ) {
					$this->old      = trim( (string) $this->del_text );
					$this->del_text = null;

					if ( $this->new !== null ) {
						$this->flush_change();
					}
				} else {
					if ( $this->old !== null ) {
						$this->flush_change();
					}

					$this->del_text = '';
				}
				break;

			case 'TABLE':
				if ( $is_closer ) {
					if ( $this->diff_table_depth !== null && $depth <= $this->diff_table_depth ) {
						$this->flush_removed_lines();
						$this->diff_table_depth = null;
						$this->side_by_side     = false;
					}
				} elseif ( self::has_class( $class, 'diff' ) ) {
					$this->diff_table_depth = $depth;
					$this->side_by_side     = self::has_class( $class, 'diff-side-by-side' );
				}
				break;

			case 'TBODY':
				// Unchanged lines in the side-by-side diff of post content.
				if ( ! $is_closer && $this->diff_table_depth !== null && self::has_class( $class, 'change-eq' ) ) {
					$this->skip_depth = $depth;
				}
				break;

			case 'TR':
				if ( $this->diff_table_depth !== null ) {
					// A diff row with only a deleted side. Kept until the next
					// row shows whether the line was replaced or removed.
					if ( $is_closer && $this->diff_old !== null ) {
						if ( $this->diff_old !== '' ) {
							$this->removed_lines[] = $this->diff_old;
						}

						$this->diff_old = null;
					}
				} else {
					if ( $is_closer && $this->key !== null ) {
						$this->flush_pair();
					}

					$this->legacy_cell = 0;
				}
				break;

			case 'TD':
				if ( $this->diff_table_depth !== null ) {
					$this->handle_diff_cell( $is_closer, $class, $depth );
				} elseif ( ! $is_closer ) {
					// Legacy key-value row: first cell is the key, second the value.
					++$this->legacy_cell;

					if ( $this->legacy_cell === 1 ) {
						$this->key = '';
					} elseif ( $this->legacy_cell === 2 ) {
						$this->value = '';
					}
				} elseif ( $this->legacy_cell === 2 && $this->value !== null ) {
					$this->flush_pair();
				}
				break;

			case 'BR':
				$this->append( "\n" );
				break;

			case 'P':
			case 'DIV':
			case 'LI':
			case 'H1':
			case 'H2':
			case 'H3':
			case 'H4':
			case 'H5':
			case 'H6':
				if ( $is_closer ) {
					$this->append( "\n" );
				}
				break;
		}
	}

	/**
	 * Read a cell of a diff table.
	 *
	 * @param bool   $is_closer Whether the token is the closing tag.
	 * @param string $class_attr Class attribute of the opening tag.
	 * @param int    $depth     Depth of the token.
	 * @return void
	 */
	private function handle_diff_cell( $is_closer, $class_attr, $depth ) {
		if ( ! $is_closer ) {
			if ( self::has_class( $class_attr, 'diff-deletedline' ) || ( $this->side_by_side && self::has_class( $class_attr, 'old' ) ) ) {
				$this->diff_cell = 'deleted';
				$this->diff_text = '';
			} elseif ( self::has_class( $class_attr, 'diff-addedline' ) || ( $this->side_by_side && self::has_class( $class_attr, 'new' ) ) ) {
				$this->diff_cell = 'added';
				$this->diff_text = '';
			} elseif ( self::has_class( $class_attr, 'diff-context' ) ) {
				// Unchanged lines add noise in plain text. A deleted line
				// before them was not replaced by an added line after them.
				$this->flush_removed_lines();
				$this->skip_depth = $depth;
			}

			return;
		}

		$cell            = $this->diff_cell;
		$cell_text       = trim( (string) $this->diff_text );
		$this->diff_cell = null;
		$this->diff_text = null;

		if ( $cell === 'deleted' ) {
			$this->diff_old = $cell_text;
		} elseif ( $cell === 'added' ) {
			$is_added_only = (string) $this->diff_old === '';

			// WordPress writes a line it cannot diff word by word as a
			// deleted-only row and then an added-only row: a replacement.
			if ( $is_added_only && $cell_text !== '' && $this->removed_lines !== [] ) {
				$this->append( self::change_to_text( (string) array_shift( $this->removed_lines ), $cell_text ) . "\n" );
			} else {
				$this->flush_removed_lines();

				if ( $this->diff_old !== null || $cell_text !== '' ) {
					$this->append( self::change_to_text( (string) $this->diff_old, $cell_text ) . "\n" );
				}
			}

			$this->diff_old = null;
		}
	}

	/**
	 * Append text to the innermost open buffer.
	 *
	 * @param string $text Text to append.
	 * @return void
	 */
	private function append( $text ) {
		if ( $this->diff_text !== null ) {
			$this->diff_text .= $text;
		} elseif ( $this->ins_text !== null ) {
			$this->ins_text .= $text;
		} elseif ( $this->del_text !== null ) {
			$this->del_text .= $text;
		} elseif ( $this->value !== null ) {
			$this->value .= $text;
		} elseif ( $this->key !== null ) {
			$this->key .= $text;
		} else {
			$this->out .= $text;
		}
	}

	/**
	 * Write deleted-only diff rows that no added row replaced.
	 *
	 * @return void
	 */
	private function flush_removed_lines() {
		foreach ( $this->removed_lines as $line ) {
			$this->append( self::change_to_text( $line, '' ) . "\n" );
		}

		$this->removed_lines = [];
	}

	/**
	 * Write a finished "old → new" change, or a lone half, into the enclosing buffer.
	 *
	 * @return void
	 */
	private function flush_change() {
		if ( $this->old === null && $this->new === null ) {
			return;
		}

		if ( $this->old !== null && $this->new !== null ) {
			$this->append( self::change_to_text( $this->old, $this->new ) );
		} else {
			$this->append( (string) ( $this->old ?? $this->new ) );
		}

		$this->old = null;
		$this->new = null;
	}

	/**
	 * Format a change as "old → new".
	 *
	 * @param string $old_value Old value.
	 * @param string $new_value New value.
	 * @return string
	 */
	private static function change_to_text( $old_value, $new_value ) {
		$old_value = trim( (string) $old_value );
		$new_value = trim( (string) $new_value );

		// A change from nothing reads as the value being set. A change to
		// nothing says so, or it would read the same way.
		if ( $old_value === '' ) {
			return $new_value;
		}

		if ( $new_value === '' ) {
			/* translators: Shown after a value that was removed, as in "Old text → (removed)". */
			return $old_value . ' → ' . __( '(removed)', 'simple-history' );
		}

		return trim( $old_value . ' → ' . $new_value );
	}

	/**
	 * Write the finished key-value pair as a line.
	 *
	 * @return void
	 */
	private function flush_pair() {
		$this->flush_change();

		$value = (string) $this->value;

		if ( $this->one_line_per_pair ) {
			$value = self::join_lines( $value );
		}

		$line = self::format_pair( (string) $this->key, $value, $this->pair_separator );

		if ( $line !== '' ) {
			$this->out .= $line . "\n";
		}

		$this->key   = null;
		$this->value = null;
	}

	/**
	 * Convert with regular expressions.
	 *
	 * Handles the markup the loggers produce but cannot see nesting, so the
	 * patterns are kept to text that contains no tags of its own. Anything
	 * they miss ends up as its plain text, unlabelled.
	 *
	 * @param string $html              Details HTML.
	 * @param bool   $one_line_per_pair Keep each key-value pair on one line.
	 * @param string $pair_separator    What goes between key and value.
	 * @return string Text with one entry per line.
	 */
	public static function convert_with_regex( $html, $one_line_per_pair = false, $pair_separator = ': ' ) {
		$html = (string) $html;

		// Screen-reader-only text.
		$html = (string) preg_replace( '/<(span|h[1-6])[^>]*class=[\'"][^"\']*screen-reader-text[^"\']*[\'"][^>]*>[^<]*<\/\1>/i', '', $html );

		// Unchanged diff context rows add noise in plain text. The empty
		// span keeps a deleted row before them from looking adjacent to an
		// added row after them.
		$html = (string) preg_replace( '/<tr[^>]*>\s*<td[^>]*class=[\'"][^"\']*diff-context[^"\']*[\'"][^>]*>[^<]*<\/td>\s*<td[^>]*>[^<]*<\/td>\s*<td[^>]*>[^<]*<\/td>\s*<\/tr>/i', '<span></span>', $html );

		// Unchanged lines in the side-by-side diff of post content.
		$html = (string) preg_replace( '/<tbody[^>]*class=[\'"][^"\']*change-eq[^"\']*[\'"][^>]*>.*?<\/tbody>/is', '', $html );

		$diff_row_to_line = function ( $matches ) {
			$deleted = trim( wp_strip_all_tags( $matches[1] ) );
			$added   = trim( wp_strip_all_tags( $matches[2] ) );

			if ( $deleted === '' && $added === '' ) {
				return '';
			}

			return self::change_to_text( $deleted, $added ) . "\n";
		};

		// Diff rows: deleted side and added side become "old → new".
		// The split view has an empty cell between the two sides.
		$html = (string) preg_replace_callback(
			'/<tr[^>]*>\s*<td[^>]*class=[\'"][^"\']*diff-deletedline[^"\']*[\'"][^>]*>([^<]*+(?:<(?!\/td>)[^<]*+)*+)<\/td>(?:\s*<td[^>]*>\s*<\/td>)?\s*<td[^>]*class=[\'"][^"\']*diff-addedline[^"\']*[\'"][^>]*>([^<]*+(?:<(?!\/td>)[^<]*+)*+)<\/td>\s*<\/tr>/is',
			$diff_row_to_line,
			$html
		);

		// Deleted-only rows followed by added-only rows: WordPress writes a
		// line it cannot diff word by word that way, so pair them up.
		$cell          = '([^<]*+(?:<(?!\/td>)[^<]*+)*+)';
		$empty_cells   = '(?:\s*<td[^>]*>(?:\s|&nbsp;)*<\/td>)*';
		$deleted_row   = '<tr[^>]*>\s*<td[^>]*class=[\'"][^"\']*diff-deletedline[^"\']*[\'"][^>]*>' . $cell . '<\/td>' . $empty_cells . '\s*<\/tr>';
		$added_row     = '<tr[^>]*>' . $empty_cells . '\s*<td[^>]*class=[\'"][^"\']*diff-addedline[^"\']*[\'"][^>]*>' . $cell . '<\/td>\s*<\/tr>';
		$non_capturing = fn( $pattern ) => str_replace( $cell, '(?:' . substr( $cell, 1 ), $pattern );

		$html = (string) preg_replace_callback(
			'/((?:' . $non_capturing( $deleted_row ) . '\s*)+)((?:' . $non_capturing( $added_row ) . '\s*)+)/is',
			function ( $matches ) use ( $deleted_row, $added_row ) {
				preg_match_all( '/' . $deleted_row . '/is', $matches[1], $deleted );
				preg_match_all( '/' . $added_row . '/is', $matches[2], $added );

				$old_lines = array_values( array_filter( array_map( fn( $text ) => self::tidy( wp_strip_all_tags( $text ) ), $deleted[1] ), 'strlen' ) );
				$new_lines = array_values( array_filter( array_map( fn( $text ) => self::tidy( wp_strip_all_tags( $text ) ), $added[1] ), 'strlen' ) );
				$out       = '';
				$row_count = max( count( $old_lines ), count( $new_lines ) );

				for ( $i = 0; $i < $row_count; $i++ ) {
					$out .= self::change_to_text( $old_lines[ $i ] ?? '', $new_lines[ $i ] ?? '' ) . "\n";
				}

				return $out;
			},
			$html
		);

		// Diff rows with only an added side.
		$html = (string) preg_replace_callback(
			'/' . $added_row . '/is',
			fn( $matches ) => self::tidy( wp_strip_all_tags( $matches[1] ) ) . "\n",
			$html
		);

		// Diff rows with only a deleted side.
		$html = (string) preg_replace_callback(
			'/<tr[^>]*>\s*<td[^>]*class=[\'"][^"\']*diff-deletedline[^"\']*[\'"][^>]*>([^<]*+(?:<(?!\/td>)[^<]*+)*+)<\/td>(?:\s*<td[^>]*>(?:\s|&nbsp;)*<\/td>)*\s*<\/tr>/is',
			fn( $matches ) => self::tidy( wp_strip_all_tags( $matches[1] ) ) === '' ? '' : self::change_to_text( wp_strip_all_tags( $matches[1] ), '' ) . "\n",
			$html
		);

		// Rows of the side-by-side diff of post content. Its cells use the
		// generic classes "old" and "new", so only look for them when the
		// side-by-side table is there.
		if ( stripos( $html, 'diff-side-by-side' ) !== false ) {
			$html = (string) preg_replace_callback(
				'/<tr[^>]*>\s*<td[^>]*class=[\'"](?:[^"\']*\s)?old(?:\s[^"\']*)?[\'"][^>]*>([^<]*+(?:<(?!\/td>)[^<]*+)*+)<\/td>\s*<td[^>]*class=[\'"](?:[^"\']*\s)?new(?:\s[^"\']*)?[\'"][^>]*>([^<]*+(?:<(?!\/td>)[^<]*+)*+)<\/td>\s*<\/tr>/is',
				$diff_row_to_line,
				$html
			);
		}

		// <del>old</del><ins>new</ins> and <ins>new</ins><del>old</del>.
		$html = (string) preg_replace_callback(
			'/<del[^>]*>([^<]*)<\/del>\s*<ins[^>]*>([^<]*)<\/ins>/i',
			fn( $matches ) => self::change_to_text( $matches[1], $matches[2] ),
			$html
		);

		$html = (string) preg_replace_callback(
			'/<ins[^>]*>([^<]*)<\/ins>\s*<del[^>]*>([^<]*)<\/del>/i',
			fn( $matches ) => self::change_to_text( $matches[2], $matches[1] ),
			$html
		);

		$pair_to_line = function ( $matches ) use ( $one_line_per_pair, $pair_separator ) {
			$value = wp_strip_all_tags( $matches[2] );

			if ( $one_line_per_pair ) {
				$value = self::join_lines( $value );
			}

			$line = self::format_pair( wp_strip_all_tags( $matches[1] ), $value, $pair_separator );

			return $line === '' ? '' : $line . "\n";
		};

		// Key-value pairs, current markup.
		$html = (string) preg_replace_callback( '/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is', $pair_to_line, $html );

		// Key-value pairs, pre-5.33 markup that third-party loggers may still emit.
		$html = (string) preg_replace_callback( '/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/is', $pair_to_line, $html );

		// Line breaks and block elements end a line.
		$html = (string) preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$html = (string) preg_replace( '/<\/(p|div|tr|dd|li|h[1-6])>/i', "\n", $html );

		return html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Format one key-value pair as a line of text.
	 *
	 * A value of "0" is a value; only an empty string counts as missing.
	 *
	 * @param string $key       Key text.
	 * @param string $value     Value text.
	 * @param string $separator What goes between key and value.
	 * @return string "key: value", a lone key or value, or "" when both are empty.
	 */
	public static function format_pair( $key, $value, $separator = ': ' ) {
		$key   = trim( (string) preg_replace( '/\s+/', ' ', (string) $key ) );
		$value = trim( (string) $value );

		if ( $key === '' ) {
			return $value;
		}

		if ( $value === '' ) {
			return $key;
		}

		return $key . $separator . $value;
	}

	/**
	 * Join the lines of a value with "; ", leaving out blank lines.
	 *
	 * @param string $text Text with newlines.
	 * @return string
	 */
	private static function join_lines( $text ) {
		return implode( '; ', explode( "\n", self::tidy( (string) $text ) ) );
	}

	/**
	 * Collapse runs of spaces, trim each line, drop blank lines.
	 *
	 * @param string $text Text with newlines.
	 * @return string
	 */
	private static function tidy( $text ) {
		$text  = (string) preg_replace( '/[ \t]+/', ' ', $text );
		$lines = array_map( 'trim', explode( "\n", $text ) );
		$lines = array_filter( $lines, fn( $line ) => $line !== '' );

		return implode( "\n", $lines );
	}

	/**
	 * Whether a class attribute value contains a class name.
	 *
	 * @param string $class_attribute Space-separated class names.
	 * @param string $class_name      Class to look for.
	 * @return bool
	 */
	private static function has_class( $class_attribute, $class_name ) {
		$classes = preg_split( '/\s+/', trim( $class_attribute ) );

		return is_array( $classes ) && in_array( $class_name, $classes, true );
	}
}
