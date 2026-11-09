<?php

namespace GrowthExperiments\Tests\Unit\NewcomerTasks\Topic;

use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\NewcomerTasks\Topic\WikimediaTopicRegistry;
use MediaWiki\Collation\Collation;
use MediaWiki\Collation\CollationFactory;
use MediaWiki\Collation\UppercaseCollation;
use MediaWiki\Extension\WikimediaMessages\ArticleTopicFiltersRegistry;
use MediaWiki\Language\LanguageFactory;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\WikimediaTopicRegistry
 */
class WikimediaTopicRegistryTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideTestGetTopics
	 */
	public function testGetTopics( array $topics, array $expected ) {
		$registry = $this->getWikimediaTopicRegistry( $topics );
		$actual = $registry->getTopics();
		$this->assertSameSize( $expected, $actual );

		foreach ( $actual as $topic ) {
			$this->assertInstanceOf( OresBasedTopic::class, $topic );
		}
	}

	public static function provideTestGetTopics(): iterable {
		if ( class_exists( ArticleTopicFiltersRegistry::class ) ) {
			# Test case requires the WikimediaMessages extension
			yield 'All WM topics recognized' => [
				ArticleTopicFiltersRegistry::getTopicList(),
				WikimediaTopicRegistry::GROWTH_ORES_TOPICS,
			];
		}
		yield 'Some WM topics recognized' => [
			[ 'africa', 'women', 'unkown', 'oceania', 'foo', 'bar' ],
			[ 'africa', 'women', 'oceania' ],
		];
		yield 'No topics recognized' => [
			[],
			[],
		];
	}

	public function testGetCampaignTopics() {
		if ( !class_exists( ArticleTopicFiltersRegistry::class ) ) {
			$this->markTestSkipped( 'Extension WikimediaMessages is required for this test' );
		}

		$registry = $this->getWikimediaTopicRegistry( ArticleTopicFiltersRegistry::getTopicList() );
		$registry->setCampaignConfigCallback( function () {
			return new CampaignConfig(
				[
					'latin-campaign' => [
						'topics' => [ 'argentina' ],
					],
				],
				[
					'argentina' => 'growtharticletopic:argentina',
				],
				$this->createNoOpMock( UserOptionsLookup::class )
			);
		} );
		$actual = $registry->getTopics();
		$expected = array_merge( WikimediaTopicRegistry::GROWTH_ORES_TOPICS, [
			new CampaignTopic( 'argentina', 'growtharticletopic:argentina' ),
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
				$collationFactoryMock,
			] )
			->onlyMethods( [ 'getAllTopics', 'sortTopics' ] )
			->getMock();
		$registry->method( 'getAllTopics' )
			->willReturn( $topics );
		$registry->method( 'sortTopics' )
			->willReturnArgument( 0 );
		return $registry;
	}

	public function testGetAllTopics(): void {
		if ( !class_exists( ArticleTopicFiltersRegistry::class ) ) {
			$this->markTestSkipped( 'Extension WikimediaMessages is required for this test' );
		}

		$registry = new WikimediaTopicRegistry(
			$this->createNoOpMock( MessageLocalizer::class ),
			$this->createNoOpMock( CollationFactory::class )
		);
		$this->assertSame( ArticleTopicFiltersRegistry::getTopicList(), $registry->getAllTopics() );
	}

	public function testGetTopicsReturnsCachedTopics(): void {
		$registry = $this->getWikimediaTopicRegistry( [ 'africa', 'women' ] );
		$this->assertSame( $registry->getTopics(), $registry->getTopics() );
	}

	public function testGetTopicsMap(): void {
		$registry = $this->getWikimediaTopicRegistry( [ 'africa', 'unknown', 'women' ] );
		$topicsMap = $registry->getTopicsMap();

		$this->assertSame( [ 'africa', 'women' ], array_keys( $topicsMap ) );
		foreach ( $topicsMap as $topicId => $topic ) {
			$this->assertSame( $topicId, $topic->getId() );
		}
	}

	public function testSortTopics(): void {
		$registry = $this->getSortingWikimediaTopicRegistry( $this->getMessageLocalizer( [
			'topic-a' => 'Zulu',
			'topic-b' => 'Alpha',
			'topic-c' => 'Mike',
			'topic-d' => 'Bravo',
		] ) );
		$topics = [
			new Topic( 'topic-a', 'culture' ),
			new Topic( 'topic-b', 'culture' ),
			new Topic( 'topic-c', 'science' ),
			new Topic( 'topic-d', 'science' ),
		];

		$registry->sortTopics( $topics, [ 'science', 'culture' ] );
		$this->assertSame( [ 'topic-d', 'topic-c', 'topic-b', 'topic-a' ], $this->getTopicIds( $topics ) );
	}

	public function testSortTopicsWithoutTopics(): void {
		$registry = new WikimediaTopicRegistry(
			$this->createNoOpMock( MessageLocalizer::class ),
			$this->createNoOpMock( CollationFactory::class )
		);
		$topics = [];

		$registry->sortTopics( $topics, [ 'culture' ] );
		$this->assertSame( [], $topics );
	}

	public function testSetMessageLocalizer(): void {
		$registry = $this->getSortingWikimediaTopicRegistry( $this->createNoOpMock( MessageLocalizer::class ) );
		$registry->setMessageLocalizer( $this->getMessageLocalizer( [
			'topic-a' => 'Zulu',
			'topic-b' => 'Alpha',
		] ) );
		$topics = [
			new Topic( 'topic-a', 'culture' ),
			new Topic( 'topic-b', 'culture' ),
		];

		$registry->sortTopics( $topics, [ 'culture' ] );
		$this->assertSame( [ 'topic-b', 'topic-a' ], $this->getTopicIds( $topics ) );
	}

	private function getSortingWikimediaTopicRegistry( MessageLocalizer $messageLocalizer ): WikimediaTopicRegistry {
		$collation = $this->createMock( Collation::class );
		$collation->method( 'getSortKey' )
			->willReturnArgument( 0 );
		$collationFactory = $this->createMock( CollationFactory::class );
		$collationFactory->method( 'getCategoryCollation' )
			->willReturn( $collation );
		return new WikimediaTopicRegistry( $messageLocalizer, $collationFactory );
	}

	/**
	 * @param string[] $topicNames Topic names, keyed by topic ID
	 */
	private function getMessageLocalizer( array $topicNames ): MessageLocalizer {
		$messages = [];
		foreach ( $topicNames as $topicId => $topicName ) {
			$messages["growthexperiments-homepage-suggestededits-topic-name-$topicId"] = $topicName;
		}
		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$messageLocalizer->method( 'msg' )
			->willReturnCallback( fn ( string $key ) => $this->getMockMessage( $messages[$key] ) );
		return $messageLocalizer;
	}

	/**
	 * @param Topic[] $topics
	 * @return string[]
	 */
	private function getTopicIds( array $topics ): array {
		return array_map( static fn ( Topic $topic ) => $topic->getId(), $topics );
	}
}
