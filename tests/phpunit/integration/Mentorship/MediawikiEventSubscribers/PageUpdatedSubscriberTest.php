<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\Mentorship\MediaWikiEventIngress\PageRevisionUpdatedIngress
 */
class PageUpdatedSubscriberTest extends MediaWikiIntegrationTestCase {

	public function testMarkMenteeAsActive(): void {
		$this->setMainCache( CACHE_NONE );
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();

		$mentor = $this->getTestSysop()->getUserIdentity();
		$menteeTestUser = $this->getMutableTestUser();
		$mentorStore->setMentorForUser( $menteeTestUser->getUserIdentity(), $mentor, MentorStore::ROLE_PRIMARY );
		$mentorStore->markMenteeAsInactive( $menteeTestUser->getUserIdentity() );
		$this->assertFalse( $mentorStore->isMenteeActive( $menteeTestUser->getUserIdentity() ) );

		$this->editPage(
			Title::newFromText( 'Sandbox' ),
			'test',
			'',
			NS_MAIN,
			$menteeTestUser->getAuthority()
		);

		$this->assertTrue( $mentorStore->isMenteeActive( $menteeTestUser->getUserIdentity() ) );
	}
}
