<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsCachePolicy;
use MediaWiki\Config\ServiceOptions;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\ReadingRecommendations\ReadingRecommendationsCachePolicy
 */
class ReadingRecommendationsCachePolicyTest extends MediaWikiUnitTestCase {

	private function getPolicy( bool $cacheEnabled, bool $developerSetup ): ReadingRecommendationsCachePolicy {
		return new ReadingRecommendationsCachePolicy( new ServiceOptions(
			ReadingRecommendationsCachePolicy::CONSTRUCTOR_OPTIONS,
			[
				'GEReadingRecommendationsCacheEnabled' => $cacheEnabled,
				'GEDeveloperSetup' => $developerSetup,
			]
		) );
	}

	public function testShouldUseCacheWhenEnabled() {
		// Default
		$this->assertTrue( $this->getPolicy( true, false )->shouldUseCache() );
	}

	public function testShouldUseCacheWhenDev() {
		$this->assertTrue( $this->getPolicy( true, true )->shouldUseCache() );
	}

	public function testShouldUseCacheWhenDisabled() {
		$this->assertTrue( $this->getPolicy( false, false )->shouldUseCache() );
	}

	public function testShouldUseCacheWhenDisabledAndDev() {
		$this->assertFalse( $this->getPolicy( false, true )->shouldUseCache() );
	}
}
