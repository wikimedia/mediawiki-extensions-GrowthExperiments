<?php

namespace GrowthExperiments\Tests\Integration\NewcomerTasks;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use LogicException;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler
 */
class TaskTypeHandlerRegistryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGetTaskTypeHandlerIdByChangeTagName
	 */
	public function testGetTaskTypeHandlerIdByChangeTagName( string $changeTagName, ?string $expectedHandlerId ) {
		$taskTypeHandlerRegistry = GrowthExperimentsServices::wrap(
			$this->getServiceContainer()
		)->getTaskTypeHandlerRegistry();
		$this->assertSame(
			$expectedHandlerId,
			$taskTypeHandlerRegistry->getTaskTypeHandlerIdByChangeTagName( $changeTagName )
		);
	}

	public function provideGetTaskTypeHandlerIdByChangeTagName(): array {
		return [
			[
				'newcomer task',
				null,
			],
			[
				'newcomer task copyedit',
				'template-based',
			],
			[
				'newcomer task add link',
				'link-recommendation',
			],
			[
				'newcomer task image suggestion',
				'image-recommendation',
			],
			[
				'foo',
				null,
			]
		];
	}

	/**
	 * @dataProvider provideGetTaskTypeIdByChangeTagNameForStructuredTasks
	 */
	public function testGetTaskTypeIdByChangeTagNameForStructuredTasks(
		string $expectedTaskTypeId, string $changeTagName, string $expectedExceptionMessage
	) {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( $expectedTaskTypeId === 'link-recommendation' ) {
			$taskTypeHandler = new LinkRecommendationTaskTypeHandler(
				$growthServices->getNewcomerTasksConfigurationValidator(),
				$services->getTitleParser(),
				$growthServices->getLinkRecommendationProvider(),
				$growthServices->getAddLinkSubmissionHandler()
			);
		} elseif ( $expectedTaskTypeId === 'image-recommendation' ) {
			$taskTypeHandler = new ImageRecommendationTaskTypeHandler(
				$growthServices->getNewcomerTasksConfigurationValidator(),
				$services->getTitleParser(),
				$growthServices->getImageRecommendationProvider(),
				$growthServices->getAddImageSubmissionHandler()
			);
		} else {
			throw new LogicException( "Unexpected task type ID $expectedTaskTypeId" );
		}
		$this->assertSame(
			$expectedTaskTypeId,
			$taskTypeHandler->getTaskTypeIdByChangeTagName( $changeTagName )
		);
		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( $expectedExceptionMessage );
		$taskTypeHandler->getTaskTypeIdByChangeTagName( 'foo' );
	}

	public function provideGetTaskTypeIdByChangeTagNameForStructuredTasks(): array {
		return [
			[
				'link-recommendation',
				'newcomer task add link',
				'"foo" is not a valid change tag name for ' .
				'GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler',
			],
			[
				'image-recommendation',
				'newcomer task image suggestion',
				'"foo" is not a valid change tag name for ' .
				'GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler',
			]
		];
	}

	public function testGetUniqueChangeTags() {
		$taskTypeHandlerRegistry = GrowthExperimentsServices::wrap(
			$this->getServiceContainer()
		)->getTaskTypeHandlerRegistry();
		$changeTags = $taskTypeHandlerRegistry->getUniqueChangeTags();
		$this->assertSame( [
			'newcomer task image suggestion',
			'newcomer task add link',
			'newcomer task copyedit',
			'newcomer task references',
			'newcomer task update',
			'newcomer task expand',
			'newcomer task links',
		], $changeTags );
	}

}
