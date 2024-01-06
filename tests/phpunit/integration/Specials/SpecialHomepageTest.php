<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\EditInfoService;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\Specials\SpecialHomepage;
use InvalidArgumentException;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\MediaWikiServices;
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
		$this->setService( 'GrowthExperimentsEditInfoService', new class extends EditInfoService {
			public function getEditsPerDay() {
				return 0;
			}
		} );
		$this->setService( 'GrowthExperimentsMentorManager', new StaticMentorManager( [] ) );

		// Needed to avoid errors in DeferredUpdates from the SpecialHomepageLogger
		$mwHttpRequest = $this->createMock( \MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( new \StatusValue() );
		$this->installMockHttp( $mwHttpRequest );

		$growthExperimentsServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		return new SpecialHomepage(
			$growthExperimentsServices->getHomepageModuleRegistry(),
			MediaWikiServices::getInstance()->getStatsdDataFactory(),
			MediaWikiServices::getInstance()->getPerDbNameStatsdDataFactory(),
			$growthExperimentsServices->getExperimentUserManager(),
			$growthExperimentsServices->getMentorManager(),
			// This would normally be wiki-powered config, but
			// there is no need to test this
			GlobalVarConfig::newInstance(),
			MediaWikiServices::getInstance()->getUserOptionsManager(),
			MediaWikiServices::getInstance()->getTitleFactory()
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
		$this->setMwGlobals( [ 'wgGEDeveloperSetup' => true ] );
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
		$this->setMwGlobals( [
			'wgGEHomepageEnabled' => true,
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTitle',
		] );
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_PT_LINK, 1 );
		$user->saveSettings();
		return $user;
	}

	/**
	 * @return array
	 */
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
