<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\FeatureManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\StaticTopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Psr\Log\NullLogger;
use StatusValue;

/**
 * @group API
 * @group medium
 * @group Database
 * @covers \GrowthExperiments\Api\ApiQueryGrowthTasks
 */
class ApiQueryGrowthTasksTest extends ApiTestCase {

	public function testNotLoggedIn() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You must be logged in.' );
		$this->doApiRequest(
			[ 'action' => 'query', 'list' => 'growthtasks' ],
			null,
			null,
			$this->getServiceContainer()->getUserFactory()->newAnonymous()
		);
	}

	public function testExecute() {
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$taskType3 = new TaskType( 'update', TaskType::DIFFICULTY_MEDIUM );
		$titleFactory = $this->getServiceContainer()->getTitleFactory();
		$copyEdit1 = $this->insertPage( 'Copyedit-1' );
		$link1 = $this->insertPage( 'Link-1' );
		$update1 = $this->insertPage( 'Update-1 ' );
		$copyedit2 = $this->insertPage( 'Copyedit-2' );
		$update2 = $this->insertPage( 'Update-2' );
		$copyedit3 = $this->insertPage( 'Copyedit-3 ' );
		$suggesterFactory = new StaticTaskSuggesterFactory( [
			new Task( $taskType1, $titleFactory->newFromID( $copyEdit1['id'] ) ),
			new Task( $taskType2, $titleFactory->newFromID( $link1['id'] ) ),
			new Task( $taskType3, $titleFactory->newFromID( $update1['id'] ) ),
			new Task( $taskType1, $titleFactory->newFromID( $copyedit2['id'] ) ),
			new Task( $taskType3, $titleFactory->newFromID( $update2['id'] ) ),
			new Task( $taskType1, $titleFactory->newFromID( $copyedit3['id'] ) ),
		], $this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			new NullLogger() );
		$configurationLoader = new StaticConfigurationLoader( [ $taskType1, $taskType2, $taskType3 ] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		$baseParams = [
			'action' => 'query',
			'list' => 'growthtasks',
		];

		[ $data ] = $this->doApiRequest( $baseParams );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertSame( 'Copyedit-1', $data['query']['growthtasks']['suggestions'][0]['title'] );
		$this->assertSame( 'copyedit', $data['query']['growthtasks']['suggestions'][0]['tasktype'] );
		$this->assertSame( 'easy', $data['query']['growthtasks']['suggestions'][0]['difficulty'] );
		$this->assertSame( 0, $data['query']['growthtasks']['suggestions'][0]['order'] );
		$this->assertSame( [], $data['query']['growthtasks']['suggestions'][0]['qualityGateIds'] );
		$this->assertSame( [], $data['query']['growthtasks']['suggestions'][0]['qualityGateConfig'] );
		$this->assertMatchesRegularExpression(
			"/^[a-z0-9]{32}+$/",
			$data['query']['growthtasks']['suggestions'][0]['token']
		);

		$this->assertResponseContainsTitles( [ 'Copyedit-1', 'Link-1', 'Update-1', 'Copyedit-2',
			'Update-2', 'Copyedit-3' ], $data );

		[ $data ] = $this->doApiRequest( $baseParams + [ 'gttasktypes' => 'update|link' ] );
		$this->assertResponseContainsTitles( [ 'Link-1', 'Update-1', 'Update-2' ], $data );
		$this->assertSame( 3, $data['query']['growthtasks']['totalCount'] );

		[ $data ] = $this->doApiRequest( $baseParams + [ 'gtlimit' => '2', 'gtoffset' => 3 ] );
		$this->assertResponseContainsTitles( [ 'Copyedit-2', 'Update-2' ], $data );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertSame( 5, $data['continue']['gtoffset'] );

		[ $data ] = $this->doApiRequest( $baseParams + [ 'gtlimit' => '2', 'gtoffset' => 4 ] );
		$this->assertResponseContainsTitles( [ 'Update-2', 'Copyedit-3' ], $data );
		$this->assertSame( 6, $data['query']['growthtasks']['totalCount'] );
		$this->assertArrayNotHasKey( 'continue', $data );
	}

	public function testExecuteGenerator() {
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$titleFactory = $this->getServiceContainer()->getTitleFactory();
		$task1 = $this->insertPage( 'Task-1' );
		$task2 = $this->insertPage( 'Task-2' );
		$suggesterFactory = new StaticTaskSuggesterFactory( [
			new Task( $taskType, $titleFactory->newFromID( $task1['id'] ) ),
			new Task( $taskType, $titleFactory->newFromID( $task2['id'] ) ),
		], $this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			new NullLogger() );
		$configurationLoader = new StaticConfigurationLoader( [ $taskType ] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		[ $data ] = $this->doApiRequest( [ 'action' => 'query', 'generator' => 'growthtasks' ] );
		$page = array_first( $data['query']['pages'] );
		$this->assertSame( 2, $data['growthtasks']['totalCount'] );
		$this->assertSame( 0, $page['ns'] );
		$this->assertSame( 'Task-1', $page['title'] );
		$this->assertSame( 'copyedit', $page['tasktype'] );
		$this->assertSame( TaskType::DIFFICULTY_EASY, $page['difficulty'] );
		$this->assertSame( 0, $page['order'] );
		$this->assertSame( [], $page['qualityGateIds'] );
		$this->assertSame( [], $page['qualityGateConfig'] );
		$this->assertMatchesRegularExpression( "/^[a-z0-9]{32}+$/", $page['token'] );
	}

	public function testInterestsParam() {
		$recorder = $this->setUpRecordingTaskSuggester();

		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'growthtasks',
			'gttasktypes' => 'copyedit',
			'gtinterests' => 'Albert Einstein|coffee',
		] );

		$this->assertSame( [ 'Albert Einstein', 'Coffee' ], $recorder->filters->getInterestFilters() );
		$this->assertSame( [], $recorder->filters->getTopicFilters() );
		$this->assertSame( [ 'copyedit' ], $recorder->filters->getTaskTypeFilters() );
		$this->assertFalse( $recorder->options['useCache'] );
	}

	/**
	 * The interests that cannot get suggestions do not reach the task suggester, and the
	 * response says which ones the module dropped.
	 * @dataProvider provideUnusableInterests
	 * @param string $interests
	 * @param string[] $expectedInterests
	 * @param string|null $expectedWarning
	 */
	public function testInterestsDropsUnusableValues(
		string $interests, array $expectedInterests, ?string $expectedWarning
	) {
		$recorder = $this->setUpRecordingTaskSuggester();

		[ $data ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'growthtasks',
			'gttasktypes' => 'copyedit',
			'gtinterests' => $interests,
			'errorformat' => 'plaintext',
			'errorlang' => 'en',
		] );

		$this->assertSame( $expectedInterests, $recorder->filters->getInterestFilters() );
		$this->assertSame( $expectedWarning, $data['warnings'][0]['text'] ?? null );
		// An explicit selection stays a preview, even when nothing of it can be used.
		$this->assertFalse( $recorder->options['useCache'] );
	}

	public static function provideUnusableInterests(): array {
		return [
			'usable interests do not warn' => [
				'Coffee|Tea', [ 'Coffee', 'Tea' ], null,
			],
			'a talk page cannot be an interest' => [
				'Coffee|Talk:Tea',
				[ 'Coffee' ],
				'Only articles can be interests. This value is ignored: Talk:Tea.',
			],
		];
	}

	/**
	 * A selection which cannot get any suggestion is a mistake of the caller. The unfiltered
	 * suggestions are no answer to it, so the module fails instead.
	 */
	public function testInterestsRejectsSelectionWithoutUsableValues() {
		$this->setUpRecordingTaskSuggester();
		$this->expectApiErrorCode( 'growthexperiments-no-usable-interests' );
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'growthtasks',
			'gttasktypes' => 'copyedit',
			'gtinterests' => 'Talk:Tea|File:Coffee.png',
		] );
	}

	public function testInterestsRejectsInvalidTitle() {
		$this->setUpRecordingTaskSuggester();
		$this->expectApiErrorCode( 'badtitle' );
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'growthtasks',
			'gtinterests' => 'Coffee|[[x',
		] );
	}

	public function testInterestsRejectsMoreThanTen() {
		$this->setUpRecordingTaskSuggester();
		$interests = array_map( static fn ( int $i ) => "Interest $i", range( 1, 11 ) );
		$this->expectApiErrorCode( 'toomanyvalues' );
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'growthtasks',
			'gtinterests' => implode( '|', $interests ),
		] );
	}

	public function testInterestsAndTopicsAreExclusive() {
		$this->setUpRecordingTaskSuggester();
		$this->setService( 'GrowthExperimentsTopicRegistry', new StaticTopicRegistry( [ new Topic( 'art' ) ] ) );
		$this->expectApiErrorCode( 'invalidparammix' );
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'growthtasks',
			'gttopics' => 'art',
			'gtinterests' => 'Coffee',
		] );
	}

	/**
	 * @dataProvider provideStoredInterests
	 */
	public function testStoredInterestsFallback( bool $isTreatment, array $expectedFilterJson ) {
		$recorder = $this->setUpRecordingTaskSuggester();
		$user = $this->setUpUserWithStoredInterests( $isTreatment );

		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'growthtasks',
			'gttasktypes' => 'copyedit',
		], null, null, $user );

		$this->assertSame( $expectedFilterJson, $recorder->filters->toJsonArray() );
		$this->assertTrue( $recorder->options['useCache'] );
	}

	public static function provideStoredInterests(): array {
		return [
			'treatment user gets stored interests' => [
				true,
				[
					'task' => [ 'copyedit' ],
					'topic' => [],
					'topicMode' => 'OR',
					'interests' => [ 'Albert Einstein', 'Coffee' ],
				],
			],
			'control user filters are unchanged' => [
				false,
				[
					'task' => [ 'copyedit' ],
					'topic' => [],
					'topicMode' => 'OR',
				],
			],
		];
	}

	/**
	 * An empty gtinterests= is an explicit empty selection, not an absent parameter.
	 * @dataProvider provideInterestsWithoutValues
	 */
	public function testInterestsWithoutValues(
		?string $interests, array $expectedInterests, bool $expectedUseCache
	) {
		$recorder = $this->setUpRecordingTaskSuggester();
		$user = $this->setUpUserWithStoredInterests( true );

		$params = [
			'action' => 'query',
			'list' => 'growthtasks',
			'gttasktypes' => 'copyedit',
		];
		if ( $interests !== null ) {
			$params['gtinterests'] = $interests;
		}
		$this->doApiRequest( $params, null, null, $user );

		$this->assertSame( $expectedInterests, $recorder->filters->getInterestFilters() );
		$this->assertSame( $expectedUseCache, $recorder->options['useCache'] );
	}

	public static function provideInterestsWithoutValues(): array {
		return [
			'absent parameter uses the stored interests' => [
				null, [ 'Albert Einstein', 'Coffee' ], true,
			],
			'empty parameter selects no interests' => [
				'', [], false,
			],
		];
	}

	public function testError() {
		$suggesterFactory = new StaticTaskSuggesterFactory(
			new ErrorForwardingTaskSuggester(
				StatusValue::newFatal( new ApiRawMessage( 'foo' ) )
			),
			$this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			new NullLogger()
		);
		$configurationLoader = new StaticConfigurationLoader( [] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'foo' );

		$this->doApiRequest( [ 'action' => 'query', 'list' => 'growthtasks' ] );
	}

	public function testGetAllowedParams() {
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		$topic1 = new Topic( 'art' );
		$topic2 = new Topic( 'science' );
		$suggesterFactory = new StaticTaskSuggesterFactory(
			[],
			$this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			new NullLogger()
		);
		$configurationLoader = new StaticConfigurationLoader( [ $taskType1, $taskType2 ] );
		$topicRegistry = new StaticTopicRegistry( [ $topic1, $topic2 ] );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$this->setService( 'GrowthExperimentsTopicRegistry', $topicRegistry );

		[ $data ] = $this->doApiRequest( [ 'action' => 'paraminfo',
			'modules' => 'query+growthtasks' ] );
		$this->assertArrayHasKey( 'paraminfo', $data );
		$this->assertArrayHasKey( 0, $data['paraminfo']['modules'] );
		$this->assertSame( 'growthtasks', $data['paraminfo']['modules'][0]['name'] );
		$this->assertArrayHasKey( 1, $data['paraminfo']['modules'][0]['parameters'] );
		$this->assertSame( 'tasktypes', $data['paraminfo']['modules'][0]['parameters'][0]['name'] );
		$this->assertSame( 'topics', $data['paraminfo']['modules'][0]['parameters'][1]['name'] );
		$this->assertSame( [ 'copyedit', 'link' ],
			$data['paraminfo']['modules'][0]['parameters'][0]['type'] );
		$this->assertSame( [ 'art', 'science' ],
			$data['paraminfo']['modules'][0]['parameters'][1]['type'] );
		$interestsParam = $data['paraminfo']['modules'][0]['parameters'][3];
		$this->assertSame( 'interests', $interestsParam['name'] );
		$this->assertSame( 'title', $interestsParam['type'] );
		$this->assertTrue( $interestsParam['multi'] );
		$this->assertSame( TaskSetFiltersFactory::MAX_INTERESTS, $interestsParam['limit'] );
		$this->assertSame( TaskSetFiltersFactory::MAX_INTERESTS, $interestsParam['highlimit'] );
		$this->assertArrayHasKey( 'paraminfo', $data );

		// Make sure loading errors do not break parameter info
		$suggesterFactory = new StaticTaskSuggesterFactory(
			[],
			$this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			new NullLogger()
		);
		$configurationLoader = new StaticConfigurationLoader( StatusValue::newFatal( 'foo' ),
			StatusValue::newFatal( 'bar' ) );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $suggesterFactory );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		[ $data ] = $this->doApiRequest( [ 'action' => 'paraminfo',
			'modules' => 'query+growthtasks' ] );
		$this->assertArrayHasKey( 'paraminfo', $data );
	}

	/**
	 * Create a user with two stored interests, and a filters factory that puts the user in the
	 * given group of the early-onboarding experiment.
	 */
	private function setUpUserWithStoredInterests( bool $isTreatment ): User {
		$user = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, AccountSetupHooks::INTEREST_ARTICLES_PROP,
			json_encode( [ 'Albert Einstein', 'Coffee' ] ) );
		$userOptionsManager->saveOptions( $user );

		$featureManager = $this->createMock( FeatureManager::class );
		$featureManager->method( 'isEarlyOnboardingExperimentTreatment' )->willReturn( $isTreatment );
		$featureManager->method( 'isNewcomerTasksAvailable' )->willReturn( true );
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->setService( 'GrowthExperimentsTaskSetFiltersFactory',
			new TaskSetFiltersFactory(
				$growthServices->getNewcomerTasksUserOptionsLookup(),
				$featureManager
			)
		);
		return $user;
	}

	/**
	 * Replace the task suggester with one that records the filters and options it receives.
	 * @return TaskSuggester&object{filters: ?TaskSetFilters, options: array}
	 */
	private function setUpRecordingTaskSuggester() {
		$recorder = new class implements TaskSuggester {
			public ?TaskSetFilters $filters = null;
			public array $options = [];

			/** @inheritDoc */
			public function suggest(
				UserIdentity $user,
				TaskSetFilters $taskSetFilters,
				?int $limit = null,
				?int $offset = null,
				array $options = []
			) {
				$this->filters = $taskSetFilters;
				$this->options = $options;
				return new TaskSet( [], 0, 0, $taskSetFilters );
			}

			/** @inheritDoc */
			public function filter( UserIdentity $user, TaskSet $taskSet ) {
				return $taskSet;
			}
		};
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', new StaticTaskSuggesterFactory(
			$recorder,
			$this->getServiceContainer()->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			new NullLogger()
		) );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader',
			new StaticConfigurationLoader( [ new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY ) ] ) );
		return $recorder;
	}

	/**
	 * @param string[] $titles
	 * @param array $response
	 */
	protected function assertResponseContainsTitles( array $titles, array $response ) {
		$this->assertSame(
			$titles,
			array_column( $response['query']['growthtasks']['suggestions'], 'title' )
		);
	}

}
