<?php

namespace GrowthExperiments\Tests;

use FormatJson;
use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\Provider\StructuredMentorProvider
 * @group Database
 */
class StructuredMentorProviderIntegrationTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::getMentors
	 * @covers ::getMentorsSafe
	 * @covers ::getAutoAssignedMentors
	 * @covers ::getWeightedAutoAssignedMentors
	 * @covers ::getManuallyAssignedMentors
	 */
	public function testNoMentorList() {
		// ensure the mentor list indeed doesn't exist at this point
		$this->setMwGlobals( 'wgGEStructuredMentorList', 'MediaWiki:DoesNotExist.json' );
		$this->assertFalse( Title::newFromText( 'MediaWiki:DoesNotExist.json' )->exists() );

		$mentorProvider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProviderStructured();

		$this->assertArrayEquals( [], $mentorProvider->getMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getMentorsSafe() );
		$this->assertArrayEquals( [], $mentorProvider->getAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getWeightedAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getManuallyAssignedMentors() );
	}

	/**
	 * @covers ::getMentors
	 * @covers ::getMentorsSafe
	 * @covers ::getAutoAssignedMentors
	 * @covers ::getManuallyAssignedMentors
	 */
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
		$this->assertArrayEquals( [], $mentorProvider->getMentorsSafe() );
		$this->assertArrayEquals( [], $mentorProvider->getAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getWeightedAutoAssignedMentors() );
		$this->assertArrayEquals( [], $mentorProvider->getManuallyAssignedMentors() );
	}

	/**
	 * @covers ::getMentors
	 * @covers ::getMentorsSafe
	 * @covers ::getAutoAssignedMentors
	 * @covers ::getManuallyAssignedMentors
	 */
	public function testWithMentors() {
		$mentorAuto = $this->getMutableTestUser()->getUser();
		$mentorManual = $this->getMutableTestUser()->getUser();
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
			[ $mentorAuto->getName(), $mentorManual->getName() ],
			$mentorProvider->getMentors()
		);
		$this->assertArrayEquals(
			[ $mentorAuto->getName(), $mentorManual->getName() ],
			$mentorProvider->getMentorsSafe()
		);
		$this->assertArrayEquals(
			[ $mentorAuto->getName() ],
			$mentorProvider->getAutoAssignedMentors()
		);
		$this->assertArrayEquals(
			[ $mentorManual->getName() ],
			$mentorProvider->getManuallyAssignedMentors()
		);
	}

	/**
	 * @covers ::getWeightedAutoAssignedMentors
	 */
	public function testGetWeightedAutoAssignedMentors() {
		$mentorAuto = $this->getMutableTestUser()->getUser();
		$mentorManual = $this->getMutableTestUser()->getUser();
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
			[ $mentorAuto->getName(), $mentorAuto->getName() ],
			$mentorProvider->getWeightedAutoAssignedMentors()
		);
	}
}
