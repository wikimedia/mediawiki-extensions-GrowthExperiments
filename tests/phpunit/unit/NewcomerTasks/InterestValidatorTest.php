<?php
declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\InterestValidator;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\InterestValidator
 */
class InterestValidatorTest extends MediaWikiUnitTestCase {

	/**
	 * The titles the parser mock knows. A null value means a malformed title.
	 * Values are the TitleValue constructor arguments.
	 */
	private const PARSED_TITLES = [
		'Coffee' => [ NS_MAIN, 'Coffee' ],
		'Tea' => [ NS_MAIN, 'Tea' ],
		'Albert Einstein' => [ NS_MAIN, 'Albert_Einstein' ],
		'Talk:Tea' => [ NS_TALK, 'Tea' ],
		'File:Coffee.png' => [ NS_FILE, 'Coffee.png' ],
		'en:Tea' => [ NS_MAIN, 'Tea', '', 'en' ],
		'Coffee#History' => [ NS_MAIN, 'Coffee', 'History' ],
		'<bad>' => null,
	];

	/**
	 * @dataProvider provideValidate
	 * @param string[] $interests
	 * @param string[] $expectedValid
	 * @param string[] $expectedInvalid
	 */
	public function testValidate( array $interests, array $expectedValid, array $expectedInvalid ): void {
		$validator = new InterestValidator( $this->getTitleParser() );

		$this->assertSame(
			[ 'valid' => $expectedValid, 'invalid' => $expectedInvalid ],
			$validator->validate( $interests )
		);
	}

	public static function provideValidate(): array {
		return [
			'no interests' => [ [], [], [] ],
			'articles are valid' => [
				[ 'Coffee', 'Albert Einstein' ], [ 'Coffee', 'Albert Einstein' ], [],
			],
			'a page outside the main namespace is invalid' => [
				[ 'Coffee', 'Talk:Tea' ], [ 'Coffee' ], [ 'Talk:Tea' ],
			],
			'an interwiki page is invalid' => [
				[ 'en:Tea' ], [], [ 'en:Tea' ],
			],
			'a fragment is invalid' => [
				[ 'Coffee#History' ], [], [ 'Coffee#History' ],
			],
			'a malformed title is invalid' => [
				[ 'Coffee', '<bad>' ], [ 'Coffee' ], [ '<bad>' ],
			],
			'the given order is kept' => [
				[ 'Talk:Tea', 'Coffee', 'File:Coffee.png', 'Tea' ],
				[ 'Coffee', 'Tea' ],
				[ 'Talk:Tea', 'File:Coffee.png' ],
			],
		];
	}

	private function getTitleParser(): TitleParser {
		// The real exception cannot be constructed here: it builds its message with wfMessage().
		$malformedTitle = $this->createMock( MalformedTitleException::class );
		$titleParser = $this->createMock( TitleParser::class );
		$titleParser->method( 'parseTitle' )->willReturnCallback(
			static function ( string $text ) use ( $malformedTitle ): TitleValue {
				$arguments = self::PARSED_TITLES[$text] ?? null;
				if ( $arguments === null ) {
					throw $malformedTitle;
				}
				return new TitleValue( ...$arguments );
			}
		);
		return $titleParser;
	}

}
