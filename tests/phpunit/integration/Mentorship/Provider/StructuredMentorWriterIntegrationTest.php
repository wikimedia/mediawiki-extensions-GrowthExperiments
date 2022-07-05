<?php

namespace GrowthExperiments\Tests;

use ChangeTags;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use MediaWikiIntegrationTestCase;
use Title;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\Provider\StructuredMentorWriter
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
		$this->assertCount(
			count( $tags ),
			array_intersect(
				$tags,
				ChangeTags::getTags(
					$this->getServiceContainer()->getDBLoadBalancer()
						->getConnection( DB_REPLICA ),
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
		$mentorListTitle = $this->getServiceContainer()->getTitleFactory()
			->newFromText( 'MediaWiki:GrowthMentors.json' );
		$this->setMwGlobals( [
			'wgGEMentorProvider' => MentorProvider::PROVIDER_STRUCTURED,
			'wgGEStructuredMentorList' => $mentorListTitle->getPrefixedText()
		] );
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorUser = $this->getTestUser()->getUser();
		$mentor = $geServices->getMentorProvider()
			->newMentorFromUserIdentity( $mentorUser );
		$writer = $geServices->getMentorWriter();

		$this->assertFalse( $mentorListTitle->exists() );
		$writer->addMentor( $mentor, $mentorUser, 'Add mentor' );
		$this->assertTrue( $mentorListTitle->exists() );
		$revId = $this->getLatestEditId( $mentorListTitle );
		$this->assertEditTagged( [ StructuredMentorWriter::CHANGE_TAG ], $revId );

		$mentor->setAutoAssigned( false );
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
