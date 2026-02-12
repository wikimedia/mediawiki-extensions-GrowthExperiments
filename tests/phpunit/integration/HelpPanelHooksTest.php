<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HelpPanelHooks;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use MediaWiki\Context\RequestContext;
use MediaWiki\ResourceLoader as RL;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\HelpPanelHooks
 * @group Database
 */
class HelpPanelHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * When Suggested Edits is disabled, getPreferredEditor
	 * must NOT call ConfigurationLoader::getTaskTypes().
	 */
	public function testGetModuleDataPreferredEditorWhenFeatureDisabled() {
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', false );
		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->expects( $this->never() )
			->method( 'getTaskTypes' );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoaderMock );

		$context = new RL\Context(
			$this->getServiceContainer()->getResourceLoader(),
			RequestContext::getMain()->getRequest()
		);
		$config = $this->getServiceContainer()->getMainConfig();

		// When feature is disabled, getPreferredEditor should return [] without touching ConfigurationLoader.
		$moduleData = HelpPanelHooks::getModuleData( $context, $config );

		$this->assertArrayHasKey( 'GEHelpPanelSuggestedEditsPreferredEditor', $moduleData );
		$this->assertSame( [], $moduleData['GEHelpPanelSuggestedEditsPreferredEditor'] );
	}

}
