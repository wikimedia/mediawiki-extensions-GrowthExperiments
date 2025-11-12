<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\MentorDashboard\Modules\MenteeOverview;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\Modules\MenteeOverview
 */
class MenteeOverviewTest extends MediaWikiIntegrationTestCase {
	public function renderMenteeOverview( string $now, string $update ): string {
		ConvertibleTimestamp::setFakeTime( $now );

		$mentorUser = $this->getMutableTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $mentorUser, MenteeOverviewDataUpdater::LAST_UPDATE_PREFERENCE, $update );
		$userOptionsManager->saveOptions( $mentorUser );

		$ctx = new DerivativeContext( RequestContext::getMain() );
		$ctx->setUser( $mentorUser );
		$ctx->setLanguage( 'qqx' );
		$userOptionsLookup = $this->getServiceContainer()->getUserOptionsLookup();
		$menteeOverview = new MenteeOverview( $ctx, $userOptionsLookup );
		return $menteeOverview->render( IDashboardModule::RENDER_DESKTOP );
	}

	/**
	 * @covers \GrowthExperiments\MentorDashboard\Modules\MenteeOverview
	 */
	public function testMentorSeesAccurateCalculationForLastUpdateTimeOfMenteeData() {
		$now = '20250921100000';
		$update = '20250921080000';

		$result = $this->renderMenteeOverview( $now, $update );

		$this->assertStringContainsString(
			'(growthexperiments-mentor-dashboard-mentee-overview-info-updated: (duration-hours: 2))', $result );
	}

	/**
	 * @covers \GrowthExperiments\MentorDashboard\Modules\MenteeOverview
	 */
	public function testMentorDoesNotSeeLastUpdateSubheadingWhenUserOptionForLastUpdateIsNonnumeric() {
		$now = '20251121100000';
		$update = 'gibberish';

		$result = $this->renderMenteeOverview( $now, $update );

		$this->assertStringNotContainsString( 'growthexperiments-mentor-dashboard-mentee-overview-info-updated',
			$result );
	}

	/**
	 * @covers \GrowthExperiments\MentorDashboard\Modules\MenteeOverview
	 */
	public function testMentorDoesNotSeeLastUpdateSubheadingWhenUserOptionForLastUpdateIsInTheFuture() {
		$now = '20251121100000';
		$update = '20251221100000';

		$result = $this->renderMenteeOverview( $now, $update );

		$this->assertStringNotContainsString( 'growthexperiments-mentor-dashboard-mentee-overview-info-updated',
			$result );
	}
}
