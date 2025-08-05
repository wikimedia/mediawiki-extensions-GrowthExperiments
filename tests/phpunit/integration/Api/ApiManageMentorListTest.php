<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\User;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group API
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Api\ApiManageMentorList
 */
class ApiManageMentorListTest extends ApiTestCase {
	use CommunityConfigurationTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->setMainCache( CACHE_NONE );
	}

	private function serializeMentor( Mentor $mentor ) {
		return [
			'message' => $mentor->hasCustomIntroText() ? $mentor->getIntroText() : null,
			'weight' => $mentor->getWeight(),
		];
	}

	private function checkSuccessfulApiCall(
		array $apiParams,
		array $expectedMentorData,
		User $user
	) {
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
	public function testNoPermissions() {
		$this->overrideConfigValues( [
			MainConfigNames::RevokePermissions => [ '*' => [ 'enrollasmentor' => true ] ],
		] );
		$this->overrideProviderConfig( [
			'GEMentorshipAutomaticEligibility' => false
		], 'Mentorship' );
		$user = $this->getMutableTestUser()->getUser();

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to enroll as a mentor.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthmanagementorlist',
				'geaction' => 'add',
				'message' => 'intro',
				'weight' => IMentorWeights::WEIGHT_NORMAL,
			],
			null,
			$user
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testNoPermissionsChange() {
		$this->overrideProviderConfig( [
			'GEMentorshipMinimumAge' => 0,
			'GEMentorshipMinimumEditcount' => 0,
		], 'Mentorship' );
		$user = $this->getMutableTestUser()->getUser();

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to manage the list of mentors.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthmanagementorlist',
				'geaction' => 'change',
				'message' => 'intro',
				'weight' => IMentorWeights::WEIGHT_NORMAL,
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
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Unrecognized value for parameter "geaction": foobar.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'growthmanagementorlist',
				'geaction' => 'foobar',
				'message' => 'intro',
				'weight' => IMentorWeights::WEIGHT_NONE,
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
				'weight' => IMentorWeights::WEIGHT_NORMAL,
			],
			[
				'message' => 'intro',
				'weight' => IMentorWeights::WEIGHT_NORMAL,
			],
			$this->getMutableTestUser( 'mentors' )->getUser()
		);
	}

	public static function provideAddPermissions() {
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
				'geaction' => 'add',
			],
			$params + [
				'message' => null,
				'weight' => IMentorWeights::WEIGHT_NORMAL,
			],
			$this->getMutableTestUser( 'sysop' )->getUser()
		);
	}

	public static function provideAddDefaultValues() {
		return [
			'none' => [ [] ],
			'only message' => [ [ 'message' => 'foo' ] ],
			'only weight' => [ [ 'weight' => IMentorWeights::WEIGHT_LOW ] ],
		];
	}

	/**
	 * @covers ::execute
	 * @dataProvider provideTestMentorStatus
	 */
	public function testMentorStatus(
		string $expectedStatus,
		?string $expectedTimestamp,
		array $params,
	) {
		ConvertibleTimestamp::setFakeTime( strtotime( '2011-04-01T00:00Z' ) );

		$mentorStatusManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStatusManager();

		$mentorUser = $this->getMutableTestUser()->getUser();
		$this->doApiRequestWithToken(
			[
				'action' => 'growthmanagementorlist',
				'message' => 'intro',
				'weight' => IMentorWeights::WEIGHT_NORMAL,
				'username' => $mentorUser->getName(),
				...$params
			],
			null,
			$this->getTestSysop()->getUser()
		);
		$this->assertSame(
			$expectedStatus,
			$mentorStatusManager->getMentorStatus( $mentorUser )
		);
		$this->assertSame(
			$expectedTimestamp,
			$mentorStatusManager->getMentorBackTimestamp( $mentorUser )
		);
	}

	private function provideTestMentorStatus() {
		return [
			[ MentorStatusManager::STATUS_AWAY, '20110425184738', [
				'geaction' => 'add',
				'isaway' => true,
				'awaytimestamp' => '2011-04-25T18:47:38.000Z',
			] ],
			[ MentorStatusManager::STATUS_ACTIVE, null, [
				'geaction' => 'add',
			] ]
		];
	}
}
