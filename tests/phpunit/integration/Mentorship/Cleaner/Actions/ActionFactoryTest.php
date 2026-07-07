<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Cleaner\Actions\ActionFactory;
use GrowthExperiments\Mentorship\Cleaner\Actions\IAction;
use GrowthExperiments\Mentorship\Cleaner\Actions\MarkMentorAsAwayAction;
use InvalidArgumentException;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \GrowthExperiments\Mentorship\Cleaner\Actions\ActionFactory
 */
class ActionFactoryTest extends MediaWikiIntegrationTestCase {
	use CommunityConfigurationTestHelpers;

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

	/**
	 * @dataProvider provideAwayDuration
	 */
	public function testAwayDuration( int $expected, array $config ) {
		$this->overrideProviderConfig( $config, 'Mentorship' );

		$actionFactory = $this->getActionFactory();
		$action = $actionFactory->newFromClassName( MarkMentorAsAwayAction::class );
		$this->assertInstanceOf( MarkMentorAsAwayAction::class, $action );
		$this->assertSame(
			$expected,
			TestingAccessWrapper::newFromObject( $action )->awayDurationInDays
		);
	}

	public static function provideAwayDuration() {
		return [
			'enabled, away after 30, removed after 90' => [ 61, [
				'GEMentorshipShouldBeAutoremoved' => true,
				'GEMentorshipAutoawayedAfterDays' => 30,
				'GEMentorshipAutoremovedAfterDays' => 90,
			] ],
			'disabled, away after 30, removed after 90' => [ 30, [
				'GEMentorshipShouldBeAutoremoved' => false,
				'GEMentorshipAutoawayedAfterDays' => 30,
				'GEMentorshipAutoremovedAfterDays' => 90,
			] ],
		];
	}
}
