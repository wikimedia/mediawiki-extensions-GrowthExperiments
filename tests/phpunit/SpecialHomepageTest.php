<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Specials\SpecialHomepage;
use SpecialPageTestBase;
use User;

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

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleTutorialVisit
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::execute
	 */
	public function testHomepageRedirectsForTutorialVisit() {
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$this->setupForTutorialVisitTests( $user );
		list( $responseBody, $webResponse ) = $this->executeSpecialPage(
			'Main_Page',
			new \FauxRequest( [], true ),
			null,
			$user );
		$this->assertEquals(
			'',
			$responseBody,
			'Empty body since we are redirecting to tutorial title.'
		);
		$this->assertContains(
			'Main_Page',
			$webResponse->getHeader( 'location' ),
			'Location contains faux Tutorial title name'
		);
	}

	/**
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::handleTutorialVisit
	 * @covers \GrowthExperiments\Specials\SpecialHomepage::execute
	 */
	public function testHompageNoTutorialTitle() {
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$this->setupForTutorialVisitTests( $user );
		$this->setMwGlobals( [ 'wgGEHomepageTutorialTitle' => 'Bogus_Title' ] );
		list( $responseBody, $webResponse ) = $this->executeSpecialPage(
			'Main_Page',
			new \FauxRequest( [], true ),
			null,
			$user );
		$this->assertContains(
			'growthexperiments-homepage-container',
			$responseBody,
			'Homepage content found'
		);
	}

	private function setupForTutorialVisitTests( User $user ) {
		$this->setMwGlobals( [
			'wgGEHomepageEnabled' => true,
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTitle',
			'wgGEHomepageTutorialTitle' => 'Main_Page',
		] );
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_ENABLE, 1 );
		$user->setOption( HomepageHooks::HOMEPAGE_PREF_PT_LINK, 1 );
		$user->saveSettings();
	}
}
