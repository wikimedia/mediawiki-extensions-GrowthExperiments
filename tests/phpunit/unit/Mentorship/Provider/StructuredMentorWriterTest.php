<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Status;
use User;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\Provider\StructuredMentorWriter
 */
class StructuredMentorWriterTest extends MediaWikiUnitTestCase {

	private const MENTOR_LIST_CONTENT = [
		123 => [
			'username' => 'Mentor',
			'message' => null,
			'weight' => 2,
			'automaticallyAssigned' => true,
		],
	];

	private const DEFAULT_INTRO_TEXT = 'This is default intro';

	private Title $mentorList;

	protected function setUp(): void {
		parent::setUp();
		$this->mentorList = $this->createNoOpMock( Title::class );
	}

	/**
	 * @param int $userId
	 * @param string|null $introText
	 * @param bool $autoAssigned
	 * @param int $weight
	 * @return Mentor
	 */
	private static function getMentor(
		int $userId,
		?string $introText,
		bool $autoAssigned,
		int $weight
	): Mentor {
		return new Mentor(
			new UserIdentityValue( $userId, 'Mentor' ),
			$introText,
			self::DEFAULT_INTRO_TEXT,
			$autoAssigned,
			$weight
		);
	}

	/**
	 * @return UserIdentityLookup|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getUserIdentityLookupMock() {
		$userIdentityLookupMock = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookupMock->expects( $this->atLeastOnce() )
			->method( 'getUserIdentityByUserId' )
			->willReturn( new UserIdentityValue( 123, 'Mentor' ) );
		return $userIdentityLookupMock;
	}

	/**
	 * @param bool $expectCall
	 * @param bool $isBlocked
	 * @return UserFactory|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getUserFactoryMock( bool $expectCall, bool $isBlocked = false ) {
		$blockMock = $this->createMock( AbstractBlock::class );
		$blockMock->expects( $expectCall ? $this->once() : $this->never() )
			->method( 'appliesToTitle' )
			->willReturnCallback( function ( $title ) use ( $isBlocked ) {
				if ( !$isBlocked ) {
					return false;
				}

				return $title === $this->mentorList;
			} );

		$userMock = $this->createMock( User::class );
		$userMock->expects( $expectCall ? $this->once() : $this->never() )
			->method( 'getBlock' )
			->willReturn( $blockMock );
		$userMock
			->method( 'isNamed' )
			->willReturn( true );

		$userFactoryMock = $this->createMock( UserFactory::class );
		$userFactoryMock
			->method( 'newFromUserIdentity' )
			->willReturn( $userMock );
		return $userFactoryMock;
	}

	/**
	 * @param bool $loadExpected Is a call to load() expected?
	 * @return WikiPageConfigLoader|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getWikiConfigLoaderMock( bool $loadExpected = true ) {
		$configLoader = $this->createMock( WikiPageConfigLoader::class );
		$configLoader->expects( $loadExpected ? $this->once() : $this->never() )
			->method( 'load' )
			->willReturn( [
				'Mentors' => self::MENTOR_LIST_CONTENT
			] );
		return $configLoader;
	}

	/**
	 * @param array $expectedMentorList
	 * @param string $expectedSummary
	 * @return WikiPageConfigWriterFactory|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getWikiPageWriterFactoryMock(
		array $expectedMentorList,
		string $expectedSummary
	) {
		$status = $this->createMock( Status::class );
		$status->method( 'isOK' )
			->willReturn( true );

		$writer = $this->createMock( WikiPageConfigWriter::class );
		$writer->expects( $this->once() )
			->method( 'setVariable' )
			->with( StructuredMentorWriter::CONFIG_KEY, $expectedMentorList );
		$writer->expects( $this->once() )
			->method( 'save' )
			->with( $expectedSummary, false, StructuredMentorWriter::CHANGE_TAG )
			->willReturn( $status );

		$factory = $this->createMock( WikiPageConfigWriterFactory::class );
		$factory->expects( $this->once() )
			->method( 'newWikiPageConfigWriter' )
			->willReturn( $writer );
		return $factory;
	}

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			StructuredMentorWriter::class,
			new StructuredMentorWriter(
				$this->createNoOpMock( UserIdentityLookup::class ),
				$this->createNoOpMock( UserFactory::class ),
				$this->createNoOpMock( WikiPageConfigLoader::class ),
				$this->createNoOpMock( WikiPageConfigWriterFactory::class ),
				$this->createNoOpMock( StructuredMentorListValidator::class ),
				$this->mentorList
			)
		);
	}

	/**
	 * @param Mentor $mentor
	 * @param bool $isBlocked
	 * @param array $expectedNewMentor
	 * @param string|null $expectedError
	 * @param bool $expectLoad
	 * @covers ::addMentor
	 * @covers ::getMentorData
	 * @covers ::saveMentorData
	 * @covers ::serializeMentor
	 * @dataProvider provideAddMentor
	 */
	public function testAddMentor(
		Mentor $mentor,
		bool $isBlocked,
		array $expectedNewMentor,
		?string $expectedError,
		bool $expectLoad
	) {
		if ( $expectedError === null ) {
			$expectedMentorList = self::MENTOR_LIST_CONTENT;
			$expectedMentorList[$mentor->getUserIdentity()->getId()] = $expectedNewMentor;
			$configWriterFactory = $this->getWikiPageWriterFactoryMock( $expectedMentorList, 'Add mentor' );

			$userIdentityLookup = $this->getUserIdentityLookupMock();
		} else {
			$configWriterFactory = $this->createNoOpMock( WikiPageConfigWriterFactory::class );
			$userIdentityLookup = $this->createNoOpMock( UserIdentityLookup::class );
		}
		$mentorWriter = new StructuredMentorWriter(
			$userIdentityLookup,
			$this->getUserFactoryMock( $isBlocked || $expectedError === null, $isBlocked ),
			$this->getWikiConfigLoaderMock( $expectLoad ),
			$configWriterFactory,
			new StructuredMentorListValidator(),
			$this->mentorList
		);

		$status = $mentorWriter->addMentor(
			$mentor,
			$this->createNoOpMock( UserIdentity::class ),
			'Add mentor'
		);
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isOK() );
		} else {
			$this->assertFalse( $status->isOK() );
			$this->assertTrue( $status->hasMessage( $expectedError ) );
		}
	}

	public static function provideAddMentor() {
		return [
			[
				'mentor' => self::getMentor( 1, null, true, 2 ),
				'isBlocked' => false,
				'expectedNewMentor' => [
					'username' => 'Mentor',
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => null,
				'expectLoad' => true,
			],
			[
				'mentor' => self::getMentor( 123, null, true, 2 ),
				'isBlocked' => false,
				'expectedNewMentor' => [
					'username' => 'Mentor',
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-already-added',
				'expectLoad' => true,
			],
			[
				'mentor' => self::getMentor( 0, null, true, 2 ),
				'isBlocked' => false,
				'expectedNewMentor' => [
					'username' => 'Mentor',
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-anonymous-user',
				'expectLoad' => false,
			],
			[
				'mentor' => self::getMentor( 2, null, true, 2 ),
				'isBlocked' => true,
				'expectedNewMentor' => [
					'username' => 'Mentor',
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-blocked',
				'expectLoad' => true,
			]
		];
	}

	/**
	 * @param Mentor $mentor
	 * @param string|null $expectedError
	 * @covers ::removeMentor
	 * @covers ::saveMentorData
	 * @covers ::getMentorData
	 * @dataProvider provideRemoveMentor
	 */
	public function testRemoveMentor( Mentor $mentor, ?string $expectedError ) {
		if ( $expectedError === null ) {
			$expectedMentorList = self::MENTOR_LIST_CONTENT;
			unset( $expectedMentorList[$mentor->getUserIdentity()->getId()] );
			$configWriterFactory = $this->getWikiPageWriterFactoryMock( $expectedMentorList, 'Remove mentor' );
		} else {
			$configWriterFactory = $this->createNoOpMock( WikiPageConfigWriterFactory::class );
		}
		$mentorWriter = new StructuredMentorWriter(
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->getUserFactoryMock( $expectedError === null ),
			$this->getWikiConfigLoaderMock(),
			$configWriterFactory,
			new StructuredMentorListValidator(),
			$this->mentorList
		);

		$status = $mentorWriter->removeMentor(
			$mentor,
			$this->createNoOpMock( UserIdentity::class ),
			'Remove mentor'
		);
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isOK() );
		} else {
			$this->assertFalse( $status->isOK() );
			$this->assertTrue( $status->hasMessage( $expectedError ) );
		}
	}

	public static function provideRemoveMentor() {
		return [
			[
				'mentor' => self::getMentor( 123, null, true, 2 ),
				'expectedError' => null,
			],
			[
				'mentor' => self::getMentor( 1, null, true, 2 ),
				'expectedError' => 'growthexperiments-mentor-writer-error-not-in-the-list',
			],
		];
	}

	/**
	 * @param Mentor $mentor
	 * @param array $expectedNewMentor
	 * @param string|null $expectedError
	 * @covers ::changeMentor
	 * @covers ::getMentorData
	 * @covers ::saveMentorData
	 * @covers ::serializeMentor
	 * @dataProvider provideChangeMentor
	 */
	public function testChangeMentor(
		Mentor $mentor,
		array $expectedNewMentor,
		?string $expectedError
	) {
		if ( $expectedError === null ) {
			$expectedMentorList = self::MENTOR_LIST_CONTENT;
			$expectedMentorList[$mentor->getUserIdentity()->getId()] = $expectedNewMentor;
			$configWriterFactory = $this->getWikiPageWriterFactoryMock( $expectedMentorList, 'Change mentor' );

			$userIdentityLookup = $this->getUserIdentityLookupMock();
		} else {
			$configWriterFactory = $this->createNoOpMock( WikiPageConfigWriterFactory::class );
			$userIdentityLookup = $this->createNoOpMock( UserIdentityLookup::class );
		}
		$mentorWriter = new StructuredMentorWriter(
			$userIdentityLookup,
			$this->getUserFactoryMock( $expectedError === null ),
			$this->getWikiConfigLoaderMock(),
			$configWriterFactory,
			new StructuredMentorListValidator(),
			$this->mentorList
		);

		$status = $mentorWriter->changeMentor(
			$mentor,
			$this->createNoOpMock( UserIdentity::class ),
			'Change mentor'
		);
		if ( $expectedError === null ) {
			$this->assertTrue( $status->isOK() );
		} else {
			$this->assertFalse( $status->isOK() );
			$this->assertTrue( $status->hasMessage( $expectedError ) );
		}
	}

	public static function provideChangeMentor() {
		return [
			[
				'mentor' => self::getMentor( 1, null, true, 2 ),
				'expectedNewMentor' => [
					'username' => 'Mentor',
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-not-in-the-list',
			],
			[
				'mentor' => self::getMentor( 123, null, true, 4 ),
				'expectedNewMentor' => [
					'username' => 'Mentor',
					'message' => null,
					'weight' => 4,
					'automaticallyAssigned' => true,
				],
				'expectedError' => null,
			],
		];
	}

	/**
	 * @param bool $isBlocked
	 * @dataProvider provideIsBlocked
	 * @covers ::isBlocked
	 */
	public function testIsBlocked( bool $isBlocked ) {
		$writer = new StructuredMentorWriter(
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->getUserFactoryMock( true, $isBlocked ),
			$this->createNoOpMock( WikiPageConfigLoader::class ),
			$this->createNoOpMock( WikiPageConfigWriterFactory::class ),
			$this->createNoOpMock( StructuredMentorListValidator::class ),
			$this->mentorList
		);

		$this->assertEquals(
			$isBlocked,
			$writer->isBlocked( $this->createNoOpMock( UserIdentity::class ) )
		);
	}

	public function provideIsBlocked() {
		yield [ true ];
		yield [ false ];
	}
}
