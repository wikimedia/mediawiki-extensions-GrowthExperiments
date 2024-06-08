<?php

namespace GrowthExperiments\Tests\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationImage;
use GrowthExperiments\NewcomerTasks\AddImage\StaticImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use StatusValue;

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
						'sectionNumber' => null,
						'sectionTitle' => null,
					],
					[
						'image' => 'Two.png',
						'source' => ImageRecommendationImage::SOURCE_WIKIPEDIA,
						'projects' => [ 'enwiki', 'dewiki' ],
						'sectionNumber' => null,
						'sectionTitle' => null,
					],
					[
						'image' => 'Three.png',
						'source' => ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_TOPICS,
						'sectionNumber' => 2,
						'sectionTitle' => 'Foo',
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

		$images = $recommendation->getImages();
		$this->assertCount( 3, $recommendation->getImages() );
		$this->assertContainsOnlyInstancesOf( ImageRecommendationImage::class, $images );
		$this->assertSame( NS_FILE, $images[0]->getImageTitle()->getNamespace() );
		$this->assertSame( 'One.png', $images[0]->getImageTitle()->getText() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIDATA, $images[0]->getSource() );
		$this->assertSame( [], $images[0]->getProjects() );
		$this->assertSame( 'Two.png', $images[1]->getImageTitle()->getText() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIPEDIA, $images[1]->getSource() );
		$this->assertSame( [ 'enwiki', 'dewiki' ], $images[1]->getProjects() );
		$this->assertSame( 'Three.png', $images[2]->getImageTitle()->getText() );
		$this->assertSame( ImageRecommendationImage::SOURCE_WIKIDATA_SECTION_TOPICS, $images[2]->getSource() );
		$this->assertSame( [], $images[2]->getProjects() );
		$this->assertSame( 2, $images[2]->getSectionNumber() );
		$this->assertSame( 'Foo', $images[2]->getSectionTitle() );
		$this->assertSame( '2', $recommendation->getDatasetId() );

		$recommendation = $provider->get( new TitleValue( NS_MAIN, 'Baz' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$this->assertStatusError( 'failed', $recommendation );

		$recommendation = $provider->get( new TitleValue( NS_MAIN, 'Boom' ), $taskType );
		$this->assertInstanceOf( StatusValue::class, $recommendation );
		$this->assertStatusError( 'default', $recommendation );
	}

}
