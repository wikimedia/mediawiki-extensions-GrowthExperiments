<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Api\ApiInvalidateImageRecommendation
 */
class ApiInvalidateImageRecommendationTest extends ApiTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'EventBus' );
	}

	public function testExecute() {
		$pageName = 'Title1';
		$this->insertConfigPage();
		$this->insertPage( $pageName );
		$this->setupTaskSet( [ $pageName ] );
		$this->setupAddImageSubmissionHandler();
		$result = $this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
			'title' => $pageName,
			'filename' => 'Foo.jpg',
		] );
		$this->assertArrayEquals( [ 'growthinvalidateimagerecommendation' => [
				'status' => 'ok',
			],
		], $result[ 0 ] );
	}

	public function testNonExistentPage() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
			'title' => 'Title',
			'filename' => 'Foo.jpg',
		] );
	}

	public function testPageNotInTaskSet() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$this->insertConfigPage();
		$pageName = 'Title1';
		$this->insertPage( $pageName );
		$this->setupTaskSet();
		$result = $this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
			'title' => $pageName,
			'filename' => 'Foo.jpg',
		] );
		$this->assertArrayEquals( [ 'growthinvalidateimagerecommendation' => [
				'status' => 'ok',
			],
		], $result[ 0 ] );
	}

	public function testMissingTitleParam() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "title" parameter must be set.' );
		$this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
		] );
	}

	public function testMissingFilenameParam() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "filename" parameter must be set.' );
		$this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
			'title' => 'Blah',
		] );
	}

	private function insertConfigPage() {
		$this->insertPage( 'MediaWiki:NewcomerTasks.json', json_encode( [
			ImageRecommendationTaskTypeHandler::TASK_TYPE_ID => [
				'type' => ImageRecommendationTaskTypeHandler::ID,
				'group' => TaskType::DIFFICULTY_EASY,
			],
		] ) );
	}

	private function setupTaskSet( array $titles = [] ) {
		$suggesterFactory = $this->createMock( TaskSuggesterFactory::class );
		$taskSuggester = $this->createMock( TaskSuggester::class );
		$taskSet = $this->createMock( TaskSet::class );
		$taskSet->method( 'containsPage' )->willReturnCallback(
			static function ( ProperPageIdentity $page ) use ( $titles ) {
				return in_array( $page->getDBkey(), $titles );
			}
		);
		$taskSuggester->method( 'suggest' )->willReturn( $taskSet );
		$suggesterFactory->method( 'create' )->willReturn( $taskSuggester );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
	}

	private function setupAddImageSubmissionHandler() {
		$handler = $this->createMock( AddImageSubmissionHandler::class );
		$this->setService( 'GrowthExperimentsAddImageSubmissionHandler', $handler );
	}
}
