<?php
/**
 * Sniff that forbids the null coalescing assignment operator.
 *
 * @package SimpleHistory
 */

namespace SimpleHistory\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Disallow "??=".
 *
 * The operator packs a null check and an assignment into three characters that
 * read as punctuation, which makes the line easy to skim past. Spelling it out
 * costs two lines and says plainly what happens:
 *
 *     if ( $needles === null ) {
 *         $needles = self::get_sensitive_field_names();
 *     }
 *
 * Note that this only covers "??=". The plain "??" operator is fine and is
 * still required over isset() ternaries by
 * SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator.
 */
class DisallowNullCoalesceEqualSniff implements Sniff {

	/**
	 * Tokens this sniff listens for.
	 *
	 * @return array<int|string>
	 */
	public function register() {
		return [ T_COALESCE_EQUAL ];
	}

	/**
	 * Report every occurrence.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  Position of the token in the token stack.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$phpcsFile->addError(
			'The "??=" operator is not allowed here. Use an explicit "if ( $var === null ) { ... }" block instead.',
			$stackPtr,
			'Found'
		);
	}
}
