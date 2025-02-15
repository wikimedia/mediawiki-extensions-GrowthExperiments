<?php

namespace GrowthExperiments\Tests\Unit;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\EventBus\EventGateImageSuggestionFeedbackUpdater;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler
 */
class AddImageSubmissionHandlerTest extends MediaWikiUnitTestCase {

	public function testValidateAccepted() {
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$userIdentityUtilsMock = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtilsMock->method( 'isNamed' )->willReturn( true );
		$imageTaskType = new ImageRecommendationTaskType( 'image', TaskType::DIFFICULTY_EASY );
		$handler = new AddImageSubmissionHandler(
			$weightedTagsUpdaterMock,
			$this->createMock( LocalSearchTaskSuggesterFactory::class ),
			$this->createMock( NewcomerTasksUserOptionsLookup::class ),
			$this->createMock( WANObjectCache::class ),
			$userIdentityUtilsMock,
			$this->createMock( EventGateImageSuggestionFeedbackUpdater::class )
		);
		$page = new PageIdentityValue( 1, 2, 3, '4' );
		$user = new UserIdentityValue( 1, 'Alice' );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage( 'apierror-growthexperiments-addimage-handler-accepted-missing', $status );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => null
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage( 'apierror-growthexperiments-addimage-handler-accepted-wrongtype', $status );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => false,
			'reasons' => [ 1 ]
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage( 'apierror-growthexperiments-addimage-handler-reason-invaliditem', $status );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => false,
			'reasons' => [ 'invalid-reason' ]
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage( 'apierror-growthexperiments-addimage-handler-reason-invaliditem', $status );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'fail'
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage( 'growthexperiments-addimage-caption-warning-tooshort', $status );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'sectionTitle' => 1337,
			'sectionNumber' => null,
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage( 'apierror-growthexperiments-addimage-handler-section-title-wrongtype', $status );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'sectionTitle' => null,
			'sectionNumber' => 1337,
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage( 'apierror-growthexperiments-addimage-handler-section-number-wrongtype', $status );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => false,
			'reasons' => [ 'noinfo' ],
			'filename' => 'SomeFile.jpg',
			'sectionTitle' => null,
			'sectionNumber' => null,
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertArrayEquals( [ false, [ 'noinfo' ], 'SomeFile.jpg', null, null ], $status->getValue() );

		$status = $handler->validate( $imageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'filename' => '',
			'sectionTitle' => null,
			'sectionNumber' => null,
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertArrayEquals( [ true, [], '', null, null ], $status->getValue() );
	}

	public function testValidateAcceptedSectionImage() {
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$sectionImageTaskType = new SectionImageRecommendationTaskType(
			'section-image-recommendation',
			TaskType::DIFFICULTY_EASY
		);
		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils
			->method( 'isNamed' )
			->willReturn( true );
		$handler = new AddImageSubmissionHandler(
			$weightedTagsUpdaterMock,
			$this->createMock( LocalSearchTaskSuggesterFactory::class ),
			$this->createMock( NewcomerTasksUserOptionsLookup::class ),
			$this->createMock( WANObjectCache::class ),
			$userIdentityUtils,
			$this->createMock( EventGateImageSuggestionFeedbackUpdater::class )
		);
		$page = new PageIdentityValue( 1, 2, 3, '4' );
		$user = new UserIdentityValue( 1, 'Alice' );

		$status = $handler->validate( $sectionImageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'filename' => '',
			'sectionTitle' => null,
			'sectionNumber' => null,
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage(
			'apierror-growthexperiments-addsectionimage-handler-section-title-wrongtype',
			$status
		);

		$status = $handler->validate( $sectionImageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'filename' => '',
			'sectionTitle' => 1,
			'sectionNumber' => null,
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage(
			'apierror-growthexperiments-addsectionimage-handler-section-title-wrongtype',
			$status
		);

		$status = $handler->validate( $sectionImageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'filename' => '',
			'sectionTitle' => 'Some section title',
			'sectionNumber' => null,
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage(
			'apierror-growthexperiments-addsectionimage-handler-section-number-wrongtype',
			$status
		);

		$status = $handler->validate( $sectionImageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'filename' => '',
			'sectionTitle' => 'Some section title',
			'sectionNumber' => 'wrongtype'
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertStatusMessage(
			'apierror-growthexperiments-addsectionimage-handler-section-number-wrongtype',
			$status
		);

		$status = $handler->validate( $sectionImageTaskType, $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed',
			'filename' => '',
			'sectionTitle' => 'Some section title',
			'sectionNumber' => 3
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertArrayEquals( [ true, [], '', 'Some section title', 3 ], $status->getValue() );
	}
}
