<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubpageImproveToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImproveToneTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImproveToneTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Title\TitleParser;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\ImproveToneTaskTypeHandler
 */
class ImproveToneTaskTypeHandlerTest extends MediaWikiUnitTestCase {
	public function testTaskType(): void {
		$sut = new ImproveToneTaskTypeHandler(
			$this->createNoOpMock( ConfigurationValidator::class ),
			$this->createNoOpMock( TitleParser::class ),
			$this->createNoOpMock( SubpageImproveToneRecommendationProvider::class ),
		);

		$taskType = $sut->createTaskType(
			ImproveToneTaskTypeHandler::TASK_TYPE_ID,
			[ 'group' => TaskType::DIFFICULTY_EASY ]
		);

		$this->assertInstanceOf( ImproveToneTaskType::class, $taskType );
		$this->assertSame( 'improve-tone', $taskType->getHandlerId() );
	}
}
