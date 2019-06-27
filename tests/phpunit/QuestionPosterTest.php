<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel\QuestionPoster;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\RevisionRecord;

/**
 * @coversDefaultClass \GrowthExperiments\HelpPanel\QuestionPoster
 */
class QuestionPosterTest extends \MediaWikiTestCase {

	/**
	 * @covers ::checkContent
	 * @covers ::submit
	 */
	public function testCheckContent() {
		$questionPoster = $this->getMockBuilder( QuestionPoster::class )
			->disableOriginalConstructor()
			->setMethods( [ 'checkContent', 'prepare' ] )
			->getMockForAbstractClass();
		$questionPoster->method( 'checkContent' )
			->willReturn( \StatusValue::newFatal( 'apierror-missingcontent-revid' ) );
		$questionPoster->method( 'prepare' )->willReturn( null );

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
			->setMethods( [ 'getContent', 'prepare', 'getPageUpdater', 'grabParentRevision' ] )
			->getMockForAbstractClass();
		$questionPoster->method( 'prepare' )->willReturn( null );
		$questionPoster->method( 'getContent' )->willReturn( null );
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

		$questionPoster->method( 'getContent' )->willReturn( 'foo' );
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
			->setMethods( [ 'checkUserPermissions', 'checkContent', 'prepare' ] )
			->getMockForAbstractClass();
		$questionPoster->method( 'prepare' )->willReturn( null );
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
				'getContent',
				'prepare',
				'runEditFilterMergedContentHook'
			] )
			->getMockForAbstractClass();
		$questionPoster->method( 'prepare' )->willReturn( null );
		$questionPoster->method( 'checkUserPermissions' )->willReturn(
			\StatusValue::newGood( '' )
		);
		$questionPoster->method( 'checkContent' )->willReturn(
			\StatusValue::newGood( '' )
		);
		$contentMock = $this->getMockBuilder( \WikitextContent::class )
			->disableOriginalConstructor()
			->getMock();
		$questionPoster->method( 'getContent' )
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
