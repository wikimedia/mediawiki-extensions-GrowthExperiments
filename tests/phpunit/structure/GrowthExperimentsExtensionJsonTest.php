<?php

namespace MediaWiki\Extension\GrowthExperiments\Tests\Structure;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * @coversNothing
 */
class GrowthExperimentsExtensionJsonTest extends ExtensionJsonTestBase {

	/** @inheritDoc */
	protected static string $extensionJsonPath = __DIR__ . '/../../../extension.json';

	/** @inheritDoc */
	protected static bool $testJobClasses = true;

	public static function provideApiQueryModuleListsAndNames(): iterable {
		foreach ( parent::provideApiQueryModuleListsAndNames() as [ $moduleList, $moduleName ] ) {
			// TODO: Figure out how to construct linkrecommendations w/o CirrusSearch
			if (
				$moduleList === 'APIListModules'
				&& $moduleName === 'linkrecommendations'
				&& !ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' )
			) {
				continue;
			}
			yield [ $moduleList, $moduleName ];
		}
	}
}
