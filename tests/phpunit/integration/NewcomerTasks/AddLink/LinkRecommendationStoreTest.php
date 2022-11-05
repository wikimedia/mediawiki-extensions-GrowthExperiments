<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationLink;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationMetadata;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiIntegrationTestCase;
use TitleValue;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore
 * @group medium
 * @group Database
 */
class LinkRecommendationStoreTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::insert
	 * @covers ::getByRevId
	 * @covers ::getByPageId
	 * @covers ::getByLinkTarget
	 * @covers ::deleteByPageIds
	 * @covers ::deleteByLinkTarget
	 */
	public function testGrowthexperimentsLinkRecommendationsCrud() {
		$store = new LinkRecommendationStore(
			$this->db,
			$this->db,
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getLinkBatchFactory(),
			$this->getServiceContainer()->getPageStore()
		);

		$pageIds = [];
		$revisionIds = [];
		foreach ( [ 'T1', 'T2', 'T3', 'T4' ] as $titleText ) {
			foreach ( [ 'r1', 'r2', 'r3' ] as $revisionText ) {
				$status = $this->editPage( $titleText, $revisionText );
				if ( !$status->isOK() ) {
					$this->fail( $status->getWikiText() );
				}
				/** @var RevisionRecord $revisionRecord */
				$revisionRecord = $status->getValue()['revision-record'];
				$pageIds[$titleText] = $revisionRecord->getPageId();
				$revisionIds[$titleText][$revisionText] = $revisionRecord->getId();
			}
		}
		$timestamp = 1577865600;

		// fixture
		$insert = static function (
			string $titleText, string $revisionText
		) use ( $store, $pageIds, $revisionIds, $timestamp ) {
			$links = [];
			foreach ( range( 1, 3 ) as $i ) {
				$links[] = new LinkRecommendationLink(
					"{$titleText}_{$revisionText}_{$i}_text",
					"{$titleText}_{$revisionText}_{$i}_target",
					2 * $i,
					10 * $i,
					1 / $i,
					"{$titleText}_{$revisionText}_{$i}_before",
					"{$titleText}_{$revisionText}_{$i}_after",
					$i
				);
			}
			$store->insert( new LinkRecommendation(
				new TitleValue( 0, $titleText ),
				$pageIds[$titleText],
				$revisionIds[$titleText][$revisionText],
				$links,
				new LinkRecommendationMetadata( 'v1', 1, [], $timestamp )
			) );
		};
		$insert( 'T1', 'r2' );
		$insert( 'T2', 'r3' );
		$insert( 'T4', 'r2' );
		$insert( 'T4', 'r3' );

		// get by revision ID
		$linkRecommendation = $store->getByRevId( $revisionIds['T1']['r2'] );
		$this->assertInstanceOf( LinkRecommendation::class, $linkRecommendation );
		$this->assertSame( $revisionIds['T1']['r2'], $linkRecommendation->getRevisionId() );
		$this->assertSame( $pageIds['T1'], $linkRecommendation->getPageId() );
		$this->assertSame( 'T1', $linkRecommendation->getTitle()->getDBkey() );
		$this->assertCount( 3, $linkRecommendation->getLinks() );
		$this->assertInstanceOf( LinkRecommendationLink::class, $linkRecommendation->getLinks()[0] );
		$this->assertSame( 'T1_r2_1_text', $linkRecommendation->getLinks()[0]->getText() );
		$this->assertInstanceOf( LinkRecommendationMetadata::class, $linkRecommendation->getMetadata() );
		$this->assertSame( 'v1', $linkRecommendation->getMetadata()->getApplicationVersion() );
		$this->assertSame( $timestamp, $linkRecommendation->getMetadata()->getTaskTimestamp() );

		// get by mismatching revision ID
		$this->assertNull( $store->getByRevId( $revisionIds['T1']['r3'] ) );

		// get by page ID
		$linkRecommendation = $store->getByPageId( $pageIds['T1'] );
		$this->assertInstanceOf( LinkRecommendation::class, $linkRecommendation );
		$this->assertSame( $revisionIds['T1']['r2'], $linkRecommendation->getRevisionId() );
		$this->assertSame( $pageIds['T1'], $linkRecommendation->getPageId() );
		$this->assertSame( 'T1', $linkRecommendation->getTitle()->getDBkey() );

		// page with no recommendations
		$this->assertNull( $store->getByRevId( $revisionIds['T3']['r3'] ) );
		$this->assertNull( $store->getByPageId( $pageIds['T3'] ) );

		// getting by page returns latest
		$linkRecommendation = $store->getByPageId( $pageIds['T4'] );
		$this->assertSame( $revisionIds['T4']['r3'], $linkRecommendation->getRevisionId() );

		// $allowAnyRevision flag
		$this->assertNull( $store->getByLinkTarget( new TitleValue( 0, 'T1' ) ) );
		$linkRecommendation = $store->getByLinkTarget( new TitleValue( 0, 'T1' ), 0, true );
		$this->assertInstanceOf( LinkRecommendation::class, $linkRecommendation );
		$this->assertSame( $revisionIds['T1']['r2'], $linkRecommendation->getRevisionId() );

		// deleteByLinkTarget
		$this->assertFalse( $store->deleteByLinkTarget( new TitleValue( 0, 'T3' ) ) );
		$this->assertTrue( $store->deleteByLinkTarget( new TitleValue( 0, 'T2' ) ) );
		$this->assertNull( $store->getByPageId( $pageIds['T2'] ) );
		$this->assertNotNull( $store->getByPageId( $pageIds['T1'] ) );
		$this->assertNotNull( $store->getByPageId( $pageIds['T4'] ) );

		$this->assertSame( 3, $store->deleteByPageIds( [ $pageIds['T1'], $pageIds['T4'] ] ) );
		$this->assertNull( $store->getByPageId( $pageIds['T1'] ) );
		$this->assertNull( $store->getByPageId( $pageIds['T4'] ) );
	}

}
