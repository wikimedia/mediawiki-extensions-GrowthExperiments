<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\LegacyStructuredMentorProvider;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserSelectQueryBuilder;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\Mentorship\Provider\LegacyStructuredMentorProvider
 * @covers \GrowthExperiments\Mentorship\Provider\AbstractStructuredMentorProvider
 */
class LegacyStructuredMentorProviderTest extends MediaWikiUnitTestCase {

	private const MENTOR_LIST_CONTENT = [
		123 => [
			'message' => null,
			'weight' => 2,
			'automaticallyAssigned' => true,
		],
		42 => [
			'message' => 'This is my introduction',
			'weight' => 4,
			'automaticallyAssigned' => true,
		],
		33 => [
			'message' => 'I only test mentorship',
			'weight' => 1,
			'automaticallyAssigned' => false,
		],
		12 => [
			'message' => null,
			'weight' => 1,
			'automaticallyAssigned' => true,
		],
	];

	private const USERNAME_MAP = [
		123 => 'Jane',
		42 => 'Peter',
		33 => 'Susan',
		12 => 'Robert',
	];

	/**
	 * Mock WikiPageConfigLoader
	 *
	 * Load will return the content of MENTOR_LIST_CONTENT.
	 *
	 * @param mixed $mentorList
	 * @return WikiPageConfigLoader|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getWikiConfigLoaderMock( $mentorList = null ) {
		$configLoader = $this->createMock( WikiPageConfigLoader::class );
		$configLoader->expects( $this->atLeastOnce() )
			->method( 'load' )
			->willReturn( $mentorList ?? [
				'Mentors' => self::MENTOR_LIST_CONTENT
			] );
		return $configLoader;
	}

	public function testGetMentorDataSuccess() {
		$provider = new LegacyStructuredMentorProvider(
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( MessageLocalizer::class ),
			$this->getWikiConfigLoaderMock(),
			$this->createNoOpMock( Title::class )
		);

		$this->assertArrayEquals(
			self::MENTOR_LIST_CONTENT,
			TestingAccessWrapper::newFromObject( $provider )->getMentorData()
		);
	}

	public function testGetMentorDataFailure() {
		$statusMock = $this->createMock( Status::class );
		$statusMock->expects( $this->once() )
			->method( 'getWikiText' )
			->willReturn( '' );

		$provider = new LegacyStructuredMentorProvider(
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( MessageLocalizer::class ),
			$this->getWikiConfigLoaderMock( $statusMock ),
			$this->createNoOpMock( Title::class )
		);

		$this->assertArrayEquals(
			[],
			TestingAccessWrapper::newFromObject( $provider )->getMentorData()
		);
	}

	/**
	 * @param int $userId
	 * @param array|null $expectedData
	 * @dataProvider provideGetMentorDataForUser
	 */
	public function testGetMentorDataForUser( int $userId, ?array $expectedData ) {
		$provider = new LegacyStructuredMentorProvider(
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( MessageLocalizer::class ),
			$this->getWikiConfigLoaderMock(),
			$this->createNoOpMock( Title::class )
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
					'automaticallyAssigned' => true
				]
			],
			[
				12,
				[
					'message' => null,
					'weight' => 1,
					'automaticallyAssigned' => true
				]
			],
			[
				33,
				[
					'message' => 'I only test mentorship',
					'weight' => 1,
					'automaticallyAssigned' => false,
				]
			],
		];
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
	 * @dataProvider provideNewMentorFromUserIdentity
	 * @param int $userId
	 * @param string $expectedMessage
	 */
	public function testNewMentorFromUserIdentity( int $userId, string $expectedMessage ) {
		$provider = new LegacyStructuredMentorProvider(
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->getMockMessageLocalizer(),
			$this->getWikiConfigLoaderMock(),
			$this->createNoOpMock( Title::class )
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
			[ 12, 'This experienced user knows you\'re new and can help you with editing' ],
			[ 33, 'I only test mentorship' ],
			[ 42, 'This is my introduction' ],
			[ 123, 'This experienced user knows you\'re new and can help you with editing' ],
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
			->with( $expectedIds )->willReturnSelf();
		$queryBuilder->method( 'registered' )->willReturnSelf();
		$queryBuilder->method( 'caller' )->willReturnSelf();

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->expects( $this->once() )->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		$provider = new LegacyStructuredMentorProvider(
			$userIdentityLookup,
			$this->getMockMessageLocalizer(),
			$this->getWikiConfigLoaderMock(),
			$this->createNoOpMock( Title::class )
		);
		$provider->$methodToCall();
	}

	public static function provideGetMentors() {
		return [
			[ [ 123, 42, 33, 12 ], 'getMentors' ],
			[ [ 123, 42, 12 ], 'getAutoAssignedMentors' ],
			[ [ 33 ], 'getManuallyAssignedMentors' ],
		];
	}

	public function testGetWeightedAutoAssignedMentors() {
		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->expects( $this->atLeastOnce() )
			->method( 'getUserIdentityByUserId' )
			->willReturnCallback( static function ( $userId ) {
				if ( !array_key_exists( $userId, self::USERNAME_MAP ) ) {
					return null;
				}

				return new UserIdentityValue(
					$userId,
					self::USERNAME_MAP[$userId]
				);
			} );

		$provider = new LegacyStructuredMentorProvider(
			$userIdentityLookup,
			$this->getMockMessageLocalizer(),
			$this->getWikiConfigLoaderMock(),
			$this->createNoOpMock( Title::class )
		);

		$this->assertArrayEquals(
			[ 'Jane', 'Jane', 'Peter', 'Peter', 'Peter', 'Peter', 'Robert' ],
			$provider->getWeightedAutoAssignedMentors()
		);
	}
}
