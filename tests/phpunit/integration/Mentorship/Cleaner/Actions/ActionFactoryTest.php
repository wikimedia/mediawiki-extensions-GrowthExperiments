<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Cleaner\Actions\ActionFactory;
use GrowthExperiments\Mentorship\Cleaner\Actions\IAction;
use InvalidArgumentException;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\Mentorship\Cleaner\Actions\ActionFactory
 */
class ActionFactoryTest extends MediaWikiIntegrationTestCase {

	private function getActionFactory(): ActionFactory {
		return GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getActionFactory();
	}

	/**
	 * Verify that every action listed in ActionFactory::ACTIONS can be constructed with its
	 * real dependencies wired through ServiceWiring.
	 *
	 * @dataProvider provideActions
	 */
	public function testNewFromClassName( string $class ) {
		$this->assertInstanceOf( IAction::class, $this->getActionFactory()->newFromClassName( $class ) );
	}

	public static function provideActions() {
		foreach ( ActionFactory::ACTIONS as $class ) {
			yield $class => [ $class ];
		}
	}

	public function testNewFromClassNameCaches() {
		$actionFactory = $this->getActionFactory();
		$class = ActionFactory::ACTIONS[0];
		$this->assertSame(
			$actionFactory->newFromClassName( $class ),
			$actionFactory->newFromClassName( $class ),
			'newFromClassName() should return the cached instance on repeated calls'
		);
	}

	public function testNewFromClassNameRejectsUnknownClass() {
		$this->expectException( InvalidArgumentException::class );
		$this->getActionFactory()->newFromClassName( 'GrowthExperiments\\NotAnAction' );
	}
}
