<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use CirrusSearch\Query\ArticleTopicFeature;
use GrowthExperiments\Maintenance\ListTaskCounts;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\Topic\StaticTopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Context\RequestContext;
use MediaWiki\Json\FormatJson;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\NullLogger;
use StatusValue;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \GrowthExperiments\Maintenance\ListTaskCounts
 */
class ListTaskCountsTest extends MaintenanceBaseTestCase {

	private const EXPECTED_GROWTH_STATS = [
		'taskCounts' => [
			'copyedit' => [ 'art' => 2, 'biography' => 1 ],
			'links' => [ 'art' => 0, 'biography' => 1 ],
		],
		'taskTypeCounts' => [ 'copyedit' => 3, 'links' => 1 ],
		'topicCounts' => [ 'art' => 2, 'biography' => 2 ],
	];

	protected function getMaintenanceClass(): string {
		return ListTaskCounts::class;
	}

	protected function setUp(): void {
		// FeatureManager::isNewcomerTasksAvailable() needs WikimediaMessages.
		$this->markTestSkippedIfExtensionNotLoaded( 'WikimediaMessages' );
		parent::setUp();

		$this->overrideConfigValues( [
			'GEHomepageSuggestedEditsEnabled' => true,
			'GENewcomerTasksRemoteApiUrl' => null,
		] );

		$copyedit = new TemplateBasedTaskType(
			'copyedit', TaskType::DIFFICULTY_EASY, [], [ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ]
		);
		$copyedit->setHandlerId( TemplateBasedTaskTypeHandler::ID );
		$links = new TemplateBasedTaskType(
			'links', TaskType::DIFFICULTY_EASY, [], [ new TitleValue( NS_TEMPLATE, 'Underlinked' ) ]
		);
		$links->setHandlerId( TemplateBasedTaskTypeHandler::ID );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader',
			new StaticConfigurationLoader( [ $copyedit, $links ] ) );
		$this->setService( 'GrowthExperimentsTopicRegistry', new StaticTopicRegistry( [
			new Topic( 'art' ),
			new Topic( 'biography' ),
		] ) );
		$this->setTaskSuggester( new StaticTaskSuggester( [
			$this->makeTask( $copyedit, 'Copyedit-1', [ 'art' ] ),
			$this->makeTask( $copyedit, 'Copyedit-2', [ 'art', 'biography' ] ),
			$this->makeTask( $copyedit, 'Copyedit-3', [] ),
			$this->makeTask( $links, 'Links-1', [ 'biography' ] ),
		] ) );
	}

	private function makeTask( TaskType $taskType, string $title, array $topicIds ): Task {
		$task = new Task( $taskType, new TitleValue( NS_MAIN, $title ) );
		$task->setTopics( array_map( static fn ( string $id ) => new Topic( $id ), $topicIds ) );
		return $task;
	}

	private function setTaskSuggester( TaskSuggester $taskSuggester ): void {
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', new StaticTaskSuggesterFactory(
			$taskSuggester,
			$this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			new NullLogger()
		) );
	}

	private function runScript( array $options ): void {
		$this->maintenance->loadParamsAndArgs( null, $options );
		$this->maintenance->execute();
	}

	private function getCachedInfo(): mixed {
		$cache = $this->getServiceContainer()->getMainWANObjectCache();
		return $cache->get( $cache->makeKey( 'growthexperiments-SuggestionsInfo' ) );
	}

	public function testNewcomerTasksNotAvailable(): void {
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', false );

		$this->runScript( [] );

		$this->expectOutputString( "Newcomer tasks not available\n" );
	}

	public function testRemoteApiUrl(): void {
		$this->overrideConfigValue( 'GENewcomerTasksRemoteApiUrl', 'https://en.wikipedia.org/w/api.php' );

		$this->runScript( [] );

		$this->expectOutputString( "Local tasks disabled\n" );
	}

	public static function provideInvalidOptions(): iterable {
		yield 'invalid topic type' => [
			[ 'topictype' => 'foo' ],
			'topictype must be one of: growth, ores',
		];
		yield 'invalid task type' => [
			[ 'tasktype' => [ 'copyedit', 'foo' ] ],
			'Invalid task types: foo',
		];
		yield 'invalid topic' => [
			[ 'topic' => [ 'art', 'foo' ] ],
			'Invalid topics: foo',
		];
	}

	/**
	 * @dataProvider provideInvalidOptions
	 */
	public function testInvalidOptions( array $options, string $expectedError ): void {
		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/' . preg_quote( $expectedError, '/' ) . '/' );

		$this->runScript( $options );
	}

	public function testJsonOutput(): void {
		$this->runScript( [ 'output' => 'json' ] );

		$this->assertSame(
			self::EXPECTED_GROWTH_STATS,
			FormatJson::decode( $this->getActualOutputForAssertion(), true )
		);
	}

	public function testJsonOutputWithFilters(): void {
		$this->runScript( [
			'output' => 'json',
			'tasktype' => [ 'links' ],
			'topic' => [ 'biography' ],
		] );

		$this->assertSame(
			[
				'taskCounts' => [ 'links' => [ 'biography' => 1 ] ],
				'taskTypeCounts' => [ 'links' => 1 ],
				'topicCounts' => [ 'biography' => 2 ],
			],
			FormatJson::decode( $this->getActualOutputForAssertion(), true )
		);
	}

	public function testAsciiTableOutput(): void {
		$this->runScript( [] );

		$this->expectOutputString(
			"Topic                     copyedit   links      \n" .
			str_repeat( '-', 80 ) . "\n" .
			"art                       2          0          \n" .
			"biography                 1          1          \n"
		);
	}

	public function testNoOutput(): void {
		$this->runScript( [ 'output' => 'none' ] );

		$this->expectOutputString( '' );
	}

	public function testGrowthTopicsAreCached(): void {
		$this->runScript( [ 'output' => 'none' ] );

		$cachedInfo = $this->getCachedInfo();
		$this->assertIsArray( $cachedInfo );
		$this->assertSame( 4, $cachedInfo['totalCount'] );
	}

	public function testOresTopics(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$this->runScript( [ 'output' => 'json', 'topictype' => 'ores' ] );

		$result = FormatJson::decode( $this->getActualOutputForAssertion(), true );
		$oresTopics = array_keys( ArticleTopicFeature::TERMS_TO_LABELS );
		$this->assertSame( $oresTopics, array_keys( $result['topicCounts'] ) );
		$this->assertSame( $oresTopics, array_keys( $result['taskCounts']['copyedit'] ) );
		$this->assertSame( [ 'copyedit' => 3, 'links' => 1 ], $result['taskTypeCounts'] );
		$this->assertSame( 2, $result['topicCounts']['biography'] );
		$this->assertSame( 1, $result['taskCounts']['copyedit']['biography'] );
		$this->assertSame( 1, $result['taskCounts']['links']['biography'] );
		// The 'art' Growth topic is not an ORES topic.
		$this->assertArrayNotHasKey( 'art', $result['topicCounts'] );
		// Only Growth topic counts are cached for Special:NewcomerTasksInfo.
		$this->assertFalse( $this->getCachedInfo() );
	}

	public function testStatsd(): void {
		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->setService( 'StatsFactory', $statsHelper->getStatsFactory() );

		$this->runScript( [ 'output' => 'none', 'statsd' => true ] );

		$wiki = WikiMap::getCurrentWikiId();
		$this->assertSame(
			[
				"mediawiki.GrowthExperiments.tasktype_count:3|c|#wiki:$wiki,tasktype:copyedit",
				"mediawiki.GrowthExperiments.tasktype_count:1|c|#wiki:$wiki,tasktype:links",
			],
			$statsHelper->consumeAllFormatted()
		);
	}

	private function makeLinksSearchFail(): void {
		$staticSuggester = $this->getServiceContainer()->get( 'GrowthExperimentsTaskSuggesterFactory' )->create();
		$suggester = $this->createMock( TaskSuggester::class );
		$suggester->method( 'suggest' )->willReturnCallback(
			static function (
				UserIdentity $user, TaskSetFilters $filters, ...$args
			) use ( $staticSuggester ) {
				if ( $filters->getTaskTypeFilters() === [ 'links' ] ) {
					return StatusValue::newFatal( 'rawmessage', 'Search failed' );
				}
				return $staticSuggester->suggest( $user, $filters, ...$args );
			}
		);
		$this->setTaskSuggester( $suggester );
	}

	public function testJsonOutputWithFailedTaskType(): void {
		$this->makeLinksSearchFail();

		$this->runScript( [ 'output' => 'json' ] );

		$result = FormatJson::decode( $this->getActualOutputForAssertion(), true );
		$this->assertSame( [ 'copyedit' => 3, 'links' => -1 ], $result['taskTypeCounts'] );
		$this->assertSame( [ 'art' => -1, 'biography' => -1 ], $result['taskCounts']['links'] );
		$this->assertSame( [ 'art' => 2, 'biography' => 1 ], $result['topicCounts'] );
	}

	public function testStatsdSkipsFailedTaskType(): void {
		$this->makeLinksSearchFail();
		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->setService( 'StatsFactory', $statsHelper->getStatsFactory() );

		$this->runScript( [ 'output' => 'none', 'statsd' => true ] );

		$wiki = WikiMap::getCurrentWikiId();
		$this->assertSame(
			[ "mediawiki.GrowthExperiments.tasktype_count:3|c|#wiki:$wiki,tasktype:copyedit" ],
			$statsHelper->consumeAllFormatted()
		);
		$this->expectOutputString( "Cannot get the task count for task type links\n" );
	}

}
