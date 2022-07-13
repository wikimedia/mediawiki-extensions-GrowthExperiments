<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster;
use GrowthExperiments\HelpPanel\QuestionRecord;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWikiUnitTestCase;
use RequestContext;
use StatusValue;
use User;
use Wikimedia\ScopedCallback;
use Wikimedia\TestingAccessWrapper;
use WikitextContent;

/**
 * @coversDefaultClass \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster
 */
class QuestionPosterTest extends MediaWikiUnitTestCase {

	private function getQuestionPoster( array $methods ) {
		$questionPoster = $this->getMockBuilder( QuestionPoster::class )
			->disableOriginalConstructor()
			->onlyMethods( $methods )
			->getMockForAbstractClass();
		$accessWrapper = TestingAccessWrapper::newFromObject( $questionPoster );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'addTemporaryUserRights' )
			->willReturnCallback(
				static function ( $user, $rights ) {
					return new ScopedCallback(
						static function () {
							// Do nothing
						}
					);
				}
			);
		$accessWrapper->permissionManager = $permissionManager;

		// Needed for providing the user to the permission manager
		$requestContext = new RequestContext();
		$user = $this->createMock( User::class );
		$requestContext->setUser( $user );
		$accessWrapper->context = $requestContext;

		return $questionPoster;
	}

	/**
	 * @covers ::checkContent
	 * @covers ::submit
	 */
	public function testCheckContent() {
		$questionPoster = $this->getQuestionPoster( [
			'makeWikitextContent',
			'checkContent',
			'loadExistingQuestions',
			'setSectionHeader',
			'getTargetContentModel',
		] );
		$questionPoster->method( 'checkContent' )
			->willReturn( StatusValue::newFatal( 'apierror-missingcontent-revid' ) );
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
		$questionPoster = $this->getQuestionPoster( [
			'makeWikitextContent',
			'setSectionHeader',
			'loadExistingQuestions',
			'getPageUpdater',
			'getTargetContentModel',
		] );
		$questionPoster->method( 'makeWikitextContent' )->willReturn( null );
		$questionPoster->method( 'setSectionHeader' )->willReturn( null );
		$questionPoster->method( 'getTargetContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );
		$pageUpdaterMock = $this->createNoOpMock( PageUpdater::class, [ 'grabParentRevision' ] );
		$revisionRecordMock = $this->createMock( RevisionRecord::class );
		$revisionRecordMock->method( 'getId' )
			->willReturn( 0 );
		$pageUpdaterMock->method( 'grabParentRevision' )
			->willReturn( $revisionRecordMock );
		$questionPoster->method( 'getPageUpdater' )
			->willReturn( $pageUpdaterMock );

		/** @var StatusValue $status */
		$status = $questionPoster->submit();
		$this->assertEquals(
			'apierror-missingcontent-revid',
			$status->getErrorsByType( 'error' )[0]['message'],
			'If content is null, submit short-circuits'
		);

		/** @var StatusValue $status */
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
		$questionPoster = $this->getQuestionPoster( [
			'makeWikitextContent',
			'checkUserPermissions',
			'loadExistingQuestions',
			'checkContent',
			'setSectionHeader',
			'getTargetContentModel',
		] );
		$questionPoster->method( 'makeWikitextContent' )->willReturn( null );
		$questionPoster->method( 'setSectionHeader' )->willReturn( null );
		$questionPoster->method( 'getTargetContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );
		$questionPoster->method( 'checkUserPermissions' )->willReturn(
			StatusValue::newFatal( '' )
		);
		$questionPoster->method( 'checkContent' )->willReturn(
			StatusValue::newGood( '' )
		);
		/** @var StatusValue $status */
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
		$questionPoster = $this->getQuestionPoster( [
			'checkUserPermissions',
			'checkContent',
			'loadExistingQuestions',
			'makeWikitextContent',
			'setSectionHeader',
			'runEditFilterMergedContentHook',
			'getTargetContentModel',
		] );
		$questionPoster->method( 'setSectionHeader' )->willReturn( null );
		$questionPoster->method( 'getTargetContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );
		$questionPoster->method( 'checkUserPermissions' )->willReturn(
			StatusValue::newGood( '' )
		);
		$questionPoster->method( 'checkContent' )->willReturn(
			StatusValue::newGood( '' )
		);
		$contentMock = $this->createMock( WikitextContent::class );
		$questionPoster->method( 'makeWikitextContent' )
			->willReturn( $contentMock );
		$questionPoster->expects( $this->once() )
			->method( 'runEditFilterMergedContentHook' )
			->willReturn(
				StatusValue::newFatal( '' )
			);
		/** @var StatusValue $status */
		$status = $questionPoster->submit();
		$this->assertFalse(
			$status->isGood(), 'Check edit filter merged content hook short-circuits checkPermissions call'
		);
	}

	/**
	 * @covers ::getNumberedSectionHeaderIfDuplicatesExist
	 */
	public function testGetNumberedSectionHeaderIfDuplicateExists() {
		$questionRecord = QuestionRecord::newFromArray( [ 'sectionHeader' => 'Foo' ] );
		$questionPosterMock = $this->createMock( QuestionPoster::class );
		$questionPoster = TestingAccessWrapper::newFromObject( $questionPosterMock );
		$questionPoster->existingQuestionsByUser = [ $questionRecord ];
		$this->assertSame(
			'Foo (2)',
			$questionPoster->getNumberedSectionHeaderIfDuplicatesExist(
				'Foo'
			)
		);
	}

	/**
	 * @covers ::getNumberedSectionHeaderIfDuplicatesExist
	 */
	public function testGetNumberedSectionHeaderWithMoreThanOne() {
		$questionPosterMock = $this->createMock( QuestionPoster::class );
		$questionPoster = TestingAccessWrapper::newFromObject( $questionPosterMock );
		$questionRecords = [
			QuestionRecord::newFromArray( [ 'sectionHeader' => 'Foo' ] ),
			QuestionRecord::newFromArray( [ 'sectionHeader' => 'Foo (2)' ] )
		];
		$questionPoster->existingQuestionsByUser = $questionRecords;
		$this->assertSame(
			'Foo (3)',
			$questionPoster->getNumberedSectionHeaderIfDuplicatesExist(
				'Foo'
			)
		);
	}

	/**
	 * @covers ::getNumberedSectionHeaderIfDuplicatesExist
	 */
	public function testGetNumberedSectionHeaderWithUnordered() {
		$questionPosterMock = $this->createMock( QuestionPoster::class );
		$questionPoster = TestingAccessWrapper::newFromObject( $questionPosterMock );
		$questionRecords = [
			QuestionRecord::newFromArray( [ 'sectionHeader' => 'Foo' ] ),
			QuestionRecord::newFromArray( [ 'sectionHeader' => 'Foo (3)' ] )
		];
		$questionPoster->existingQuestionsByUser = $questionRecords;
		$this->assertSame(
			'Foo (2)',
			$questionPoster->getNumberedSectionHeaderIfDuplicatesExist( 'Foo' )
		);
	}

}
