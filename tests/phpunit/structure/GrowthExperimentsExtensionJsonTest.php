<?php

namespace MediaWiki\Extension\GrowthExperiments\Tests\Structure;

use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * @coversNothing
 */
class GrowthExperimentsExtensionJsonTest extends ExtensionJsonTestBase {

	/** @inheritDoc */
	protected static string $extensionJsonPath = __DIR__ . '/../../../extension.json';

	/** @inheritDoc */
	protected static bool $testJobClasses = true;
}
