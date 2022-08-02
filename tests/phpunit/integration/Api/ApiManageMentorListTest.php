<?php

namespace GrowthExperiments\Tests;

use ApiTestCase;
use ApiUsageException;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use User;

/**
 * @group API
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Api\ApiManageMentorList
 */
class ApiManageMentorListTest extends ApiTestCase {

	private function serializeMentor( Mentor $mentor ) {
		return [
			'message' => $mentor->hasCustomIntroText() ? $mentor->getIntroText() : null,
			'weight' => $mentor->getWeight(),
			'automaticallyAssigned' => $mentor->getAutoAssigned(),
		];
	}

	private function checkSuccessfulApiCall(
		array $apiParams,
		array $expectedMentorData,
		User $user
	) {
		$this->setMwGlobals( 'wgGEMentorProvider', MentorProvider::PROVIDER_STRUCTURED );

		$response = $this->doApiRequestWithToken(
			$apiParams,
			null,
			$user
		);
		$this->assertEquals( 'ok', $response[0]['growthmanagementorlist']['status'] );

		$provider = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorProviderStructured();
		$this->assertTrue( $provider->isMentor( $user ) );
		$this->assertArrayEquals(
			$expectedMentorData,
			$this->serializeMentor( $provider->newMentorFromUserIdentity( $user ) )
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testWrongProvider() {
		$this->setMwGlobals( 'wgGEMentorProvider', MentorProvider::PROVIDER_WIKITEXT );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Permission denied' );
		$this->doApiRequestWithToken( [
			'action' => 'growthmanagementorlist',
			'geaction' => 'add',
			'message' => 'intro',
			'autoassigned' => true,
			'weight' => MentorWeightManager::WEIGHT_NORMAL
		] );
	}

	/**
	 * @covers ::execute
	 */
	public function testNoPermissions() {
		$this->setMwGlobals( [
			'wgGEMentorProvider' => MentorProvider::PROVIDER_STRUCTURED,
			'wgRevokePermissions' => [ '*' => [ 'enrollasmentor' => true ] ],
			'wgGEMentorshipAutomaticEligibility' => false,
		] );
		$user = $this->getMutableTestUser()->getUser();

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to enroll as a mentor.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthmanagementorlist',
				'geaction' => 'add',
				'message' => 'intro',
				'autoassigned' => true,
				'weight' => MentorWeightManager::WEIGHT_NORMAL
			],
			null,
			$user
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testNoPermissionsChange() {
		$this->setMwGlobals( 'wgGEMentorProvider', MentorProvider::PROVIDER_STRUCTURED );
		$user = $this->getMutableTestUser()->getUser();

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to manage the list of mentors.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthmanagementorlist',
				'geaction' => 'change',
				'message' => 'intro',
				'autoassigned' => true,
				'weight' => MentorWeightManager::WEIGHT_NORMAL,
				'username' => 'FooUser',
			],
			null,
			$user
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testNonsenseGeAction() {
		$this->setMwGlobals( 'wgGEMentorProvider', MentorProvider::PROVIDER_STRUCTURED );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Unrecognized value for parameter "geaction": foobar.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthmanagementorlist',
				'geaction' => 'foobar',
				'message' => 'intro',
				'autoassigned' => false,
			],
			null,
			$this->getTestSysop()->getUser()
		);
	}

	/**
	 * @param array $permissions
	 * @covers ::execute
	 * @dataProvider provideAddPermissions
	 */
	public function testAddPermissions( array $permissions ) {
		$this->setGroupPermissions( [ 'mentors' => $permissions ] );

		$this->checkSuccessfulApiCall(
			[
				'action' => 'growthmanagementorlist',
				'geaction' => 'add',
				'message' => 'intro',
				'autoassigned' => true,
				'weight' => MentorWeightManager::WEIGHT_NORMAL
			],
			[
				'message' => 'intro',
				'autoassigned' => true,
				'weight' => MentorWeightManager::WEIGHT_NORMAL
			],
			$this->getMutableTestUser( 'mentors' )->getUser()
		);
	}

	public function provideAddPermissions() {
		return [
			'only enrollasmentor' => [ [ 'enrollasmentor' => true, 'managementors' => false, ] ],
			'only managementors' => [ [ 'enrollasmentor' => false, 'managementors' => true ] ],
			'both rights' => [ [ 'enrollasmentor' => true, 'managementors' => true ] ],
		];
	}

	/**
	 * @param array $params
	 * @covers ::execute
	 * @dataProvider provideAddDefaultValues
	 */
	public function testAddDefaultValues( array $params ) {
		$this->checkSuccessfulApiCall(
			$params + [
				'action' => 'growthmanagementorlist',
				'geaction' => 'add'
			],
			$params + [
				'message' => null,
				'autoassigned' => false,
				'weight' => MentorWeightManager::WEIGHT_NORMAL
			],
			$this->getMutableTestUser( 'sysop' )->getUser()
		);
	}

	public function provideAddDefaultValues() {
		return [
			'none' => [ [] ],
			'only message' => [ [ 'message' => 'foo' ] ],
			'only weight' => [ [ 'weight' => MentorWeightManager::WEIGHT_LOW ] ],
			'only autoassigned' => [ [ 'autoassigned' => true ] ],
		];
	}
}
