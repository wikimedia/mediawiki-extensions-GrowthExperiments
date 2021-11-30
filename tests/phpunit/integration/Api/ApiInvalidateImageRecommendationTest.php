<?php

namespace GrowthExperiments\Tests;

use ApiTestCase;
use ApiUsageException;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use MediaWiki\Page\ProperPageIdentity;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Api\ApiInvalidateImageRecommendation
 */
class ApiInvalidateImageRecommendationTest extends ApiTestCase {

	public function testExecute() {
		$pageName = 'Title1';
		$this->insertPage( $pageName );
		$this->setupTaskSet( [ $pageName ] );
		$this->setupAddImageSubmissionHandler();
		$result = $this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
			'title' => $pageName
		] );
		$this->assertArrayEquals( [ 'growthinvalidateimagerecommendation' => [
				'status' => 'ok'
			]
		], $result[ 0 ] );
	}

	public function testNonExistentPage() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
			'title' => 'Title'
		] );
	}

	public function testPageNotInTaskSet() {
		$pageName = 'Title1';
		$this->insertPage( $pageName );
		$this->setupTaskSet();
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation',
			'title' => $pageName
		] );
	}

	public function testMissingTitleParam() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken( [
			'action' => 'growthinvalidateimagerecommendation'
		] );
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
