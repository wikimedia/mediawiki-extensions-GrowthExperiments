<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use CirrusSearch\CirrusSearch;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;
use WANObjectCache;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandlerTest
 */
class AddImageSubmissionHandlerTest extends MediaWikiUnitTestCase {

	public function testValidateAccepted() {
		$cirrusSearchFactoryMock = function () {
			return $this->createMock( CirrusSearch::class );
		};
		$imageTaskType = new ImageRecommendationTaskType( 'image', TaskType::DIFFICULTY_EASY );
		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->method( 'getTaskTypes' )->willReturn( [
			'image-recommendation' => $imageTaskType
		] );
		$handler = new AddImageSubmissionHandler(
			$cirrusSearchFactoryMock,
			$this->createMock( LocalSearchTaskSuggesterFactory::class ),
			$this->createMock( NewcomerTasksUserOptionsLookup::class ),
			$configurationLoaderMock,
			$this->createMock( WANObjectCache::class )
		);
		$page = new PageIdentityValue( 1, 2, 3, '4' );
		$user = new UserIdentityValue( 1, 'Alice' );

		$status = $handler->validate( $page, $user, 1, [] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage( 'apierror-growthexperiments-addimage-handler-accepted-missing' ) );

		$status = $handler->validate( $page, $user, 1, [
			'accepted' => null
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage( 'apierror-growthexperiments-addimage-handler-accepted-wrongtype' ) );

		$status = $handler->validate( $page, $user, 1, [
			'accepted' => false,
			'reasons' => [ 1 ]
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage( 'apierror-growthexperiments-addimage-handler-reason-invaliditem' ) );

		$status = $handler->validate( $page, $user, 1, [
			'accepted' => false,
			'reasons' => [ 'invalid-reason' ]
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage( 'apierror-growthexperiments-addimage-handler-reason-invaliditem' ) );

		$status = $handler->validate( $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'fail'
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage( 'growthexperiments-addimage-caption-warning-tooshort' ) );

		$status = $handler->validate( $page, $user, 1, [
			'accepted' => false,
			'reasons' => [ 'noinfo' ],
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertArrayEquals( [ false, [ 'noinfo' ] ], $status->getValue() );

		$status = $handler->validate( $page, $user, 1, [
			'accepted' => true,
			'reasons' => [],
			'caption' => 'succeed'
		] );
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertArrayEquals( [ true, [] ], $status->getValue() );
	}
}
