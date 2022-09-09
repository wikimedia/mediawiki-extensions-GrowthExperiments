<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Status;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\Provider\StructuredMentorWriter
 */
class StructuredMentorWriterTest extends MediaWikiUnitTestCase {

	private const MENTOR_LIST_CONTENT = [
		123 => [
			'message' => null,
			'weight' => 2,
			'automaticallyAssigned' => true,
		],
	];

	private const DEFAULT_INTRO_TEXT = 'This is default intro';

	/**
	 * @param int $userId
	 * @param string|null $introText
	 * @param bool $autoAssigned
	 * @param int $weight
	 * @return Mentor
	 */
	private function getMentor(
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
				$this->createNoOpMock( WikiPageConfigLoader::class ),
				$this->createNoOpMock( WikiPageConfigWriterFactory::class ),
				$this->createNoOpMock( StructuredMentorListValidator::class ),
				$this->createNoOpMock( LinkTarget::class )
			)
		);
	}

	/**
	 * @param Mentor $mentor
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
		array $expectedNewMentor,
		?string $expectedError,
		bool $expectLoad
	) {
		if ( $expectedError === null ) {
			$expectedMentorList = self::MENTOR_LIST_CONTENT;
			$expectedMentorList[$mentor->getUserIdentity()->getId()] = $expectedNewMentor;
			$configWriterFactory = $this->getWikiPageWriterFactoryMock( $expectedMentorList, 'Add mentor' );
		} else {
			$configWriterFactory = $this->createNoOpMock( WikiPageConfigWriterFactory::class );
		}
		$mentorWriter = new StructuredMentorWriter(
			$this->getWikiConfigLoaderMock( $expectLoad ),
			$configWriterFactory,
			new StructuredMentorListValidator(),
			$this->createNoOpMock( LinkTarget::class )
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

	public function provideAddMentor() {
		return [
			[
				'mentor' => $this->getMentor( 1, null, true, 2 ),
				'expectedNewMentor' => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => null,
				'expectLoad' => true,
			],
			[
				'mentor' => $this->getMentor( 123, null, true, 2 ),
				'expectedNewMentor' => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-already-added',
				'expectLoad' => true,
			],
			[
				'mentor' => $this->getMentor( 0, null, true, 2 ),
				'expectedNewMentor' => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-anonymous-user',
				'expectLoad' => false,
			],
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
			$this->getWikiConfigLoaderMock(),
			$configWriterFactory,
			new StructuredMentorListValidator(),
			$this->createNoOpMock( LinkTarget::class )
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

	public function provideRemoveMentor() {
		return [
			[
				'mentor' => $this->getMentor( 123, null, true, 2 ),
				'expectedError' => null,
			],
			[
				'mentor' => $this->getMentor( 1, null, true, 2 ),
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
		} else {
			$configWriterFactory = $this->createNoOpMock( WikiPageConfigWriterFactory::class );
		}
		$mentorWriter = new StructuredMentorWriter(
			$this->getWikiConfigLoaderMock(),
			$configWriterFactory,
			new StructuredMentorListValidator(),
			$this->createNoOpMock( LinkTarget::class )
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

	public function provideChangeMentor() {
		return [
			[
				'mentor' => $this->getMentor( 1, null, true, 2 ),
				'expectedNewMentor' => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
				'expectedError' => 'growthexperiments-mentor-writer-error-not-in-the-list',
			],
			[
				'mentor' => $this->getMentor( 123, null, true, 4 ),
				'expectedNewMentor' => [
					'message' => null,
					'weight' => 4,
					'automaticallyAssigned' => true,
				],
				'expectedError' => null,
			],
		];
	}
}
