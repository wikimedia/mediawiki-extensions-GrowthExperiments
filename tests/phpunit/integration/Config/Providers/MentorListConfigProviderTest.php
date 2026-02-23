<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Config\Providers\MentorListConfigProvider
 * @group Database
 */
class MentorListConfigProviderTest extends MediaWikiIntegrationTestCase {
	use CommunityConfigurationTestHelpers;

	public function testLoadsEmptyPage() {
		$this->getNonexistingTestPage( 'MediaWiki:GrowthMentors.json' );
		$provider = CommunityConfigurationServices::wrap( $this->getServiceContainer() )
			->getConfigurationProviderFactory()
			->newProvider( 'GrowthMentorList' );

		$status = $provider->loadValidConfiguration();
		$this->assertStatusOK( $status );
		$this->assertStatusValue( [ 'Mentors' => [] ], $status );
	}

	public static function provideLoadsFromInvalid() {
		return [
			'empty array' => [ [], [ 'Mentors' => [] ] ],
		];
	}

	/**
	 * @param array $expectedMentorList
	 * @param mixed $storedConfig
	 * @dataProvider provideLoadsFromInvalid
	 */
	public function testLoadsFromInvalid( array $expectedMentorList, mixed $storedConfig ) {
		// Several WMF wikis have GrowthMentors.json with content that would be invalid under
		// jsonschema. Verify such content can be still loaded.
		$this->overrideProviderConfig( $storedConfig, 'GrowthMentorList' );

		$provider = CommunityConfigurationServices::wrap( $this->getServiceContainer() )
			->getConfigurationProviderFactory()
			->newProvider( 'GrowthMentorList' );
		$status = $provider->loadValidConfiguration();
		$this->assertStatusOK( $status );
		$this->assertStatusValue( [ 'Mentors' => $expectedMentorList ], $status );
	}
}
