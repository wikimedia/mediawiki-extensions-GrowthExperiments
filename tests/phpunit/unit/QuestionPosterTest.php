<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel\QuestionPoster;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\RevisionRecord;

/**
 * @coversDefaultClass \GrowthExperiments\HelpPanel\QuestionPoster
 */
class QuestionPosterTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::checkContent
	 * @covers ::submit
	 */
	public function testCheckContent() {
		$questionPoster = $this->getMockBuilder( QuestionPoster::class )
			->disableOriginalConstructor()
			->setMethods( [
				'makeWikitextContent',
				'checkContent',
				'loadExistingQuestions',
				'setSectionHeader',
				'getTargetContentModel',
			] )
			->getMockForAbstractClass();
		$questionPoster->method( 'checkContent' )
			->willReturn( \StatusValue::newFatal( 'apierror-missingcontent-revid' ) );
		$questionPoster->method( 'makeWikitextContent' )->willReturn( null );
		$questionPoster->method( 'setSectionHeader' )->willReturn( null );
		$questionPoster->method( 'getTargetContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );

		/** @var \StatusValue $status */
		$status = $questionPoster->submit();
		$this->assertEquals(
			'apierror-missingcontent-revid',
			$status->getErrorsByType( 'error' )[0]['message'],
			'if checkContent returns fatal status, submit short circuits'
		);
	}

	/**
	 * @covers ::submit
	 * @covers ::checkContent
	 */
	public function testNullContent() {
		$questionPoster = $this->getMockBuilder( QuestionPoster::class )
			->disableOriginalConstructor()
			->setMethods( [
				'makeWikitextContent',
				'setSectionHeader',
				'loadExistingQuestions',
				'getPageUpdater',
				'grabParentRevision',
				'getTargetContentModel',
			] )
			->getMockForAbstractClass();
		$questionPoster->method( 'makeWikitextContent' )->willReturn( null );
		$questionPoster->method( 'setSectionHeader' )->willReturn( null );
		$questionPoster->method( 'getTargetContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );
		$pageUpdaterMock = $this->getMockBuilder( PageUpdater::class )
			->disableOriginalConstructor()
			->setMethods( [ 'grabParentRevision' ] )
			->getMock();
		$revisionRecordMock = $this->getMockBuilder( RevisionRecord::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getId' ] )
			->getMockForAbstractClass();
		$revisionRecordMock->expects( $this->any() )
			->method( 'getId' )
			->willReturn( 0 );
		$pageUpdaterMock->expects( $this->any() )
			->method( 'grabParentRevision' )
			->willReturn( $revisionRecordMock );
		$questionPoster->expects( $this->any() )
			->method( 'getPageUpdater' )
			->willReturn( $pageUpdaterMock );

		/** @var \StatusValue $status */
		$status = $questionPoster->submit();
		$this->assertEquals(
			'apierror-missingcontent-revid',
			$status->getErrorsByType( 'error' )[0]['message'],
			'If content is null, submit short-circuits'
		);

		/** @var \StatusValue $status */
		$status = $questionPoster->submit();
		$this->assertEquals(
			'apierror-missingcontent-revid',
			$status->getErrorsByType( 'error' )[0]['message'],
			'If content is a string, submit short-circuits'
		);
	}

	/**
	 * @covers ::checkPermissions
	 * @covers ::checkUserPermissions
	 */
	public function testCheckUserPermissions() {
		$questionPoster = $this->getMockBuilder( QuestionPoster::class )
			->disableOriginalConstructor()
			->setMethods( [
				'makeWikitextContent',
				'checkUserPermissions',
				'loadExistingQuestions',
				'checkContent',
				'setSectionHeader',
				'getTargetContentModel',
			] )
			->getMockForAbstractClass();
		$questionPoster->method( 'makeWikitextContent' )->willReturn( null );
		$questionPoster->method( 'setSectionHeader' )->willReturn( null );
		$questionPoster->method( 'getTargetContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );
		$questionPoster->method( 'checkUserPermissions' )->willReturn(
			\StatusValue::newFatal( '' )
		);
		$questionPoster->method( 'checkContent' )->willReturn(
			\StatusValue::newGood( '' )
		);
		/** @var \StatusValue $status */
		$status = $questionPoster->submit();
		$this->assertFalse(
			$status->isGood(), 'Check user permissions short-circuits checkPermissions call'
		);
	}

	/**
	 * @covers ::checkPermissions
	 * @covers ::checkContent
	 * @covers ::runEditFilterMergedContentHook
	 */
	public function testRunEditFilterMergedContentHook() {
		$questionPoster = $this->getMockBuilder( QuestionPoster::class )
			->disableOriginalConstructor()
			->setMethods( [
				'checkUserPermissions',
				'checkContent',
				'loadExistingQuestions',
				'makeWikitextContent',
				'setSectionHeader',
				'runEditFilterMergedContentHook',
				'getTargetContentModel',
			] )
			->getMockForAbstractClass();
		$questionPoster->method( 'setSectionHeader' )->willReturn( null );
		$questionPoster->method( 'getTargetContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );
		$questionPoster->method( 'checkUserPermissions' )->willReturn(
			\StatusValue::newGood( '' )
		);
		$questionPoster->method( 'checkContent' )->willReturn(
			\StatusValue::newGood( '' )
		);
		$contentMock = $this->getMockBuilder( \WikitextContent::class )
			->disableOriginalConstructor()
			->getMock();
		$questionPoster->method( 'makeWikitextContent' )
			->willReturn( $contentMock );
		$questionPoster->expects( $this->once() )
			->method( 'runEditFilterMergedContentHook' )
			->willReturn(
			\StatusValue::newFatal( '' )
		);
		/** @var \StatusValue $status */
		$status = $questionPoster->submit();
		$this->assertFalse(
			$status->isGood(), 'Check edit filter merged content hook short-circuits checkPermissions call'
		);
	}

}
