<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * Verifies that the ObjectFactory decorator specs passed to DecoratingTaskSuggesterFactory in
 * ServiceWiring.php are correct.
 *
 * The specs live inline in the GrowthExperimentsTaskSuggesterFactory wiring on the CirrusSearch
 * branch, so the only way to exercise the real (non-duplicated) specs is to obtain the factory
 * through the service container and call create(). A spec with a reordered or mistyped argument
 * would throw a TypeError when ObjectFactory instantiates the decorator against its constructor.
 *
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory
 * @group Database
 */
class DecoratingTaskSuggesterFactoryTest extends MediaWikiIntegrationTestCase {

	public function testServiceWiringDecoratorSpecs() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$this->overrideConfigValue( MainConfigNames::SearchType, 'CirrusSearch' );
		$this->resetServices();

		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$factory = $growthServices->getTaskSuggesterFactory();
		$this->assertInstanceOf( DecoratingTaskSuggesterFactory::class, $factory );

		$this->assertInstanceOf( TaskSuggester::class, $factory->create() );
	}

}
