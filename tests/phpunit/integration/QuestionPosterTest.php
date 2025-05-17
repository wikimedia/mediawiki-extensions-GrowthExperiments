<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster
 */
class QuestionPosterTest extends MediaWikiIntegrationTestCase {
	use MockTitleTrait;

	/**
	 * @covers ::makeWikitextContent
	 * @dataProvider provideMakeWikitextContent
	 */
	public function testMakeWikitextContent(
		bool $postOnTop,
		string $body,
		string $sectionHeader,
		?array $parentRevisionSpec,
		?string $expectedResult
	) {
		if ( $parentRevisionSpec !== null ) {
			[ $text, $visibility ] = $parentRevisionSpec;
			$title = $this->makeMockTitle( 'QuestionPosterTest' );
			$parentRevision = new MutableRevisionRecord( $title );
			$parentRevision->setContent( SlotRecord::MAIN, new WikitextContent( $text ) );
			$parentRevision->setVisibility( $visibility );
		} else {
			$parentRevision = null;
		}

		$questionPoster = $this->getMockBuilder( QuestionPoster::class )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
		$wrappedQuestionPoster = TestingAccessWrapper::newFromObject( $questionPoster );
		$wrappedQuestionPoster->postOnTop = $postOnTop;
		$wrappedQuestionPoster->body = $body;
		$wrappedQuestionPoster->sectionHeader = $sectionHeader;
		$wrappedQuestionPoster->pageUpdater = $this->createMock( PageUpdater::class );
		$wrappedQuestionPoster->pageUpdater->method( 'grabParentRevision' )
			->willReturn( $parentRevision );

		$actualResult = $wrappedQuestionPoster->makeWikitextContent();
		if ( $expectedResult === null ) {
			$this->assertNull( $actualResult );
		} else {
			$this->assertInstanceOf( WikitextContent::class, $actualResult );
			$this->assertSame( $expectedResult, $actualResult->getText() );
		}
	}

	public static function provideMakeWikitextContent() {
		return [
			'no parent' => [
				'postOnTop' => false,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => null,
				'expectedResult' => "== Some header ==\n\nFoo\nbar\n\nbaz --~~~~",
			],
			'hidden parent' => [
				'postOnTop' => false,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => [ 'x', RevisionRecord::SUPPRESSED_ALL ],
				'expectedResult' => null,
			],
			'bottom' => [
				'postOnTop' => false,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => [ "Section 0\n\n== H1 ==\nSection1\n== H2 ==\nSection2", 0 ],
				'expectedResult' => "Section 0\n\n== H1 ==\nSection1\n== H2 ==\nSection2\n\n"
					. "== Some header ==\n\nFoo\nbar\n\nbaz --~~~~",
			],
			'top' => [
				'postOnTop' => true,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => [ "Section 0\n\n== H1 ==\nSection1\n== H2 ==\nSection2", 0 ],
				'expectedResult' => "Section 0\n\n== Some header ==\n\nFoo\nbar\n\nbaz --~~~~\n\n"
					. "== H1 ==\nSection1\n\n== H2 ==\nSection2",
			],
			'top with no sections' => [
				'postOnTop' => true,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => [ "Section\n\n0", 0 ],
				'expectedResult' => "Section\n\n0\n\n== Some header ==\n\nFoo\nbar\n\nbaz --~~~~",
			],
			'top with subsections' => [
				'postOnTop' => true,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => [ "Section 0\n\n== H1 ==\nSection1\n=== H2 ===\nSection2", 0 ],
				'expectedResult' => "Section 0\n\n== Some header ==\n\nFoo\nbar\n\nbaz --~~~~\n\n"
					. "== H1 ==\nSection1\n=== H2 ===\nSection2",
			],
		];
	}

}
