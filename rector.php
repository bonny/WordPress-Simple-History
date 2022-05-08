<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function ( RectorConfig $rectorConfig ): void {
	$rectorConfig->paths(
		array(
			__DIR__ . '/loggers',
			__DIR__ . '/dropins',
			__DIR__ . '/examples',
			__DIR__ . '/inc',
			__DIR__ . '/templates',
		)
	);

	$rectorConfig->phpVersion( PhpVersion::PHP_70 );

	// register a single rule
	// $rectorConfig->rule( InlineConstructorDefaultToPropertyRector::class );

	// define sets of rules
	$rectorConfig->sets(
		array(
			LevelSetList::UP_TO_PHP_70,
			// Add lists one by one to make diffs not to big.
			// SetList::CODE_QUALITY
		)
	);
};
