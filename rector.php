<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;
use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\ClassMethod\ReturnTypeFromStrictScalarReturnExprRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\DeadCode\Rector\MethodCall\RemoveEmptyMethodCallRector;
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php74\Rector\Assign\NullCoalescingOperatorRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector;

return static function ( RectorConfig $rectorConfig ): void {
	$rectorConfig->paths(
		array(
			__DIR__,
		)
	);

	$rectorConfig->phpVersion( PhpVersion::PHP_74 );

	// register a single rule
	// $rectorConfig->rule( InlineConstructorDefaultToPropertyRector::class );

	// Skip some paths and skip some tests.
	$rectorConfig->skip(
		array(
			__DIR__ . '/tests',
			__DIR__ . '/build',
			__DIR__ . '/vendor',
			__DIR__ . '/node_modules',
			__DIR__ . '/rector.php',
			// Disable rule because I like to concat my strings.
			SimplifyUselessVariableRector::class,
			// Doesn't feel WordPress'ish.
			CallableThisArrayToAnonymousFunctionRector::class,
			// Makes to code more difficult to read.
			SimplifyIfReturnBoolRector::class,
			// Need to find out what they do
			RenameFunctionRector::class,
			JsonThrowOnErrorRector::class,
			// I think `count($array) > 0;` is more readable than `$array !== [];`.
			CountArrayToEmptyArrayComparisonRector::class,
			// I prefer `if ( empty( $post_ids ) ) ` over `if ( $post_ids === [] )`. Also WordPress does not use short arrays.
			SimplifyEmptyCheckOnEmptyArrayRector::class,
			DisallowedEmptyRuleFixerRector::class,
			IssetOnPropertyObjectToPropertyExistsRector::class,
			// WordPress uses long arrays.
			LongArrayToShortArrayRector::class,
			// Prefer `foreach $key => $value` over `foreach (array_keys($values) as $key) {`
			UnusedForeachValueToArrayKeysRector::class,
			ReturnTypeFromStrictTypedCallRector::class,
			// ReturnTypeFromStrictScalarReturnExprRector::class,
			// Ternary not allowed in WP.
			SimplifyIfElseToTernaryRector::class,
			NullCoalescingOperatorRector::class,
		)
	);

	// define sets of rules
	// https://github.com/rectorphp/rector-src/blob/main/packages/Set/ValueObject/SetList.php
	// https://github.com/rectorphp/rector-src/blob/main/rector.php
	// https://github.com/rectorphp/rector/blob/main/docs/rector_rules_overview.md
	$rectorConfig->sets(
		array(
			LevelSetList::UP_TO_PHP_74,
			SetList::DEAD_CODE,
			SetList::CODE_QUALITY,
			// Add lists one by one to make diffs not to big.
			// SetList::CODING_STYLE,
			// SetList::EARLY_RETURN,
		)
	);
};
