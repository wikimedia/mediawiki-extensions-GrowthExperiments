<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\CampaignConfig;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\CampaignConfig
 */
class CampaignConfigTest extends MediaWikiUnitTestCase {

	public function testConfigNotSet() {
		$campaignConfig = new CampaignConfig();
		$this->assertArrayEquals( $campaignConfig->getCampaignTopics(), [] );
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-glam-2022' ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-glam-2022' ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( null ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign(),
			[]
		);
	}

	public function testSingleCampaign() {
		$topicIds = [ 'argentina', 'mexico', 'chile' ];
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'topics' => $topicIds,
				'pattern' => '/^growth-glam-2022$/'
			]
		], [
			'argentina' => 'growtharticletopic:argentina',
			'mexico' => 'growtharticletopic:mexico',
			'chile' => 'growtharticletopic:chile'
		] );
		$this->assertArrayEquals( $campaignConfig->getCampaignTopics(), [
			[ 'id' => 'argentina', 'searchExpression' => 'growtharticletopic:argentina' ],
			[ 'id' => 'mexico', 'searchExpression' => 'growtharticletopic:mexico' ],
			[ 'id' => 'chile', 'searchExpression' => 'growtharticletopic:chile' ]
		] );
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-glam-2022' ),
			$topicIds
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-glam-2022' ),
			[]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign(),
			$topicIds
		);
	}

	public function testMultipleCampaigns() {
		$campaignConfig = new CampaignConfig( [
			'growth-glam-2022' => [
				'topics' => [ 'argentina', 'mexico', 'chile' ],
				'pattern' => '/^growth-glam-2022$/'
			],
			'growth-argentina-2022' => [
				'topics' => [ 'argentina', 'argentina-expanded' ],
				'pattern' => '/^growth-argentina-2022$/'
			]
		], [
			'argentina' => 'growtharticletopic:argentina',
			'mexico' => 'growtharticletopic:mexico',
			'chile' => 'growtharticletopic:chile',
			'argentina-expanded' => 'morelikethis:argentina'
		] );
		$this->assertArrayEquals(
			$campaignConfig->getCampaignTopics(),
			[
				[ 'id' => 'argentina', 'searchExpression' => 'growtharticletopic:argentina' ],
				[ 'id' => 'mexico', 'searchExpression' => 'growtharticletopic:mexico' ],
				[ 'id' => 'chile', 'searchExpression' => 'growtharticletopic:chile' ],
				[ 'id' => 'argentina-expanded', 'searchExpression' => 'morelikethis:argentina' ]
			]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-glam-2022' ),
			[ 'argentina', 'mexico', 'chile' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsForCampaign( 'growth-argentina-2022' ),
			[ 'argentina', 'argentina-expanded' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-glam-2022' ),
			[ 'argentina-expanded' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign( 'growth-argentina-2022' ),
			[ 'mexico', 'chile' ]
		);
		$this->assertArrayEquals(
			$campaignConfig->getTopicsToExcludeForCampaign(),
			[ 'argentina', 'mexico', 'chile', 'argentina-expanded' ]
		);
	}
}
