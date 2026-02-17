<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter
 * @group Database
 */
class CommunityStructuredMentorWriterTest extends MediaWikiIntegrationTestCase {

	public function testAddRemoveMentor() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$provider = $geServices->getMentorProvider();
		$writer = $geServices->getMentorWriter();

		$mentor = $provider->newMentorFromUserIdentity( $this->getTestUser()->getUserIdentity() );
		$performer = $this->getTestSysop()->getUserIdentity();

		$status = $writer->addMentor( $mentor, $performer, 'test' );
		$this->assertStatusOK( $status );

		$status = $writer->removeMentor( $mentor, $performer, 'test' );
		$this->assertStatusOK( $status );
	}
}
