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
			]
		);

		$this->assertInstanceOf( NewcomerTasksCacheRefreshJob::class, $job );
		$this->assertSame( 1, $job->getParams()['userId'] );
	}

}
