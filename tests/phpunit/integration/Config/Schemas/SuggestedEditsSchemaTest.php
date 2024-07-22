<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use MockMessageLocalizer;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\CommunityConfigurationLoader
 *
 * Conceptually, it especially covers @see \GrowthExperiments\Config\Schemas\SuggestedEditsSchema,
 * but that class does not contain any executable code.
 *
 * @group Database
 */
class SuggestedEditsSchemaTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'CommunityConfiguration' );
	}

	public function testDefaultTaskTypesDataWithEmptyConfig(): void {
		$this->overrideConfigValues( [
			'GEUseCommunityConfigurationExtension' => true,
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GENewcomerTasksImageRecommendationsEnabled' => true,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => true,
		] );

		$this->getNonexistingTestPage( 'MediaWiki:GrowthExperimentsSuggestedEdits.json' );

		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$configurationValidator = $growthServices->getNewcomerTasksConfigurationValidator();
		$configurationValidator->setMessageLocalizer( new MockMessageLocalizer() );
		$configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$taskTypes = $configurationLoader->loadTaskTypes();

		if ( $taskTypes instanceof StatusValue ) {
			$this->fail(
				"Should be able to load tasks from empty config without errors, but got:\n"
				. implode(
					"\n",
					array_map( static function ( $message ) {
						return $message->parse();
					}, $taskTypes->getMessages() )
				)
			);
		}

		$this->assertEquals( [
			[
				'id' => 'copyedit',
				'extraData' => [ 'learnMoreLink' => '' ],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'easy',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'expand',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'hard',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'links',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'easy',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'references',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'medium',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'update',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'medium',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'image-recommendation',
				'extraData' => [
					'learnMoreLink' => null,
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType',
				'handlerId' => 'image-recommendation',
				'difficulty' => 'medium',
				'iconData' => [
					'icon' => 'robot-task-type-medium',
					'filterIcon' => 'robot',
					'descriptionMessageKey' => 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'settings' => [
					'maxTasksPerDay' => 25,
					'minimumCaptionCharacterLength' => 5,
					'minimumImageSize' => [
						'width' => 100
					],
				],
			],
			[
				'id' => 'section-image-recommendation',
				'extraData' => [
					'learnMoreLink' => null,
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType',
				'handlerId' => 'section-image-recommendation',
				'difficulty' => 'medium',
				'iconData' => [
					'icon' => 'robot-task-type-medium',
					'filterIcon' => 'robot',
					'descriptionMessageKey' => 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'settings' => [
					'maxTasksPerDay' => 25,
					'minimumCaptionCharacterLength' => 5,
					'minimumImageSize' => [
						'width' => 100
					],
				],
			],
			[
				'id' => 'link-recommendation',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType',
				'handlerId' => 'link-recommendation',
				'difficulty' => 'easy',
				'iconData' => [
					'icon' => 'robot-task-type-easy',
					'filterIcon' => 'robot',
					'descriptionMessageKey' => 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'settings' => [
					'minimumTasksPerTopic' => 500,
					'minimumLinksPerTask' => 2,
					'minimumLinkScore' => 0.6,
					'maximumLinksPerTask' => 10,
					'maximumLinksToShowPerTask' => 3,
					'minimumTimeSinceLastEdit' => 86400,
					'minimumWordCount' => 0,
					'maximumWordCount' => 9223372036854775807,
					'maxTasksPerDay' => 25,
					'underlinkedWeight' => 0.5,
					'underlinkedMinLength' => 300,
				],
			],
		], array_map( static fn ( $taskType ) => $taskType->jsonSerialize(), $taskTypes ) );
	}

	public function testDefaultTaskTypesDataWithPartialConfig(): void {
		$this->overrideConfigValues( [
			'GEUseCommunityConfigurationExtension' => true,
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GENewcomerTasksImageRecommendationsEnabled' => true,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => true,
		] );

		/*
		 * This close to what was saved in https://es.wikipedia.beta.wmflabs.org/w/index.php?title=MediaWiki:GrowthExperimentsSuggestedEdits.json&oldid=34353
		 * and triggered T365653
		 */
		$partialConfigJson = <<<JSON
{
	"section_image_recommendation": {
		"group": "medium",
		"type": "section-image-recommendation"
	},
	"image_recommendation": {
		"group": "medium",
		"type": "image-recommendation"
	},
	"link_recommendation": {
		"group": "easy",
		"type": "link-recommendation"
	},
	"expand": {
		"disabled": true
	}
}
JSON;

		$this->editPage( 'MediaWiki:GrowthExperimentsSuggestedEdits.json', $partialConfigJson );

		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$configurationValidator = $growthServices->getNewcomerTasksConfigurationValidator();
		$configurationValidator->setMessageLocalizer( new MockMessageLocalizer() );
		$configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$taskTypes = $configurationLoader->loadTaskTypes();

		if ( $taskTypes instanceof StatusValue ) {
			$this->fail(
				"Should be able to load tasks from empty config without errors, but got:\n"
				. implode(
					"\n",
					array_map( static function ( $message ) {
						return $message->parse();
					}, $taskTypes->getMessages() )
				)
			);
		}

		$this->assertEquals( [
			[
				'id' => 'copyedit',
				'extraData' => [ 'learnMoreLink' => '' ],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'easy',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'links',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'easy',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'references',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'medium',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'update',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType',
				'handlerId' => 'template-based',
				'difficulty' => 'medium',
				'iconData' => [],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'templates' => [],
			],
			[
				'id' => 'image-recommendation',
				'extraData' => [
					'learnMoreLink' => null,
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType',
				'handlerId' => 'image-recommendation',
				'difficulty' => 'medium',
				'iconData' => [
					'icon' => 'robot-task-type-medium',
					'filterIcon' => 'robot',
					'descriptionMessageKey' => 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'settings' => [
					'maxTasksPerDay' => 25,
					'minimumCaptionCharacterLength' => 5,
					'minimumImageSize' => [
						'width' => 100
					],
				],
			],
			[
				'id' => 'section-image-recommendation',
				'extraData' => [
					'learnMoreLink' => null,
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType',
				'handlerId' => 'section-image-recommendation',
				'difficulty' => 'medium',
				'iconData' => [
					'icon' => 'robot-task-type-medium',
					'filterIcon' => 'robot',
					'descriptionMessageKey' => 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'settings' => [
					'maxTasksPerDay' => 25,
					'minimumCaptionCharacterLength' => 5,
					'minimumImageSize' => [
						'width' => 100
					],
				],
			],
			[
				'id' => 'link-recommendation',
				'extraData' => [
					'learnMoreLink' => '',
				],
				'_type_' => 'GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType',
				'handlerId' => 'link-recommendation',
				'difficulty' => 'easy',
				'iconData' => [
					'icon' => 'robot-task-type-easy',
					'filterIcon' => 'robot',
					'descriptionMessageKey' => 'growthexperiments-homepage-suggestededits-tasktype-machine-description',
				],
				'excludedTemplates' => [],
				'excludedCategories' => [],
				'settings' => [
					'minimumTasksPerTopic' => 500,
					'minimumLinksPerTask' => 2,
					'minimumLinkScore' => 0.6,
					'maximumLinksPerTask' => 10,
					'maximumLinksToShowPerTask' => 3,
					'minimumTimeSinceLastEdit' => 86400,
					'minimumWordCount' => 0,
					'maximumWordCount' => 9223372036854775807,
					'maxTasksPerDay' => 25,
					'underlinkedWeight' => 0.5,
					'underlinkedMinLength' => 300,
				],
			],
		], array_values( array_map( static fn ( $taskType ) => $taskType->jsonSerialize(), $taskTypes ) ) );
	}
}
