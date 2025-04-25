<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\JobQueue\Job;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\NewcomerTasksCacheRefreshJob
 */
class NewcomerTasksCacheRefreshJobTest extends MediaWikiIntegrationTestCase {

	public function testFactory() {
		// sanity check
		$job = Job::factory( 'newcomerTasksCacheRefreshJob', [
			'userId' => 1,
			'taskTypeFilters' => [ 'copyedit' ],
			'topicFilters' => [ 'sociology' ],
			'limit' => 10,
		] );
		$params = $job->getParams();
		$this->assertArrayHasKey( 'taskTypeFilters', $params );
		$this->assertSame( [ 'copyedit' ], $params['taskTypeFilters'] );
	}

}
