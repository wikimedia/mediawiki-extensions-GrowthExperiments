<?php

namespace GrowthExperiments\Tests;

use GlobalVarConfig;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\Specials\SpecialHomepage;
use MediaWiki\MediaWikiServices;
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
		$mwHttpRequest = $this->getMockBuilder( \MWHttpRequest::class )
			->disableOriginalConstructor()
			->getMock();
		$mwHttpRequest->method( 'execute' )
			->willReturn( new \StatusValue() );
		$this->installMockHttp( $mwHttpRequest );

		$growthExperimentsServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		return new SpecialHomepage(
			$growthExperimentsServices->getHomepageModuleRegistry(),
			$growthExperimentsServices->getNewcomerTaskTrackerFactory(),
			MediaWikiServices::getInstance()->getStatsdDataFactory(),
			MediaWikiServices::getInstance()->getPerDbNameStatsdDataFactory(),
			$growthExperimentsServices->getExperimentUserManager(),
			// This would normally be wiki-powered config, but
			// there is no need to test this
			GlobalVarConfig::newInstance(),
			MediaWikiServices::getInstance()->getUserOptionsManager()
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
	 * @throws \MWException
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleDisabledPreference
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::execute
	 */
	public function testHomepageRendersWhenPreferenceIsEnabled() {
		$this->setMwGlobals( [
			'wgGEHomepageEnabled' => true,
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTitle',
		] );
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$userOptionsManager->setOption( $user, HomepageHooks::HOMEPAGE_PREF_PT_LINK, 1 );
		$user->saveSettings();
		$response = $this->executeSpecialPage( '', null, null, $user );
		$this->assertStringContainsString( 'growthexperiments-homepage-container', $response[0] );
	}

}
