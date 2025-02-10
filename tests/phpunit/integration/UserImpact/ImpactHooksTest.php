<?php

namespace GrowthExperiments\Tests\Integration;

use DateInterval;
use DatePeriod;
use DateTime;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\ExpensiveUserImpact;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \GrowthExperiments\ImpactHooks
 */
class ImpactHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * Test full integration from editing a page to retrieving stored impact
	 * data, without calling code on ImpactHooks directly.
	 * Note that this test uses a mock for GrowthExperimentsUserImpactLookup_Computed
	 * to avoid the dependency on the PageViewInfo extension. So it only covers
	 * information flow, not computation of impact data.
	 */
	public function testUpdatePropagation() {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn( [
			'copyedit' => new TemplateBasedTaskType( 'copyedit', 'easy', [], [] )
		] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		$mockImpact = $this->makeMockImpact( $user );
		$computedLookup = $this->createNoOpMock(
			UserImpactLookup::class,
			[ 'getExpensiveUserImpact' ]
		);
		$computedLookup->method( 'getExpensiveUserImpact' )
			->willReturn( $mockImpact );

		$this->setService( 'GrowthExperimentsUserImpactLookup_Computed', $computedLookup );

		$options = $this->getServiceContainer()->getUserOptionsManager();
		$options->setOption(
			$user,
			HomepageHooks::HOMEPAGE_PREF_ENABLE,
			true
		);
		$options->saveOptions( $user );

		// Do one edit to trigger the hook. The stored impact data should be
		// updated based on the mock impact returned by $computedLookup.
		$this->editPage( 'Foo', 'test edit', '', NS_MAIN, $user );

		$runner = $this->getServiceContainer()->getJobRunner();
		$runner->run( [ 'type' => 'refreshUserImpactJob' ] );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$userImpactStore = $geServices->getUserImpactStore();
		$userImpact = $userImpactStore->getExpensiveUserImpact( $user );
		$this->assertNotNull( $userImpact );

		$this->assertSame(
			$mockImpact->getEditCountByNamespace(),
			$userImpact->getEditCountByNamespace()
		);
		$this->assertSame(
			$mockImpact->getNewcomerTaskEditCount(),
			$userImpact->getNewcomerTaskEditCount()
		);
		$this->assertSame(
			$mockImpact->getLastEditTimestamp(),
			$userImpact->getLastEditTimestamp()
		);
	}

	private function makeMockImpact( UserIdentity $user ): ExpensiveUserImpact {
		return new ExpensiveUserImpact(
			$user,
			2,
			[
				NS_MAIN => 10,
				NS_USER => 3,
			],
			[
				'01-10-2022' => 10,
				'02-10-2022' => 20,
				'03-10-2022' => 30,
			],
			[
				'expand' => 10,
				'links' => 10,
				'references' => 20
			],
			0,
			3,
			(int)( ( new ConvertibleTimestamp( '2022-03-10T00:00:00Z' ) )->getTimestamp() ),
			[
				'2022-10-01' => 100,
				'2022-10-02' => 200,
				'2022-10-03' => 300,
			],
			[
				'Foo' => [
					'firstEditDate' => '2022-10-01',
					'newestEdit' => '20221003223344',
					'imageUrl' => null,
					'views' => [
						'2022-10-01' => 100,
						'2022-10-02' => 200,
						'2022-10-03' => 300,
					]
				]
			],
			new EditingStreak(
				new DatePeriod(
					new DateTime( '01-10-2022' ),
					new DateInterval( 'P3D' ),
					new DateTime( '03-10-2022' )
				)
			),
			50
		);
	}

}
