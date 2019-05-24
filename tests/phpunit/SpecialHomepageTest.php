<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Specials\SpecialHomepage;
use SpecialPageTestBase;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialHomepage
 */
class SpecialHomepageTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new SpecialHomepage();
	}

	/**
	 * @expectedException \ErrorPageError
	 * @expectedExceptionMessage To enable the newcomer homepage, visit your
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleDisabledPreference
	 */
	public function testHomepageDoesNotRenderWhenPreferenceIsDisabled() {
		$user = $this->getTestUser()->getUser();
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
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_PT_LINK, 1 );
		$user->saveSettings();
		$response = $this->executeSpecialPage( '', null, null, $user );
		$this->assertContains( 'growthexperiments-homepage-container', $response[0] );
	}
}
