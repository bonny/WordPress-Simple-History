<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;
use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Array_\ArrayThisCallToThisMethodCallRector;
use Rector\DeadCode\Rector\MethodCall\RemoveEmptyMethodCallRector;
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;

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
			__DIR__ . '/docs',
			__DIR__ . '/build',
			__DIR__ . '/vendor',
			__DIR__ . '/node_modules',
			__DIR__ . '/rector.php',
			// Disable rule because I like to concat my strings.
			SimplifyUselessVariableRector::class,
			// Doesn't feel WordPress'ish.
			CallableThisArrayToAnonymousFunctionRector::class,
			// Looks wierd.
			ArrayThisCallToThisMethodCallRector::class,
			// Makes to code more difficult to read.
			SimplifyIfReturnBoolRector::class,
			// Need to find out what they do
			RemoveEmptyMethodCallRector::class,
			RenameFunctionRector::class,
			JsonThrowOnErrorRector::class
		)
	);

	// define sets of rules
	// https://github.com/rectorphp/rector-src/blob/main/packages/Set/ValueObject/SetList.php
	// https://github.com/rectorphp/rector-src/blob/main/rector.php
	$rectorConfig->sets(
		array(
			LevelSetList::UP_TO_PHP_74,
			SetList::DEAD_CODE,
			SetList::CODE_QUALITY,
			// Add lists one by one to make diffs not to big.
			// SetList::CODING_STYLE
		)
	);

};

/*
Random todo som har med PHP 7.4 att göra:
- Ta bort ca. version_compare( PHP_VERSION, '5.4.0' )
- Flytta ut kod ca "get user role, as done in user-edit.php" till egen funktion
- Få igång tester så vi kan testa enkelt.
- Göra dold sida som visar loggen utan ajax, borde gå snabbare att använda för testerna som slipper vänta på ajax.
*/
