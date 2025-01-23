<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Provider\AbstractStructuredMentorWriter;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\Provider\LegacyStructuredMentorWriter
 * @covers \GrowthExperiments\Mentorship\Provider\AbstractStructuredMentorWriter
 * @group Database
 */
class StructuredMentorWriterIntegrationTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param Title $title
	 * @return int
	 */
	private function getLatestEditId( Title $title ): int {
		$this->getServiceContainer()
			->getRevisionLookup()
			->getRevisionByTitle( $title );
		return $this->getServiceContainer()
			->getRevisionLookup()
			->getRevisionByTitle( $title )
			->getId();
	}

	private function assertEditTagged( array $tags, int $revId ) {
		$this->assertSameSize(
			$tags,
			array_intersect(
				$tags,
				$this->getServiceContainer()->getChangeTagsStore()->getTags(
					$this->getServiceContainer()->getConnectionProvider()->getReplicaDatabase(),
					null,
					$revId
				)
			)
		);
	}

	public function testEditTagged() {
		$mentorListTitle = $this->getNonexistingTestPage( 'MediaWiki:GrowthMentors.json' )->getTitle();
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorUser = $this->getTestUser()->getUser();
		$mentor = $geServices->getMentorProvider()
			->newMentorFromUserIdentity( $mentorUser );
		$writer = $geServices->getMentorWriter();

		$this->assertStatusGood( $writer->addMentor( $mentor, $mentorUser, 'Add mentor' ) );
		$this->assertTrue( $mentorListTitle->exists() );
		$revId = $this->getLatestEditId( $mentorListTitle );
		$this->assertEditTagged( [ AbstractStructuredMentorWriter::CHANGE_TAG ], $revId );

		$mentor->setWeight( IMentorWeights::WEIGHT_NONE );
		$oldRevId = $revId;
		$this->assertStatusGood( $writer->changeMentor( $mentor, $mentorUser, 'Change mentor' ) );
		$revId = $this->getLatestEditId( $mentorListTitle );
		$this->assertNotEquals( $oldRevId, $revId );
		$this->assertEditTagged( [ AbstractStructuredMentorWriter::CHANGE_TAG ], $revId );

		$oldRevId = $revId;
		$this->assertStatusGood( $writer->removeMentor( $mentor, $mentorUser, 'Remove mentor' ) );
		$revId = $this->getLatestEditId( $mentorListTitle );
		$this->assertNotEquals( $oldRevId, $revId );
		$this->assertEditTagged( [ AbstractStructuredMentorWriter::CHANGE_TAG ], $revId );
	}
}
