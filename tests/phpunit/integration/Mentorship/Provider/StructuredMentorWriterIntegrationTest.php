<?php

namespace GrowthExperiments\Tests;

use ChangeTags;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\Provider\StructuredMentorWriter
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

	/**
	 * @param array $tags
	 * @param int $revId
	 */
	private function assertEditTagged( array $tags, int $revId ) {
		$this->assertSameSize(
			$tags,
			array_intersect(
				$tags,
				ChangeTags::getTags(
					$this->getServiceContainer()->getConnectionProvider()->getReplicaDatabase(),
					null,
					$revId
				)
			)
		);
	}

	/**
	 * @covers ::addMentor
	 * @covers ::changeMentor
	 * @covers ::removeMentor
	 * @covers ::saveMentorData
	 */
	public function testEditTagged() {
		$mentorListTitle = $this->getNonexistingTestPage( 'MediaWiki:GrowthMentors.json' )->getTitle();
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorUser = $this->getTestUser()->getUser();
		$mentor = $geServices->getMentorProvider()
			->newMentorFromUserIdentity( $mentorUser );
		$writer = $geServices->getMentorWriter();

		$writer->addMentor( $mentor, $mentorUser, 'Add mentor' );
		$this->assertTrue( $mentorListTitle->exists() );
		$revId = $this->getLatestEditId( $mentorListTitle );
		$this->assertEditTagged( [ StructuredMentorWriter::CHANGE_TAG ], $revId );

		$mentor->setWeight( IMentorWeights::WEIGHT_NONE );
		$oldRevId = $revId;
		$writer->changeMentor( $mentor, $mentorUser, 'Change mentor' );
		$revId = $this->getLatestEditId( $mentorListTitle );
		$this->assertNotEquals( $oldRevId, $revId );
		$this->assertEditTagged( [ StructuredMentorWriter::CHANGE_TAG ], $revId );

		$oldRevId = $revId;
		$writer->removeMentor( $mentor, $mentorUser, 'Remove mentor' );
		$revId = $this->getLatestEditId( $mentorListTitle );
		$this->assertNotEquals( $oldRevId, $revId );
		$this->assertEditTagged( [ StructuredMentorWriter::CHANGE_TAG ], $revId );
	}
}
