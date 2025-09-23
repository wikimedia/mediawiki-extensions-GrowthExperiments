<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorProvider;
use Iterator;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserSelectQueryBuilder;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorProvider
 * @covers \GrowthExperiments\Mentorship\Provider\CommunityGetMentorDataTrait
 */
class CommunityStructuredMentorProviderTest extends MediaWikiUnitTestCase {
	private const TEST_MENTOR_DATA = [
		'Mentors' => [
			123 => [
				'message' => null,
				'weight' => 2,
				'username' => 'TestMentor1',
			],
			456 => [
				'message' => 'Custom intro',
				'weight' => 4,
				'username' => 'TestMentor2',
			],
			33 => [
				'message' => 'I only test mentorship',
				'weight' => 0,
				'username' => 'TestMentor3',
			],
			789 => [
				'message' => 'I am opted out despite having weight',
				'weight' => 3,
				'username' => 'TestMentor4',
				'automaticallyAssigned' => false,
			],
		],
	];

	private const USERNAME_MAP = [
		123 => 'TestMentor1',
		456 => 'TestMentor2',
		33 => 'TestMentor3',
		789 => 'TestMentor4',
	];

	private function getMockConfigurationProvider() {
		$provider = $this->createMock( IConfigurationProvider::class );
		$provider->method( 'loadValidConfiguration' )
			->willReturn( Status::newGood( self::TEST_MENTOR_DATA ) );
		return $provider;
	}

	private function getMockStatusFormatter() {
		$formatter = $this->createMock( StatusFormatter::class );
		$formatter->method( 'getPsr3MessageAndContext' )
			->willReturn( [ 'Error loading mentor list', [ 'context' => 'some context' ] ] );
		return $formatter;
	}

	/**
	 * @return MessageLocalizer|MockObject
	 */
	private function getMockMessageLocalizer() {
		$localizer = $this->getMockBuilder( MessageLocalizer::class )
			->onlyMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$localizer->method( 'msg' )
			->willReturnCallback( function ( $key, ...$params ) {
				$message = $this->createMock( Message::class );
				$message->method( 'exists' )->willReturn( true );
				$message->method( 'inContentLanguage' )->willReturnSelf();
				$message->method( 'params' )->willReturnSelf();
				$message->method( 'text' )->willReturnCallback(
					static function () use ( $key, $params ) {
						switch ( $key ) {
							case 'growthexperiments-homepage-mentorship-intro':
								return 'This experienced user knows you\'re new and can help you with editing';
							default:
								return $key;
						}
					}
				);
				return $message;
			} );
		return $localizer;
	}

	/**
	 * Test that mentor data is loaded correctly through CC provider
	 */
	public function testGetMentorData() {
		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( MessageLocalizer::class ),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$this->createMock( WANObjectCache::class )
		);

		$data = TestingAccessWrapper::newFromObject( $provider )->getMentorData();
		$this->assertArrayEquals( self::TEST_MENTOR_DATA['Mentors'], $data );
	}

	/**
	 * Test handling failed configuration load
	 */
	public function testGetMentorDataFailure() {
		$ccProvider = $this->createMock( IConfigurationProvider::class );
		$ccProvider->method( 'loadValidConfiguration' )
			->willReturn( Status::newFatal( 'some-error' ) );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'Error loading mentor list',
				[ 'context' => 'some context' ]
			);

		$provider = new CommunityStructuredMentorProvider(
			$logger,
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( MessageLocalizer::class ),
			$ccProvider,
			$this->getMockStatusFormatter(),
			$this->createMock( WANObjectCache::class )
		);

		$data = TestingAccessWrapper::newFromObject( $provider )->getMentorData();
		$this->assertSame( [], $data );
	}

	/**
	 * @param int $userId
	 * @param array|null $expectedData
	 * @dataProvider provideGetMentorDataForUser
	 */
	public function testGetMentorDataForUser( int $userId, ?array $expectedData ) {
		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( MessageLocalizer::class ),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$this->createMock( WANObjectCache::class )
		);

		$result = TestingAccessWrapper::newFromObject( $provider )->getMentorDataForUser(
			new UserIdentityValue( $userId, 'Mentor' )
		);

		if ( $expectedData === null ) {
			$this->assertNull( $result );
		} else {
			$this->assertArrayEquals(
				$expectedData,
				$result
			);
		}
	}

	/**
	 * Data provider for testGetMentorDataForUser
	 *
	 * @return array[]
	 */
	public static function provideGetMentorDataForUser() {
		return [
			[
				99,
				null,
			],
			[
				123,
				[
					'message' => null,
					'weight' => 2,
					'username' => 'TestMentor1',
				],
			],
			[
				456,
				[
					'message' => 'Custom intro',
					'weight' => 4,
					'username' => 'TestMentor2',
				],
			],
			[
				33,
				[
					'message' => 'I only test mentorship',
					'weight' => 0,
					'username' => 'TestMentor3',
				],
			],
		];
	}

	/**
	 * @dataProvider provideNewMentorFromUserIdentity
	 * @param int $userId
	 * @param string $expectedMessage
	 */
	public function testNewMentorFromUserIdentity( int $userId, string $expectedMessage ) {
		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$this->createMock( UserIdentityLookup::class ),
			$this->getMockMessageLocalizer(),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$this->createMock( WANObjectCache::class )
		);

		$mentor = $provider->newMentorFromUserIdentity(
			new UserIdentityValue( $userId, 'Mentor' )
		);

		$this->assertInstanceOf(
			Mentor::class,
			$mentor
		);
		$this->assertEquals(
			$expectedMessage,
			$mentor->getIntroText()
		);
	}

	public static function provideNewMentorFromUserIdentity() {
		return [
			[ 123, 'This experienced user knows you\'re new and can help you with editing' ],
			[ 456, 'Custom intro' ],
			[ 33, 'I only test mentorship' ],
			[ 999, 'This experienced user knows you\'re new and can help you with editing' ],
		];
	}

	/**
	 * @param int[] $expectedIds
	 * @param string $methodToCall
	 * @dataProvider provideGetMentors
	 */
	public function testGetMentors( array $expectedIds, string $methodToCall ) {
		$mockIterator = $this->createMock( Iterator::class );

		$queryBuilder = $this->createMock( UserSelectQueryBuilder::class );
		$queryBuilder->expects( $this->once() )->method( 'whereUserIds' )
			->with( $expectedIds )->willReturnSelf();
		$queryBuilder->method( 'registered' )->willReturnSelf();
		$queryBuilder->method( 'caller' )->willReturnSelf();
		$queryBuilder->method( 'fetchUserIdentities' )
			->willReturn( $mockIterator );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->expects( $this->once() )->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		$cache = new HashBagOStuff();
		$wanCache = new WANObjectCache( [
			'cache' => $cache,
			'logger' => new NullLogger(),
			'asyncHandler' => null,
		] );

		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$userIdentityLookup,
			$this->getMockMessageLocalizer(),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$wanCache
		);

		$provider->$methodToCall();
	}

	public static function provideGetMentors() {
		return [
			[ [ 123, 456, 33, 789 ], 'getMentors' ],
			[ [ 123, 456 ], 'getAutoAssignedMentors' ],
			[ [ 33, 789 ], 'getManuallyAssignedMentors' ],
		];
	}

	public function testGetWeightedAutoAssignedMentors() {
		$userIdentities = self::USERNAME_MAP;
		array_walk( $userIdentities, static function ( &$user, $userId ) {
			$user = new UserIdentityValue( $userId, $user );
		} );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->expects( $this->atLeastOnce() )
			->method( 'getUserIdentityByUserId' )
			->willReturnCallback( static function ( $userId ) use ( $userIdentities ) {
				return $userIdentities[$userId] ?? null;
			} );

		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$userIdentityLookup,
			$this->getMockMessageLocalizer(),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$this->createMock( WANObjectCache::class )
		);

		$result = $provider->getWeightedAutoAssignedMentors();

		$names = array_map( static fn ( UserIdentity $user ) => $user->getName(), $result );

		$expected = [
			'TestMentor1', 'TestMentor1',
			'TestMentor2', 'TestMentor2', 'TestMentor2', 'TestMentor2',
		];

		$this->assertEquals( $expected, $names );
	}
}
