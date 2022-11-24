<?php

namespace GrowthExperiments\Tests\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationImage;
use GrowthExperiments\NewcomerTasks\AddImage\StaticImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWikiUnitTestCase;
use StatusValue;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\StaticImageRecommendationProvider
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation
 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationImage
 */
class StaticImageRecommendationProviderTest extends MediaWikiUnitTestCase {

	public function testGet() {
		$provider = new StaticImageRecommendationProvider( [
			'0:Foo' => new ImageRecommendation(
				new TitleValue( NS_MAIN, 'Foo' ),
				[],
				'1'
			),
			'0:Bar' => [
				'titleNamespace' => NS_MAIN,
				'titleText' => 'Bar',
				'images' => [
					[
						'image' => 'One.png',
						'source' => ImageRecommendationImage::SOURCE_WIKIDATA,
					],
					[
						'image' => 'Two.png',
						'source' => ImageRecommendationImage::SOURCE_WIKIPEDIA,
						'projects' => [ 'enwiki', 'dewiki' ],
					],
				],
				'datasetId' => '2',
			],
			'0:Baz' => StatusValue::newFatal( 'failed' ),
		], StatusValue::newFatal( 'default' ) );
		$taskType = new ImageRecommendationTaskType( 'image', TaskType::DIFFICULTY_EASY );

		$recommendation = $provider->get( new TitleValue( NS_MAIN, 'Foo' ), $taskType );
		$this->assertInstanceOf( ImageRecommendation::class, $recommendation );
		$this->assertSame( 'Foo', $recommendation->getTitle()->getText() );
		$this->assertSame( [], $recommendation->getImages() );
		$this->assertSame( '1', $recommendation->getDatasetId() );

		$recommendation = $provider->get( new TitleValue( NS_MAIN, 'Bar' ), $taskType );
		$this->assertInstanceOf( ImageRecommendation::class, $recommendation );
		$this->assertSame( 'Bar', $recommendation->getTitle()->getText() );
		$this->assertArrayHasKey( 0, $recommendation->getImages() );
		$this->assertInstanceOf( ImageRecommendationImage::class, $recommendation->getImages()[0] );
		$this->assertSame( NS_FILE, $recommendation->getImages()[0]->getImageTitle()->getNamespace() );
		$this->assertSame( 'One.png', $recommendation->getImages()[0]->getImageTitle()->getText() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIDATA, $recommendation->getImages()[0]->getSource() );
		$this->assertSame( [], $recommendation->getImages()[0]->getProjects() );
		$this->assertInstanceOf( ImageRecommendationImage::class, $recommendation->getImages()[1] );
		$this->assertSame( 'Two.png', $recommendation->getImages()[1]->getImageTitle()->getText() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIPEDIA, $recommendation->getImages()[1]->getSource() );
		$this->assertSame( [ 'enwiki', 'dewiki' ], $recommendation->getImages()[1]->getProjects() );
		$this->assertSame( '2', $recommendation->getDatasetId() );

		$recommendation = $provider->get( new TitleValue( NS_MAIN, 'Baz' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$this->assertTrue( $recommendation->hasMessage( 'failed' ) );

		$recommendation = $provider->get( new TitleValue( NS_MAIN, 'Boom' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$this->assertTrue( $recommendation->hasMessage( 'default' ) );
	}

}
