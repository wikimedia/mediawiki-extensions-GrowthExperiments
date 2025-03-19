<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Extension\CommunityConfiguration\Store\IConfigurationStore;
use MediaWiki\Extension\CommunityConfiguration\Store\WikiPageStore;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * @covers \GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter
 * @covers \GrowthExperiments\Mentorship\Provider\AbstractStructuredMentorWriter
 * @covers \GrowthExperiments\Mentorship\Provider\CommunityGetMentorDataTrait
 */
class CommunityStructuredMentorWriterTest extends MediaWikiUnitTestCase {
	private const MENTOR_LIST_CONTENT = [
		123 => [
			'username' => 'ExistingMentor',
			'message' => null,
			'weight' => 2,
		],
	];

	private const DEFAULT_INTRO_TEXT = 'This is default intro';

	/**
	 * @param int $userId
	 * @param string|null $introText
	 * @param int $weight
	 * @return Mentor
	 */
	private static function getMentor(
		int $userId,
		?string $introText,
		int $weight
	): Mentor {
		return new Mentor(
			new UserIdentityValue( $userId, $userId === 123 ? 'ExistingMentor' : 'Mentor' ),
			$introText,
			self::DEFAULT_INTRO_TEXT,
			$weight
		);
	}

	/**
	 * @param array $mentorData
	 * @return IConfigurationProvider|MockObject
	 */
	private function getMockConfigurationProvider( array $mentorData = [] ) {
		$provider = $this->createMock( IConfigurationProvider::class );
		$provider->method( 'loadValidConfiguration' )
			->willReturn( Status::newGood( [
				'Mentors' => $mentorData ?: self::MENTOR_LIST_CONTENT
			] ) );

		return $provider;
	}

	private function getMockStatusFormatter() {
		$formatter = $this->createMock( StatusFormatter::class );
		$formatter->method( 'getPsr3MessageAndContext' )
			->willReturn( [ 'Error loading mentor list', [ 'context' => 'some context' ] ] );
		return $formatter;
	}

	/**
	 * @param bool $isBlocked Whether user should appear blocked
	 * @param bool $isNamed Whether user should appear as named
	 * @return UserFactory|MockObject
	 */
	private function getUserFactoryMock( bool $isBlocked = false, bool $isNamed = true ) {
		$blockMock = $this->createMock( AbstractBlock::class );
		$blockMock->method( 'appliesToTitle' )
			->willReturn( $isBlocked );

		$userMock = $this->createMock( User::class );
		$userMock->method( 'getBlock' )
			->willReturn( $isBlocked ? $blockMock : null );
		$userMock->method( 'isNamed' )
			->willReturn( $isNamed );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $userMock );

		return $userFactory;
	}

	/**
	 * @param UserIdentity|null $user
	 * @return UserIdentityLookup|MockObject
	 */
	private function getUserIdentityLookupMock( ?UserIdentity $user = null ) {
		$lookup = $this->createMock( UserIdentityLookup::class );
		$lookup->method( 'getUserIdentityByUserId' )
			->willReturnCallback( static function ( $userId ) use ( $user ) {
				if ( $user ) {
					return $user;
				}
				return new UserIdentityValue(
					$userId,
					$userId === 123 ? 'ExistingMentor' : 'Mentor'
				);
			} );
		return $lookup;
	}

	/**
	 * Test all mentor addition scenarios
	 *
	 * @dataProvider provideAddMentorCases
	 * @param array $mentorData The mentor data (id, intro, weight)
	 * @param bool $isBlocked Whether the performer is blocked
	 * @param bool $isNamed Whether the user is a named user (vs anonymous)
	 * @param string $expectedResult Expected result type ('success' or error key)
	 */
	public function testAddMentor( array $mentorData, bool $isBlocked, bool $isNamed, string $expectedResult ) {
		$mentor = self::getMentor(
			$mentorData['id'],
			$mentorData['intro'] ?? null,
			$mentorData['weight'] ?? IMentorWeights::WEIGHT_NORMAL
		);

		$ccProvider = $this->createMock( IConfigurationProvider::class );

		if ( $isNamed && !$isBlocked ) {
			$ccProvider->method( 'loadValidConfiguration' )
				->willReturn( Status::newGood( [ 'Mentors' => self::MENTOR_LIST_CONTENT ] ) );
		}

		if ( $expectedResult === 'success' ) {
			$expectedData = [
				'Mentors' => [
					123 => [
						'username' => 'ExistingMentor',
						'message' => null,
						'weight' => 2,
					],
					$mentorData['id'] => [
						'username' => 'Mentor',
						'message' => $mentorData['intro'] ?? null,
						'weight' => $mentorData['weight'] ?? IMentorWeights::WEIGHT_NORMAL,
					],
				],
			];

			$ccProvider->expects( $this->once() )
				->method( 'alwaysStoreValidConfiguration' )
				->with(
					$expectedData,
					$this->isInstanceOf( User::class ),
					$this->stringContains( 'Add mentor' )
				)
				->willReturn( StatusValue::newGood() );
		} else {
			$ccProvider->expects( $this->never() )
				->method( 'alwaysStoreValidConfiguration' );
		}

		$writer = new CommunityStructuredMentorWriter(
			$this->createMock( MentorProvider::class ),
			$this->getUserIdentityLookupMock(),
			$this->getUserFactoryMock( $isBlocked, $isNamed ),
			$this->getMockStatusFormatter(),
			$ccProvider
		);

		$writer->setLogger( $this->createMock( LoggerInterface::class ) );

		$result = $writer->addMentor(
			$mentor,
			new UserIdentityValue( 999, 'Performer' ),
			'Add mentor'
		);

		if ( $expectedResult === 'success' ) {
			$this->assertTrue( $result->isOK() );
		} else {
			$this->assertStatusError(
				'growthexperiments-mentor-writer-error-' . $expectedResult,
				$result
			);
		}
	}

	/**
	 * Data provider for testAddMentor
	 *
	 * @return array[] Test cases
	 */
	public function provideAddMentorCases() {
		return [
			'Success case' => [
				[ 'id' => 456, 'intro' => 'Custom intro', 'weight' => IMentorWeights::WEIGHT_HIGH ],
				false,
				true,
				'success'
			],
			'Temporary user' => [
				[ 'id' => 0 ],
				false,
				false,
				'anonymous-user'
			],
			'Already exists' => [
				[ 'id' => 123 ],
				false,
				true,
				'already-added'
			],
			'Blocked performer' => [
				[ 'id' => 456 ],
				true,
				true,
				'blocked'
			],
		];
	}

	/**
	 * Test mentor removal scenarios
	 *
	 * @dataProvider provideRemoveMentorCases
	 * @param int $mentorId Mentor ID to remove
	 * @param bool $expectSave Whether to expect a save operation
	 * @param string|null $expectedError Expected error message key (null for success)
	 */
	public function testRemoveMentor( int $mentorId, bool $expectSave, ?string $expectedError ) {
		$mentor = self::getMentor( $mentorId, null, IMentorWeights::WEIGHT_NORMAL );
		$ccProvider = $this->getMockConfigurationProvider();

		if ( $expectSave ) {
			$ccProvider->expects( $this->once() )
				->method( 'alwaysStoreValidConfiguration' )
				->with(
					$this->callback( static function ( $data ) use ( $mentorId ) {
						return is_array( $data['Mentors'] ) && !isset( $data['Mentors'][$mentorId] );
					} ),
					$this->isInstanceOf( User::class ),
					$this->stringContains( 'Remove mentor' )
				)
				->willReturn( StatusValue::newGood() );
		} else {
			$ccProvider->expects( $this->never() )
				->method( 'alwaysStoreValidConfiguration' );
		}

		$writer = new CommunityStructuredMentorWriter(
			$this->createMock( MentorProvider::class ),
			$this->getUserIdentityLookupMock(),
			$this->getUserFactoryMock(),
			$this->getMockStatusFormatter(),
			$ccProvider
		);

		$writer->setLogger( $this->createMock( LoggerInterface::class ) );

		$result = $writer->removeMentor(
			$mentor,
			new UserIdentityValue( 999, 'Performer' ),
			'Remove mentor'
		);

		if ( $expectedError === null ) {
			$this->assertTrue( $result->isOK() );
		} else {
			$this->assertStatusError( $expectedError, $result );
		}
	}

	/**
	 * Data provider for testRemoveMentor
	 *
	 * @return array[] Test cases
	 */
	public function provideRemoveMentorCases() {
		return [
			'Existing mentor' => [
				123,
				true,
				null
			],
			'Non-existent mentor' => [
				456,
				false,
				'growthexperiments-mentor-writer-error-not-in-the-list'
			],
		];
	}

	/**
	 * Test changing a mentor
	 */
	public function testChangeMentor() {
		$mentor = self::getMentor( 123, 'Updated intro', IMentorWeights::WEIGHT_LOW );

		$ccProvider = $this->getMockConfigurationProvider();

		// Check data is saved with updated properties
		$ccProvider->expects( $this->once() )
			->method( 'alwaysStoreValidConfiguration' )
			->with(
				$this->callback( static function ( $data ) {
					return isset( $data['Mentors'][123] ) &&
						$data['Mentors'][123]['message'] === 'Updated intro' &&
						$data['Mentors'][123]['weight'] === IMentorWeights::WEIGHT_LOW;
				} ),
				$this->isInstanceOf( User::class ),
				$this->stringContains( 'Change mentor' )
			)
			->willReturn( StatusValue::newGood() );

		$writer = new CommunityStructuredMentorWriter(
			$this->createMock( MentorProvider::class ),
			$this->getUserIdentityLookupMock(),
			$this->getUserFactoryMock(),
			$this->getMockStatusFormatter(),
			$ccProvider
		);

		$writer->setLogger( $this->createMock( LoggerInterface::class ) );

		$result = $writer->changeMentor(
			$mentor,
			new UserIdentityValue( 999, 'Performer' ),
			'Change mentor'
		);

		$this->assertTrue( $result->isOK() );
	}

	/**
	 * Test touchList functionality
	 */
	public function testTouchList() {
		$mentorProvider = $this->createMock( MentorProvider::class );
		$mentorProvider->method( 'newMentorFromUserIdentity' )
			->willReturn( self::getMentor(
				123, 'Refreshed message', IMentorWeights::WEIGHT_NORMAL ) );

		$ccProvider = $this->getMockConfigurationProvider();

		// Check data is refreshed from mentorProvider
		$ccProvider->expects( $this->once() )
			->method( 'alwaysStoreValidConfiguration' )
			->with(
				$this->callback( static function ( $data ) {
					return isset( $data['Mentors'][123] ) &&
						$data['Mentors'][123]['message'] === 'Refreshed message';
				} ),
				$this->isInstanceOf( User::class ),
				'Refresh list'
			)
			->willReturn( StatusValue::newGood() );

		$writer = new CommunityStructuredMentorWriter(
			$mentorProvider,
			$this->getUserIdentityLookupMock(),
			$this->getUserFactoryMock(),
			$this->getMockStatusFormatter(),
			$ccProvider
		);

		$writer->setLogger( $this->createMock( LoggerInterface::class ) );

		$result = $writer->touchList(
			new UserIdentityValue( 999, 'Performer' ),
			'Refresh list'
		);

		$this->assertTrue( $result->isOK() );
	}

	/**
	 * Test isBlocked method
	 * @dataProvider provideIsBlocked
	 */
	public function testIsBlocked( bool $isBlocked, bool $isWikiPageStore ) {
		if ( $isWikiPageStore ) {
			$store = $this->createMock( WikiPageStore::class );
			$configTitle = $this->createMock( Title::class );
			$store->method( 'getConfigurationTitle' )
				->willReturn( $configTitle );
		} else {
			$store = $this->createMock( IConfigurationStore::class );
		}

		$ccProvider = $this->createMock( IConfigurationProvider::class );
		$ccProvider->method( 'getStore' )
			->willReturn( $store );

		$writer = new CommunityStructuredMentorWriter(
			$this->createMock( MentorProvider::class ),
			$this->getUserIdentityLookupMock(),
			$this->getUserFactoryMock( $isBlocked ),
			$this->getMockStatusFormatter(),
			$ccProvider
		);

		$writer->setLogger( $this->createMock( LoggerInterface::class ) );

		$result = $writer->isBlocked(
			new UserIdentityValue( 999, 'Performer' ) );

		$this->assertSame( $isBlocked, $result );
	}

	public static function provideIsBlocked() {
		return [
			'Not blocked, WikiPageStore' => [ false, true ],
			'Blocked, WikiPageStore' => [ true, true ],
			'Not blocked, other store' => [ false, false ],
			'Blocked, other store' => [ true, false ],
		];
	}
}
