<?php

namespace phpunit\unit\NewcomerTasks\Topic;

use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\NewcomerTasks\Topic\WikimediaTopicRegistry;
use MediaWiki\Collation\CollationFactory;
use MediaWiki\Extension\WikimediaMessages\ArticleTopicFiltersRegistry;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockObject;
use UppercaseCollation;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\WikimediaTopicRegistry
 */
class WikimediaTopicRegistryTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideTestLoadTopics
	 */
	public function testLoadTopics( array $topics, array $expected ) {
		$registry = $this->getWikimediaTopicRegistry( $topics );
		$actual = $registry->loadTopics();
		$this->assertSameSize( $expected, $actual );

		foreach ( $actual as $topic ) {
			$this->assertInstanceOf( OresBasedTopic::class, $topic );
		}
	}

	public function provideTestLoadTopics(): iterable {
		yield 'All WM topics recognized' => [
			ArticleTopicFiltersRegistry::getTopicList(),
			WikimediaTopicRegistry::GROWTH_ORES_TOPICS
		];
		yield 'Some WM topics recognized' => [
			[ 'africa', 'women', 'unkown', 'oceania', 'foo', 'bar' ],
			[ 'africa', 'women', 'oceania' ]
		];
		yield 'No topics recognized' => [
			[],
			[]
		];
	}

	public function testGetTopics() {
		$registry = $this->getWikimediaTopicRegistry( ArticleTopicFiltersRegistry::getTopicList() );
		$registry->setCampaignConfigCallback( function () {
			return new CampaignConfig(
				[
					'latin-campaign' => [
						'topics' => [ 'argentina' ]
					]
				],
				[
					'argentina' => 'growtharticletopic:argentina'
				],
				$this->createNoOpMock( UserOptionsLookup::class )
			);
		} );
		$actual = $registry->getTopics();
		$expected = array_merge( WikimediaTopicRegistry::GROWTH_ORES_TOPICS, [
			new CampaignTopic( 'argentina', 'growtharticletopic:argentina' )
		] );
		$this->assertSameSize( $expected, $actual );

		foreach ( $actual as $topic ) {
			$this->assertInstanceOf( Topic::class, $topic );
		}
	}

	/**
	 * @return WikimediaTopicRegistry|MockObject
	 */
	private function getWikimediaTopicRegistry( array $topics ): WikimediaTopicRegistry {
		$collationFactoryMock = $this->createMock( CollationFactory::class );
		$collationFactoryMock
			->method( 'getCategoryCollation' )
			->willReturn( new UppercaseCollation(
				$this->createMock( LanguageFactory::class )
			) );
		$registry = $this->getMockBuilder( WikimediaTopicRegistry::class )
			->setConstructorArgs( [
				$this->createNoOpMock( MessageLocalizer::class, [ 'msg' ] ),
				$collationFactoryMock
			] )
			->onlyMethods( [ 'getAllTopics', 'sortTopics' ] )
			->getMock();
		$registry->method( 'getAllTopics' )
			->willReturn( $topics );

		// FIXME: sortTopics be also tested instead of mocked but the ArticleTopicFiltersRegistry::getTopicMessages
		// call in OresBasedTopic::getName requires work to be extracted or mocked
		$registry->method( 'sortTopics' )
			->willReturnArgument( 0 );
		return $registry;
	}
}
