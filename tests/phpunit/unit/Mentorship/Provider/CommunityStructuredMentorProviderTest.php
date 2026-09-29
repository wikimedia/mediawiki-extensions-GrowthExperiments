<?php

namespace GrowthExperiments\Tests\Unit;

use ArrayIterator;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorProvider;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserSelectQueryBuilder;
use MediaWikiUnitTestCase;
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

	/**
	 * @return UserIdentity[] The mentor users as the UserIdentityLookup resolves them
	 */
	private static function getMentorUserIdentities(): array {
		$users = [];
		foreach ( self::USERNAME_MAP as $userId => $userName ) {
			$users[] = new UserIdentityValue( $userId, $userName );
		}
		return $users;
	}

	/**
	 * @return UserIdentityLookup|MockObject A lookup that refuses per-user resolution (T439638)
	 */
	private function getMockUserIdentityLookup() {
		$queryBuilder = $this->createMock( UserSelectQueryBuilder::class );
		$queryBuilder->method( 'whereUserIds' )->willReturnSelf();
		$queryBuilder->method( 'registered' )->willReturnSelf();
		$queryBuilder->method( 'caller' )->willReturnSelf();
		$queryBuilder->method( 'fetchUserIdentities' )
			->willReturn( new ArrayIterator( self::getMentorUserIdentities() ) );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'newSelectQueryBuilder' )->willReturn( $queryBuilder );
		$userIdentityLookup->expects( $this->never() )->method( 'getUserIdentityByUserId' );
		return $userIdentityLookup;
	}

	private function getWanCache(): WANObjectCache {
		return new WANObjectCache( [
			'cache' => new HashBagOStuff(),
			'logger' => new NullLogger(),
			'asyncHandler' => null,
		] );
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
		$queryBuilder = $this->createMock( UserSelectQueryBuilder::class );
		$queryBuilder->expects( $this->once() )->method( 'whereUserIds' )
			->with( array_keys( self::USERNAME_MAP ) )->willReturnSelf();
		$queryBuilder->method( 'registered' )->willReturnSelf();
		$queryBuilder->method( 'caller' )->willReturnSelf();
		$queryBuilder->method( 'fetchUserIdentities' )
			->willReturn( new ArrayIterator( self::getMentorUserIdentities() ) );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->expects( $this->once() )->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );
		$userIdentityLookup->expects( $this->never() )->method( 'getUserIdentityByUserId' );

		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$userIdentityLookup,
			$this->getMockMessageLocalizer(),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$this->getWanCache()
		);

		$this->assertSame( $expectedIds, array_keys( $provider->$methodToCall() ) );
	}

	public static function provideGetMentors() {
		return [
			[ [ 123, 456, 33, 789 ], 'getMentors' ],
			[ [ 123, 456 ], 'getAutoAssignedMentors' ],
			[ [ 33, 789 ], 'getManuallyAssignedMentors' ],
		];
	}

	public function testGetWeightedAutoAssignedMentors() {
		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$this->getMockUserIdentityLookup(),
			$this->getMockMessageLocalizer(),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$this->getWanCache()
		);

		$result = $provider->getWeightedAutoAssignedMentors();

		$names = array_map(
			static fn ( Mentor $mentor ) => $mentor->getUserIdentity()->getName(),
			$result
		);

		$expected = [
			'TestMentor1', 'TestMentor1',
			'TestMentor2', 'TestMentor2', 'TestMentor2', 'TestMentor2',
		];

		$this->assertSame( $expected, $names );
	}

	/**
	 * @param int $userId
	 * @param string $userName
	 * @param bool $expected
	 * @dataProvider provideIsMentor
	 */
	public function testIsMentor( int $userId, string $userName, bool $expected ) {
		$provider = new CommunityStructuredMentorProvider(
			new NullLogger(),
			$this->getMockUserIdentityLookup(),
			$this->getMockMessageLocalizer(),
			$this->getMockConfigurationProvider(),
			$this->getMockStatusFormatter(),
			$this->getWanCache()
		);

		$this->assertSame(
			$expected,
			$provider->isMentor( new UserIdentityValue( $userId, $userName ) )
		);
	}

	public static function provideIsMentor() {
		return [
			'automatically assigned mentor' => [ 123, 'TestMentor1', true ],
			'manually assigned mentor' => [ 33, 'TestMentor3', true ],
			'opted out mentor' => [ 789, 'TestMentor4', true ],
			'not a mentor' => [ 999, 'TestUser', false ],
			'anonymous user' => [ 0, '1.2.3.4', false ],
		];
	}
}
