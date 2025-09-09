<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubpageReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Title\TitleParser;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler
 */
class ReviseToneTaskTypeHandlerTest extends MediaWikiUnitTestCase {
	public function testTaskType(): void {
		$sut = new ReviseToneTaskTypeHandler(
			$this->createNoOpMock( ConfigurationValidator::class ),
			$this->createNoOpMock( TitleParser::class ),
			$this->createNoOpMock( SubpageReviseToneRecommendationProvider::class ),
		);

		$taskType = $sut->createTaskType(
			ReviseToneTaskTypeHandler::TASK_TYPE_ID,
			[ 'group' => TaskType::DIFFICULTY_EASY ]
		);

		$this->assertInstanceOf( ReviseToneTaskType::class, $taskType );
		$this->assertSame( 'revise-tone', $taskType->getHandlerId() );
	}
}
