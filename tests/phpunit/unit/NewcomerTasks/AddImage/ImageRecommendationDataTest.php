<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationData
 */
class ImageRecommendationDataTest extends MediaWikiUnitTestCase {

	public function testGetFormattedProjects() {
		$imageRecommendationData = new ImageRecommendationData(
			'image.jpg', 'wikipedia', 'enwiki, dewiki', '1.23'
		);
		$imageRecommendationDataInvalidProjects = new ImageRecommendationData(
			'image.jpg', 'wikipedia', null, '1.23'
		);

		$this->assertArrayEquals(
			[ 'enwiki', 'dewiki' ],
			$imageRecommendationData->getFormattedProjects()
		);

		$this->assertArrayEquals(
			[],
			$imageRecommendationDataInvalidProjects->getFormattedProjects()
		);
	}
}
