<?php

namespace GrowthExperiments\Tests;

use CirrusSearch\Wikimedia\WeightedTagsHooks;

/**
 * Terrible hack to get around CirrusSearch not being installed in CI.
 * If CirrusSearch is included in test builds for GrowthExperiments, this hack can be removed.
 */
trait MockCirrusClassesTrait {

	public static function setUpBeforeClass(): void {
		self::mockCirrusClasses();
		parent::setUpBeforeClass();
	}

	protected static function mockCirrusClasses() {
		$mockDir = __DIR__ . '/mock-classes';
		if ( !class_exists( WeightedTagsHooks::class ) ) {
			// used in HomepageHooksTest
			require_once $mockDir . '/WeightedTagsHooks.php';
			require_once $mockDir . '/CirrusIndexField.php';
		}
	}

}
