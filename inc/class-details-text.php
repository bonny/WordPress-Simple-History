<?php

namespace Simple_History;

/**
 * Turns the HTML that loggers produce for event details into plain text.
 *
 * Used for text-only surfaces: Copy as text, plain-text alerts and emails,
 * the abilities API. The rules:
 *
 * - A key-value pair (<dt>key</dt><dd>value</dd>, or the pre-5.33 form
 *   <tr><td>key</td><td>value</td></tr>) becomes "key: value" on its own line.
 * - <ins>new</ins> next to <del>old</del>, in either order, becomes "old → new".
 * - A diff table row (td.diff-deletedline / td.diff-addedline) becomes
 *   "old → new"; unchanged context rows are dropped.
 * - Screen-reader-only text is dropped.
 * - Line breaks and block elements end a line; blank lines are removed.
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

	/** @var int|null Depth of an element whose text is dropped (screen-reader text, diff context). */
	private $skip_depth = null;

	/** @var int|null Depth of the open diff table, so its cells are not read as legacy key-value cells. */
	private $diff_table_depth = null;

	/** @var int Which cell of a legacy <tr><td>key</td><td>value</td></tr> row comes next. */
	private $legacy_cell = 0;

	/**
	 * Convert details HTML to plain text.
	 *
	 * @param string $html Details HTML from a logger.
	 * @return string Plain text, empty when there is nothing to show.
	 */
	public static function from_html( $html ) {
		$html = (string) $html;

		if ( trim( $html ) === '' ) {
			return '';
		}

		$text = self::convert_with_html_api( $html );

		if ( $text === null ) {
			$text = self::convert_with_regex( $html );
		}

		return self::tidy( $text );
	}

	/**
	 * Convert using WP_HTML_Processor.
	 *
	 * @param string $html Details HTML.
	 * @return string|null Text with one entry per line, or null when the
	 *                     processor is unavailable or cannot parse the markup.
	 */
	public static function convert_with_html_api( $html ) {
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
			if ( $this->skip_depth === null ) {
				$this->append( $processor->get_modifiable_text() );
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
						$this->diff_table_depth = null;
					}
				} elseif ( self::has_class( $class, 'diff' ) ) {
					$this->diff_table_depth = $depth;
				}
				break;

			case 'TR':
				if ( $this->diff_table_depth !== null ) {
					// A diff row with only a deleted side.
					if ( $is_closer && $this->diff_old !== null ) {
						$this->append( $this->diff_old . "\n" );
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
			if ( self::has_class( $class_attr, 'diff-deletedline' ) ) {
				$this->diff_cell = 'deleted';
				$this->diff_text = '';
			} elseif ( self::has_class( $class_attr, 'diff-addedline' ) ) {
				$this->diff_cell = 'added';
				$this->diff_text = '';
			} elseif ( self::has_class( $class_attr, 'diff-context' ) ) {
				// Unchanged lines add noise in plain text.
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
			if ( $this->diff_old !== null || $cell_text !== '' ) {
				$this->append( trim( (string) $this->diff_old . ' → ' . $cell_text ) . "\n" );
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
	 * Write a finished "old → new" change, or a lone half, into the enclosing buffer.
	 *
	 * @return void
	 */
	private function flush_change() {
		if ( $this->old === null && $this->new === null ) {
			return;
		}

		if ( $this->old !== null && $this->new !== null ) {
			$this->append( trim( $this->old . ' → ' . $this->new ) );
		} else {
			$this->append( (string) ( $this->old ?? $this->new ) );
		}

		$this->old = null;
		$this->new = null;
	}

	/**
	 * Write the finished key-value pair as a line.
	 *
	 * @return void
	 */
	private function flush_pair() {
		$this->flush_change();

		$line = self::format_pair( (string) $this->key, (string) $this->value );

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
	 * @param string $html Details HTML.
	 * @return string Text with one entry per line.
	 */
	public static function convert_with_regex( $html ) {
		$html = (string) $html;

		// Screen-reader-only text.
		$html = (string) preg_replace( '/<(span|h[1-6])[^>]*class=[\'"][^"\']*screen-reader-text[^"\']*[\'"][^>]*>[^<]*<\/\1>/i', '', $html );

		// Unchanged diff context rows add noise in plain text.
		$html = (string) preg_replace( '/<tr[^>]*>\s*<td[^>]*class=[\'"][^"\']*diff-context[^"\']*[\'"][^>]*>[^<]*<\/td>\s*<td[^>]*>[^<]*<\/td>\s*<td[^>]*>[^<]*<\/td>\s*<\/tr>/i', '', $html );

		// Diff rows: deleted side and added side become "old → new".
		$html = (string) preg_replace_callback(
			'/<tr[^>]*>\s*<td[^>]*class=[\'"][^"\']*diff-deletedline[^"\']*[\'"][^>]*>(.*?)<\/td>\s*<td[^>]*>\s*<\/td>\s*<td[^>]*class=[\'"][^"\']*diff-addedline[^"\']*[\'"][^>]*>(.*?)<\/td>\s*<\/tr>/is',
			function ( $matches ) {
				$deleted = trim( wp_strip_all_tags( $matches[1] ) );
				$added   = trim( wp_strip_all_tags( $matches[2] ) );

				if ( $deleted === '' && $added === '' ) {
					return '';
				}

				return trim( $deleted . ' → ' . $added ) . "\n";
			},
			$html
		);

		// <del>old</del><ins>new</ins> and <ins>new</ins><del>old</del>.
		$html = (string) preg_replace_callback(
			'/<del[^>]*>([^<]*)<\/del>\s*<ins[^>]*>([^<]*)<\/ins>/i',
			fn( $matches ) => trim( trim( $matches[1] ) . ' → ' . trim( $matches[2] ) ),
			$html
		);

		$html = (string) preg_replace_callback(
			'/<ins[^>]*>([^<]*)<\/ins>\s*<del[^>]*>([^<]*)<\/del>/i',
			fn( $matches ) => trim( trim( $matches[2] ) . ' → ' . trim( $matches[1] ) ),
			$html
		);

		$pair_to_line = function ( $matches ) {
			$line = self::format_pair( wp_strip_all_tags( $matches[1] ), wp_strip_all_tags( $matches[2] ) );

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
	 * @param string $key   Key text.
	 * @param string $value Value text.
	 * @return string "key: value", a lone key or value, or "" when both are empty.
	 */
	public static function format_pair( $key, $value ) {
		$key   = trim( (string) preg_replace( '/\s+/', ' ', (string) $key ) );
		$value = trim( (string) $value );

		if ( $key === '' ) {
			return $value;
		}

		if ( $value === '' ) {
			return $key;
		}

		return $key . ': ' . $value;
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
