<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\TaskSuggester\NewcomerTasksCacheRefreshJob;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\NewcomerTasksCacheRefreshJob
 */
class NewcomerTasksCacheRefreshJobTest extends MediaWikiIntegrationTestCase {

	public function testFactory() {
		// sanity check
		$job = $this->getServiceContainer()->getJobFactory()->newJob(
			NewcomerTasksCacheRefreshJob::JOB_NAME, [
				'userId' => 1,
				'taskTypeFilters' => [ 'copyedit' ],
				'topicFilters' => [ 'sociology' ],
				'limit' => 10,
			]
		);

		$params = $job->getParams();
		$this->assertArrayHasKey( 'taskTypeFilters', $params );
		$this->assertSame( [ 'copyedit' ], $params['taskTypeFilters'] );
	}

}
