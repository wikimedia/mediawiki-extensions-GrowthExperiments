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

	public function testCacheEnabledPassesTheVersionThrough() {
		$this->assertSame(
			[ 'version' => 3 ],
			$this->getPolicy( true, true )->getCacheOptions( 3 )
		);
	}

	public function testCacheDisabledRejectsAnyStoredValue() {
		$this->assertSame(
			[ 'version' => 3, 'minAsOf' => INF ],
			$this->getPolicy( false, true )->getCacheOptions( 3 )
		);
	}

	public function testCacheDisabledIsIgnoredWithoutDeveloperSetup() {
		$this->assertSame(
			[ 'version' => 3 ],
			$this->getPolicy( false, false )->getCacheOptions( 3 )
		);
	}
}
