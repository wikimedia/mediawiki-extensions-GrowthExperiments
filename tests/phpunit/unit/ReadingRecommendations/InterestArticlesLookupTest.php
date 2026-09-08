<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\ReadingRecommendations\InterestArticlesLookup;
use MediaWiki\Json\FormatJson;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @covers \GrowthExperiments\ReadingRecommendations\InterestArticlesLookup
 */
class InterestArticlesLookupTest extends MediaWikiUnitTestCase {

	public static function provideDegenerateOptions() {
		return [
			'no option' => [ null ],
			'empty string' => [ '' ],
			'invalid JSON' => [ '{' ],
			'not an array' => [ '"Cat"' ],
		];
	}

	/**
	 * @dataProvider provideDegenerateOptions
	 */
	public function testDegenerateOptions( ?string $optionValue ) {
		$lookup = $this->getLookup( $optionValue );
		$this->assertSame( [], $lookup->getInterests( new UserIdentityValue( 1, 'Alice' ) ) );
	}

	public function testValidationAndDeduplication() {
		$lookup = $this->getLookup( FormatJson::encode( [
			123,
			'Cat',
			'Talk:Style',
			'fr:Paris',
			'Cat#Anatomy',
			'Bad<Title',
			'Cat',
			'Dog',
		] ) );
		$this->assertEquals(
			[ new TitleValue( NS_MAIN, 'Cat' ), new TitleValue( NS_MAIN, 'Dog' ) ],
			$lookup->getInterests( new UserIdentityValue( 1, 'Alice' ) )
		);
	}

	public function testWarningOmitsUserControlledEntry() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with( 'InterestArticlesLookup: skipped an interest article entry', [
				'reason' => 'malformed',
			] );
		$lookup = $this->getLookup( FormatJson::encode( [ 'Bad<Title' ] ), $logger );

		$this->assertSame( [], $lookup->getInterests( new UserIdentityValue( 1, 'Alice' ) ) );
	}

	private function getLookup(
		?string $optionValue,
		?LoggerInterface $logger = null
	): InterestArticlesLookup {
		$userOptionsLookup = $this->createNoOpMock( UserOptionsLookup::class, [ 'getOption' ] );
		$userOptionsLookup->method( 'getOption' )
			->with( $this->anything(), AccountSetupHooks::INTEREST_ARTICLES_PROP )
			->willReturn( $optionValue );

		$titleParser = $this->createMock( TitleParser::class );
		// A mocked exception: the real constructor needs globals unit tests lack.
		$malformed = $this->createMock( MalformedTitleException::class );
		$titleParser->method( 'parseTitle' )->willReturnCallback( static function ( $text ) use ( $malformed ) {
			switch ( $text ) {
				case 'Bad<Title':
					throw $malformed;
				case 'Talk:Style':
					return new TitleValue( NS_TALK, 'Style' );
				case 'fr:Paris':
					return new TitleValue( NS_MAIN, 'Paris', '', 'fr' );
				case 'Cat#Anatomy':
					return new TitleValue( NS_MAIN, 'Cat', 'Anatomy' );
				default:
					return new TitleValue( NS_MAIN, str_replace( ' ', '_', $text ) );
			}
		} );

		return new InterestArticlesLookup( $userOptionsLookup, $titleParser, $logger ?? new NullLogger() );
	}
}
