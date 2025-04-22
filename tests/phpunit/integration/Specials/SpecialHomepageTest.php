<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\Specials\SpecialHomepage;
use InvalidArgumentException;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use SpecialPageTestBase;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialHomepage
 */
class SpecialHomepageTest extends SpecialPageTestBase {

	use \MockHttpTrait;

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$this->setService( 'GrowthExperimentsMentorManager', new StaticMentorManager( [] ) );

		// Needed to avoid errors in DeferredUpdates from the SpecialHomepageLogger
		$mwHttpRequest = $this->createMock( \MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( new \StatusValue() );
		$this->installMockHttp( $mwHttpRequest );

		$growthExperimentsServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$communityConfigServices = CommunityConfigurationServices::wrap( $this->getServiceContainer() );
		return new SpecialHomepage(
			$growthExperimentsServices->getHomepageModuleRegistry(),
			$this->getServiceContainer()->getStatsFactory(),
			$growthExperimentsServices->getExperimentUserManager(),
			$growthExperimentsServices->getMentorManager(),
			$communityConfigServices->getMediaWikiConfigRouter(),
			$this->getServiceContainer()->getUserOptionsManager(),
			$this->getServiceContainer()->getTitleFactory()
		);
	}

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleDisabledPreference
	 */
	public function testHomepageDoesNotRenderWhenPreferenceIsDisabled() {
		$user = $this->getTestUser()->getUser();

		$this->expectException( \ErrorPageError::class );
		$this->expectExceptionMessage( 'To enable the newcomer homepage, visit your' );

		$this->executeSpecialPage( '', null, null, $user );
	}

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleDisabledPreference
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::execute
	 */
	public function testHomepageRendersWhenPreferenceIsEnabled() {
		$user = $this->enableHomepageForTesting();
		$response = $this->executeSpecialPage( '', null, null, $user );
		$this->assertStringContainsString( 'growthexperiments-homepage-container', $response[0] );
	}

	/**
	 * @dataProvider provideTestMissingParametersToNewcomerTaskSubpath
	 * @covers ::handleNewcomerTask
	 * @param array $params
	 * @param array $expectedMissingParams
	 */
	public function testMissingParametersToNewcomerTaskSubpath(
		array $params, array $expectedMissingParams
	) {
		// Make sure that the title ID is valid
		$titleId = $this->getExistingTestPage()->getId();
		$this->overrideConfigValue( 'GEDeveloperSetup', true );
		$user = $this->enableHomepageForTesting();
		$request = new FauxRequest( $params );
		$this->expectException( InvalidArgumentException::class );
		$message = sprintf(
			'Invalid parameters passed to Special:Homepage/newcomertask. Missing params: %s',
			implode( ',', $expectedMissingParams )
		);
		$this->expectExceptionMessage( $message );
		$this->executeSpecialPage( 'newcomertask/' . $titleId, $request, null, $user );
	}

	private function enableHomepageForTesting(): User {
		$this->overrideConfigValues( [
			'GEHelpPanelHelpDeskTitle' => 'HelpDeskTitle',
		] );
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_PT_LINK, 1 );
		$user->saveSettings();
		return $user;
	}

	public static function provideTestMissingParametersToNewcomerTaskSubpath(): array {
		return [
			'missing click id' => [
				[
					'genewcomertasktoken' => 2,
					'getasktype' => 'links'
				],
				[
					'geclickid'
				]
			],
			'null click id' => [
				[
					'geclickid' => null,
					'genewcomertasktoken' => 2,
					'getasktype' => 'copyedit'
				],
				[
					'geclickid'
				]
			],
			'missing token' => [
				[
					'geclickid' => 1,
					'getasktype' => 'copyedit'
				],
				[
					'genewcomertasktoken'
				]
			],
			'null token' => [
				[
					'genewcomertasktoken' => null,
					'geclickid' => 1,
					'getasktype' => 'copyedit'
				],
				[
					'genewcomertasktoken'
				]
			],
			'missing task type' => [
				[
					'genewcomertasktoken' => 2,
					'geclickid' => 1,
				],
				[
					'getasktype'
				]
			],
			'null task type' => [
				[
					'genewcomertasktoken' => 2,
					'geclickid' => 1,
					'getasktype' => null
				],
				[
					'getasktype'
				]
			],
		];
	}

}
