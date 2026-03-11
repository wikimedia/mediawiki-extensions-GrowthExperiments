<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\Json\FormatJson;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Config\Providers\MentorListConfigProvider
 * @group Database
 */
class MentorListConfigProviderTest extends MediaWikiIntegrationTestCase {
	use CommunityConfigurationTestHelpers;

	public static function provideValidMentorList() {
		return [
			'empty object' => [ '{"Mentors": {}}' ],
		];
	}

	/**
	 * @dataProvider provideValidMentorList
	 */
	public function testValidMentorList( string $validMentorList ) {
		$status = $this->editPage(
			'MediaWiki:GrowthMentors.json',
			$validMentorList
		);
		$this->assertStatusOK( $status );
	}

	public static function provideInvalidMentors() {
		return [
			'too long message' => [
				[ 'username' => 'Mentor1', 'weight' => 0, 'message' => str_repeat( 'a', 1001 ) ],
			],
		];
	}

	/**
	 * @dataProvider provideInvalidMentors
	 */
	public function testInvalidMentors( array $invalidMentor ) {
		$status = $this->editPage(
			'MediaWiki:GrowthMentors.json',
			FormatJson::encode( [ 'Mentors' => [
				1 => $invalidMentor,
			] ] )
		);

		$this->assertStatusError( 'communityconfiguration-schema-validation-error', $status );
	}

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
