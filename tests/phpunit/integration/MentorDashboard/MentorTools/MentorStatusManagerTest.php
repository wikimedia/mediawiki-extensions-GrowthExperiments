<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager
 */
class MentorStatusManagerTest extends MediaWikiIntegrationTestCase {

	private function getMentorStatusManager(): MentorStatusManager {
		return GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStatusManager();
	}

	/**
	 * Regression test for T426465.
	 *
	 * A mentor who is not blocked but accesses the wiki through a hard-blocked IP must not be
	 * reported as away. The IP block only affects requests made by that mentor themselves, so
	 * their mentees are not reassigned and the "away because of a block" status is misleading.
	 *
	 * getAwayReasonUncached() must therefore check the mentor's block without consulting the
	 * current request's IP. This test sets the mentor up as the global session user behind a
	 * hard-blocked IP, which is exactly the situation where User::getBlock() would otherwise
	 * pull in the request's IP block.
	 */
	public function testGetAwayReasonIgnoresRequestIpBlock(): void {
		$blockedIp = '10.20.30.40';
		$mentor = $this->getMutableTestUser()->getUser();

		// Make the mentor the global session user behind the blocked IP, so that
		// User::getBlock() would pass the request (and therefore the IP block) to BlockManager.
		$request = new FauxRequest();
		$request->setIP( $blockedIp );
		RequestContext::getMain()->setUser( $mentor );
		RequestContext::getMain()->setRequest( $request );
		TestingAccessWrapper::newFromObject( $mentor )->mRequest = $request;
		$request->getSession()->setUser( $mentor );

		// Hard sitewide block on the IP only; the mentor's account itself is not blocked.
		$this->getServiceContainer()->getDatabaseBlockStore()->insertBlockWithParams( [
			'address' => $blockedIp,
			'by' => $this->getTestSysop()->getUser(),
		] );

		$this->assertNull(
			$this->getMentorStatusManager()->getAwayReason( $mentor ),
			'A mentor behind a hard-blocked IP must not be considered away because of a block'
		);
	}
}
