<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\Tests\CommunityConfigurationTestHelpers;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \GrowthExperiments\Mentorship\Cleaner\MentorListCleaner
 */
class MentorListCleanerTest extends MediaWikiIntegrationTestCase {
	use CommunityConfigurationTestHelpers;

	protected function setUp(): void {
		parent::setUp();

		$this->setMainCache( CACHE_NONE );
	}

	private function getNewMentor(): UserIdentity {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();

		$mentor = $this->getMutableTestUser()->getUserIdentity();
		$this->assertStatusOK( $geServices->getMentorWriter()->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentor ),
			$mentor,
			''
		) );
		$this->assertTrue( $mentorProvider->isMentor( $mentor ) );
		return $mentor;
	}

	private function getMentorListRevisionCount(): int {
		$page = $this->getServiceContainer()->getPageStore()
			->getPageByName( NS_MEDIAWIKI, 'GrowthMentors.json' );
		$this->assertNotNull( $page );

		return (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'revision' )
			->where( [ 'rev_page' => $page->getId() ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	public function testRemoveWhenAwayAndRemovalThresholdsAreEqual() {
		// T444824
		$this->overrideProviderConfig( [
			'GEMentorshipShouldBeAutoawayed' => true,
			'GEMentorshipAutoawayedAfterDays' => 30,
			'GEMentorshipShouldBeAutoremoved' => true,
			'GEMentorshipAutoremovedAfterDays' => 30,
		], 'Mentorship' );
		$mentor = $this->getNewMentor();
		$revisionCount = $this->getMentorListRevisionCount();
		ConvertibleTimestamp::setFakeTime( time() + 31 * 86_400 );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->assertStatusGood(
			$geServices->getMentorListCleaner()->processMentors( RequestContext::getMain() )
		);

		$this->assertFalse( $geServices->getMentorProvider()->isMentor( $mentor ) );
		$this->assertSame(
			$revisionCount + 1,
			$this->getMentorListRevisionCount(),
			'Only the removal should edit the mentor list'
		);
	}

	public function testMarkAsAwayBeforeRemovalThreshold() {
		$this->overrideProviderConfig( [
			'GEMentorshipShouldBeAutoawayed' => true,
			'GEMentorshipAutoawayedAfterDays' => 30,
			'GEMentorshipShouldBeAutoremoved' => true,
			'GEMentorshipAutoremovedAfterDays' => 60,
		], 'Mentorship' );
		$mentor = $this->getNewMentor();
		$revisionCount = $this->getMentorListRevisionCount();
		ConvertibleTimestamp::setFakeTime( time() + 45 * 86_400 );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->assertStatusGood(
			$geServices->getMentorListCleaner()->processMentors( RequestContext::getMain() )
		);

		$this->assertTrue( $geServices->getMentorProvider()->isMentor( $mentor ) );
		$this->assertSame(
			MentorStatusManager::STATUS_AWAY,
			$geServices->getMentorStatusManager()->getMentorStatus( $mentor )
		);
		$this->assertSame( $revisionCount + 1, $this->getMentorListRevisionCount() );
	}
}
