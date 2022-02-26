<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;
use Wikimedia\TestingAccessWrapper;
use WikitextContent;

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
		?RevisionRecord $parentRevision,
		?string $expectedResult
	) {
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

	public function provideMakeWikitextContent() {
		$makeParent = function ( string $text ) {
			$title = $this->makeMockTitle( 'QuestionPosterTest' );
			$revision = new MutableRevisionRecord( $title );
			$revision->setContent( SlotRecord::MAIN, new WikitextContent( $text ) );
			return $revision;
		};

		$hiddenParent = $makeParent( 'x' );
		$hiddenParent->setVisibility( RevisionRecord::SUPPRESSED_ALL );

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
				'parentRevision' => $hiddenParent,
				'expectedResult' => null,
			],
			'bottom' => [
				'postOnTop' => false,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => $makeParent( "Section 0\n\n== H1 ==\nSection1\n== H2 ==\nSection2" ),
				'expectedResult' => "Section 0\n\n== H1 ==\nSection1\n== H2 ==\nSection2\n\n"
					. "== Some header ==\n\nFoo\nbar\n\nbaz --~~~~",
			],
			'top' => [
				'postOnTop' => true,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => $makeParent( "Section 0\n\n== H1 ==\nSection1\n== H2 ==\nSection2" ),
				'expectedResult' => "Section 0\n\n== Some header ==\n\nFoo\nbar\n\nbaz --~~~~\n\n"
					. "== H1 ==\nSection1\n\n== H2 ==\nSection2",
			],
			'top with no sections' => [
				'postOnTop' => true,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => $makeParent( "Section\n\n0" ),
				'expectedResult' => "Section\n\n0\n\n== Some header ==\n\nFoo\nbar\n\nbaz --~~~~",
			],
			'top with subsections' => [
				'postOnTop' => true,
				'body' => "Foo\nbar\n\nbaz",
				'sectionHeader' => 'Some header',
				'parentRevision' => $makeParent( "Section 0\n\n== H1 ==\nSection1\n=== H2 ===\nSection2" ),
				'expectedResult' => "Section 0\n\n== Some header ==\n\nFoo\nbar\n\nbaz --~~~~\n\n"
					. "== H1 ==\nSection1\n=== H2 ===\nSection2",
			],
		];
	}

}
