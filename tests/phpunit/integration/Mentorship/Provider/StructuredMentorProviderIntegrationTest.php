<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\Provider\LegacyStructuredMentorProvider
 * @covers \GrowthExperiments\Mentorship\Provider\AbstractStructuredMentorProvider
 * @group Database
 */
class StructuredMentorProviderIntegrationTest extends MediaWikiIntegrationTestCase {

	public function testNoMentorList() {
		// ensure the mentor list indeed doesn't exist at this point
		$this->overrideConfigValue( 'GEStructuredMentorList', 'MediaWiki:DoesNotExist.json' );
		$this->assertFalse( Title::newFromText( 'MediaWiki:DoesNotExist.json' )->exists() );

		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProviderStructured();

		$this->assertArrayEquals( [], $mentorProvider->getMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getWeightedAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getManuallyAssignedMentors() );
	}

	public function testEmptyMentorList() {
		$this->insertPage(
			'MediaWiki:GrowthMentors.json',
			FormatJson::encode( [
				'Mentors' => []
			] )
		);

		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProviderStructured();
		$this->assertArrayEquals( [], $mentorProvider->getMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getWeightedAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getManuallyAssignedMentors() );
	}

	public function testWithMentors() {
		$mentorAuto = $this->getMutableTestUser()->getUserIdentity();
		$mentorManual = $this->getMutableTestUser()->getUserIdentity();
		$this->insertPage(
			'MediaWiki:GrowthMentors.json',
			FormatJson::encode( [
				'Mentors' => [
					$mentorAuto->getId() => [
						'message' => null,
						'weight' => 2,
					],
					$mentorManual->getId() => [
						'message' => 'I only test mentorship',
						'weight' => 0,
					]
				]
			] )
		);

		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProviderStructured();
		$this->assertArrayEquals(
			[ $mentorAuto, $mentorManual ],
			$mentorProvider->getMentors()
		);
		$this->assertArrayEquals(
			[ $mentorAuto ],
			$mentorProvider->getAutoAssignedMentors()
		);
		$this->assertArrayEquals(
			[ $mentorManual ],
			$mentorProvider->getManuallyAssignedMentors()
		);
	}

	public function testGetWeightedAutoAssignedMentors() {
		$mentorAuto = $this->getMutableTestUser()->getUserIdentity();
		$mentorManual = $this->getMutableTestUser()->getUserIdentity();
		$this->insertPage(
			'MediaWiki:GrowthMentors.json',
			FormatJson::encode( [
				'Mentors' => [
					$mentorAuto->getId() => [
						'message' => null,
						'weight' => 2,
						'automaticallyAssigned' => true
					],
					$mentorManual->getId() => [
						'message' => 'I only test mentorship',
						'weight' => 2,
						'automaticallyAssigned' => false
					]
				]
			] )
		);

		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProviderStructured();
		$this->assertArrayEquals(
			[ $mentorAuto, $mentorAuto ],
			$mentorProvider->getWeightedAutoAssignedMentors()
		);
	}
}
