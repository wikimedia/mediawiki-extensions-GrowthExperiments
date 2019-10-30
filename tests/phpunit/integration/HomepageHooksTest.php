<?php

namespace GrowthExperiments;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use RawMessage;
use RequestContext;
use ResourceLoaderContext;
use StatusValue;

/**
 * @coversDefaultClass \GrowthExperiments\HomepageHooks
 */
class HomepageHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::getTaskTypesJson
	 */
	public function testGetTaskTypesJson() {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->setMethods( [ 'loadTaskTypes', 'loadTemplateBlacklist', 'setMessageLocalizer' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
			->willReturn( [
				new TaskType( 'tt1', TaskType::DIFFICULTY_EASY ),
				new TaskType( 'tt2', TaskType::DIFFICULTY_EASY ),
			] );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );
		$context = new ResourceLoaderContext( MediaWikiServices::getInstance()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ 'tt1', 'tt2' ], array_keys( $configData ) );
	}

	/**
	 * @covers ::getTaskTypesJson
	 */
	public function testGetTaskTypesJson_error() {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->setMethods( [ 'loadTaskTypes', 'loadTemplateBlacklist', 'setMessageLocalizer' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
			->willReturn( StatusValue::newFatal( new RawMessage( 'foo' ) ) );
		$this->setService( 'GrowthExperimentsConfigurationLoader', $configurationLoader );
		$context = new ResourceLoaderContext( MediaWikiServices::getInstance()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ '_error' => 'foo' ], $configData );
	}

}
