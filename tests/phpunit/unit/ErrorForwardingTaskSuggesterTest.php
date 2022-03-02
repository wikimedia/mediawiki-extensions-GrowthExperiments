<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class ErrorForwardingTaskSuggesterTest extends MediaWikiUnitTestCase {

	public function testSuggest() {
		$user = new UserIdentityValue( 1, 'Foo' );
		$suggester = new ErrorForwardingTaskSuggester( StatusValue::newFatal( 'foo' ) );
		$result = $suggester->suggest( $user, new TaskSetFilters() );
		$this->assertInstanceOf( StatusValue::class, $result );
		$this->assertTrue( $result->hasMessage( 'foo' ) );
	}

}
