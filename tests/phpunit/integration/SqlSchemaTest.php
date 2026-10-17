<?php

declare( strict_types=1 );

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Tests\Structure\AbstractSchemaTestBase;

/**
 * @group GrowthExperiments
 * @coversNothing
 * @license GPL-2.0-or-later
 */
class SqlSchemaTest extends AbstractSchemaTestBase {
	protected static function getSchemasDirectory(): string {
		return __DIR__ . '/../../../sql/';
	}

	protected static function getSchemaChangesDirectory(): string {
		return __DIR__ . '/../../../sql/abstractSchemaChanges/';
	}

	protected static function getSchemaSQLDirs(): array {
		return [
			'mysql' => __DIR__ . '/../../../sql/mysql/',
			'sqlite' => __DIR__ . '/../../../sql/sqlite/',
			'postgres' => __DIR__ . '/../../../sql/postgres/',
		];
	}

	protected static function getSchemaChangesSQLDirs(): array {
		return self::getSchemaSQLDirs();
	}
}
