<?php
declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic
 */
class InterestBasedTopicTest extends MediaWikiUnitTestCase {

	public function testGetters(): void {
		$title = new TitleValue( NS_MAIN, 'Albert_Einstein' );
		$topic = new InterestBasedTopic( 'Albert Einstein', $title );
		$this->assertSame( 'Albert Einstein', $topic->getId() );
		$this->assertSame( $title, $topic->getTitle() );
		$this->assertNull( $topic->getGroupId() );
	}

	/**
	 * @dataProvider provideIsValidTitle
	 */
	public function testIsValidTitle( TitleValue $title, bool $expected ): void {
		$this->assertSame( $expected, InterestBasedTopic::isValidTitle( $title ) );
	}

	public static function provideIsValidTitle(): array {
		return [
			'article' => [ new TitleValue( NS_MAIN, 'Coffee' ), true ],
			'talk page' => [ new TitleValue( NS_TALK, 'Coffee' ), false ],
			'fragment' => [ new TitleValue( NS_MAIN, 'Coffee', 'History' ), false ],
			'interwiki' => [ new TitleValue( NS_MAIN, 'Coffee', '', 'en' ), false ],
		];
	}

	public function testJsonSerialization(): void {
		// JsonCodec isn't stable to construct but there is no better way in a unit test.
		$codec = new JsonCodec();
		$topic = new InterestBasedTopic( 'Contents', new TitleValue( NS_MAIN, 'Contents' ) );
		$topic2 = $codec->deserialize( $codec->serialize( $topic ) );
		$this->assertEquals( $topic, $topic2 );
	}

	public function testGetName(): void {
		$title = new TitleValue( NS_MAIN, 'Albert_Einstein' );
		$topic = new InterestBasedTopic( 'Albert Einstein', $title );
		$this->assertSame(
			[ 'Albert Einstein' ],
			$topic->getName( $this->createNoOpMock( MessageLocalizer::class ) )->getParamsOfRawMessage(),
		);
		$this->assertSame(
			'$1',
			$topic->getName( $this->createNoOpMock( MessageLocalizer::class ) )->getTextOfRawMessage()
		);
	}

}
