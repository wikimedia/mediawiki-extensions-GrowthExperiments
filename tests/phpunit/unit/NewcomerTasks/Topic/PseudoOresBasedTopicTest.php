<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\PseudoOresBasedTopic;
use MediaWiki\Json\JsonCodec;
use MediaWikiUnitTestCase;
use StatusValue;
use TitleFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\PseudoOresBasedTopic
 */
class PseudoOresBasedTopicTest extends MediaWikiUnitTestCase {

	public function testJsonSerialization() {
		$codec = new JsonCodec();
		$topic = new PseudoOresBasedTopic( 'argentina', 'foo' );
		$topic2 = $codec->unserialize( $codec->serialize( $topic ) );
		$this->assertEquals( $topic, $topic2 );
	}

	public function testSearchStrategy() {
		$topic = new PseudoOresBasedTopic( 'argentina', 'foo' );
		$taskTypeHandler = $this->createNoOpMock( TaskTypeHandler::class, [ 'getSearchTerm' ] );
		$taskTypeHandler->method( 'getSearchTerm' )->willReturn( 'tasktype:foo' );
		$taskTypeHandlerRegistry = $this->createNoOpMock( TaskTypeHandlerRegistry::class,
			[ 'getByTaskType' ] );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );
		$queries = $searchStrategy->getQueries(
			[ new TaskType( 'foo', TaskType::DIFFICULTY_EASY ) ],
			[ $topic ]
		);
		$this->assertCount( 1, $queries );
		$this->assertArrayHasKey( 'foo:argentina', $queries );
		$query = $queries['foo:argentina'];
		$this->assertSame( $topic, $query->getTopic() );
		$this->assertSame( 'tasktype:foo growtharticletopic:argentina', $query->getQueryString() );
	}

	public function testConfigurationLoader() {
		$titleFactory = $this->createNoOpMock( TitleFactory::class );
		$pageLoader = $this->createNoOpMock( WikiPageConfigLoader::class );
		$configurationValidator = $this->createMock( ConfigurationValidator::class );
		$configurationValidator->method( $this->anything() )->willReturn( StatusValue::newGood() );
		$taskTypeHandlerRegistry = $this->createNoOpMock( TaskTypeHandlerRegistry::class );
		$configurationLoader = new PageConfigurationLoader( $titleFactory, $pageLoader,
			$configurationValidator, $taskTypeHandlerRegistry, '', '',
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );

		$topics = TestingAccessWrapper::newFromObject( $configurationLoader )
			->parseTopicsFromConfig( [ 'topics' => [], 'groups' => [] ] );
		$this->assertIsArray( $topics );
		$this->assertEmpty( $topics );

		$configurationLoader->useArgentinaTopic = true;
		$topics = TestingAccessWrapper::newFromObject( $configurationLoader )
			->parseTopicsFromConfig( [ 'topics' => [], 'groups' => [] ] );
		$this->assertIsArray( $topics );
		$this->assertCount( 1, $topics );
		$topic = $topics[0];
		$this->assertInstanceOf( PseudoOresBasedTopic::class, $topic );
		$this->assertSame( 'argentina', $topic->getId() );
	}

}
