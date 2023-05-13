<?php

namespace GrowthExperiments\Tests\Integration;

use ApiQueryTestBase;
use ApiUsageException;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationImage;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddImage\StaticImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\MainConfigNames;
use TitleValue;

/**
 * @covers \GrowthExperiments\Api\ApiQueryImageSuggestionData
 * @group Database
 */
class ApiQueryImageSuggestionDataTest extends ApiQueryTestBase {

	/** @inheritDoc */
	public function addDBDataOnce() {
		if ( $this->getExistingTestPage( 'ImageSuggestionDataTest' ) ) {
			return;
		}
		$this->editPage( 'ImageSuggestionDataTest', 'Test' );
	}

	public function testIsAnon() {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You must be logged in.' );
		$this->check( [
			[ 'prop' => 'growthimagesuggestiondata', 'titles' => 'ImageSuggestionDataTest' ],
			[]
		], [], false, $user );
	}

	public function testNoTaskType() {
		$this->overrideMwServices( null,
			[
				'GrowthExperimentsNewcomerTasksConfigurationLoader' => static function (): ConfigurationLoader {
					return new StaticConfigurationLoader(
						[]
					);
				},
			]
		);
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Invalid task type(s): image-recommendation' );
		$this->check( [
			[ 'prop' => 'growthimagesuggestiondata', 'titles' => 'ImageSuggestionDataTest' ],
			[]
		] );
	}

	public function testPingLimiter() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			"You've exceeded your rate limit. Please wait some time and try again."
		);
		$this->overrideConfigValue( MainConfigNames::RateLimits,
			[ 'growthexperiments-apiqueryimagesuggestiondata' => [ '&can-bypass' => false, 'user' => [ 0, 60 ] ] ]
		);
		$this->check( [
			[ 'prop' => 'growthimagesuggestiondata', 'titles' => 'ImageSuggestionDataTest' ],
			[]
		] );
	}

	public function testQuery() {
		$imageRecommendationTaskType = new ImageRecommendationTaskType(
			'image-recommendation', TaskType::DIFFICULTY_MEDIUM, []
		);

		$this->overrideMwServices( null,
			[
				'GrowthExperimentsNewcomerTasksConfigurationLoader' => static function () use (
					$imageRecommendationTaskType
				): ConfigurationLoader {
					return new StaticConfigurationLoader(
						[ $imageRecommendationTaskType ]
					);
				},
				'GrowthExperimentsImageRecommendationProviderUncached' =>
					static function (): ImageRecommendationProvider {
						return new StaticImageRecommendationProvider(
							[ '0:ImageSuggestionDataTest' => new ImageRecommendation(
								new TitleValue( NS_MAIN, 'ImageSuggestionDataTest' ),
								[ new ImageRecommendationImage(
									new TitleValue( NS_FILE, 'ImageSuggestionDataTestRecommendedImage' ),
									'test'
								) ],
								'testdataset'
							) ],
							new \StatusValue()
						);
					}
			]
		);

		$this->check( [
			[ 'prop' => 'growthimagesuggestiondata', 'titles' => 'ImageSuggestionDataTest' ],
			[
				'pages' => [
					'1' => [
						'pageid' => 1,
						'ns' => 0,
						'title' => 'ImageSuggestionDataTest',
						'growthimagesuggestiondata' => [ [
							'datasetId' => 'testdataset',
							'images' => [ [
								'displayFilename' => 'ImageSuggestionDataTestRecommendedImage',
								'image' => 'ImageSuggestionDataTestRecommendedImage',
								'metadata' => [],
								'projects' => [],
								'source' => 'test',
								'sectionNumber' => null,
								'sectionTitle' => null,
							] ],
							'titleNamespace' => 0,
							'titleText' => 'ImageSuggestionDataTest'
						] ]
					]
				]
			]
		] );
	}

}
