<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationLink;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationMetadata;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\NullLinkRecommendation;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore
 * @group medium
 * @group Database
 */
class LinkRecommendationStoreTest extends MediaWikiIntegrationTestCase {

	public function testGrowthexperimentsLinkRecommendationsCrud(): void {
		$store = new LinkRecommendationStore(
			$this->getServiceContainer()->getDBLoadBalancer(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getLinkBatchFactory(),
			$this->getServiceContainer()->getPageStore(),
			new NullLogger(),
		);

		$pageIds = [];
		$revisionIds = [];
		foreach ( [ 'T1', 'T2', 'T3', 'T4', 'T5', 'T6' ] as $titleText ) {
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
			$store->insertExistingLinkRecommendation( new LinkRecommendation(
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
		$store->insertNoLinkRecommendationFound( $pageIds['T5'], $revisionIds['T5']['r3'] );
		$store->insertNoLinkRecommendationFound( $pageIds['T6'], $revisionIds['T6']['r3'] );

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
		$this->assertSame(
			LinkRecommendationStore::RECOMMENDATION_AVAILABLE,
			$store->getRecommendationStateByRevision( $revisionIds['T2']['r3'] )
		);

		// get by mismatching revision ID
		$this->assertNull( $store->getByRevId( $revisionIds['T1']['r3'] ) );

		// get by page ID
		$linkRecommendation = $store->getByPageId( $pageIds['T1'] );
		$this->assertInstanceOf( LinkRecommendation::class, $linkRecommendation );
		$this->assertSame( $revisionIds['T1']['r2'], $linkRecommendation->getRevisionId() );
		$this->assertSame( $pageIds['T1'], $linkRecommendation->getPageId() );
		$this->assertSame( 'T1', $linkRecommendation->getTitle()->getDBkey() );

		// page with unknown recommendations
		$this->assertNull( $store->getByRevId( $revisionIds['T3']['r3'] ) );
		$this->assertNull( $store->getByPageId( $pageIds['T3'] ) );
		$this->assertSame(
			LinkRecommendationStore::RECOMMENDATION_UNKNOWN,
			$store->getRecommendationStateByRevision( $revisionIds['T3']['r3'] )
		);

		// page with known no recommendations
		$this->assertNull( $store->getByRevId( $revisionIds['T5']['r3'] ) );
		$this->assertNull( $store->getByPageId( $pageIds['T5'] ) );
		$this->assertSame(
			LinkRecommendationStore::RECOMMENDATION_NOT_AVAILABLE,
			$store->getRecommendationStateByRevision( $revisionIds['T5']['r3'] )
		);

		// getting by page returns latest
		$linkRecommendation = $store->getByPageId( $pageIds['T4'] );
		$this->assertSame( $revisionIds['T4']['r3'], $linkRecommendation->getRevisionId() );

		// $allowAnyRevision flag
		$this->assertNull( $store->getByLinkTarget( new TitleValue( 0, 'T1' ) ) );
		$linkRecommendation = $store->getByLinkTarget( new TitleValue( 0, 'T1' ), 0, true );
		$this->assertInstanceOf( LinkRecommendation::class, $linkRecommendation );
		$this->assertSame( $revisionIds['T1']['r2'], $linkRecommendation->getRevisionId() );

		$fromPageId = 0;
		$allExistingRecommendations = $store->getAllExistingRecommendations( 10, $fromPageId );
		$this->assertCount( 4, $allExistingRecommendations );

		$allPageIds = $store->listPageIds( 10 );
		$this->assertCount( 3, $allPageIds );

		$fromPageId = 0;
		$allRecommendationEntries = $store->getAllRecommendationEntries( 10, $fromPageId );
		$this->assertCount( 6, $allRecommendationEntries );
		$nullRecommendations = array_filter(
			$allRecommendationEntries,
			static fn ( $entry ) => $entry instanceof NullLinkRecommendation
		);
		$this->assertCount( 2, $nullRecommendations );

		// deleteByLinkTarget
		$this->assertFalse( $store->deleteByLinkTarget( new TitleValue( 0, 'T3' ) ) );
		$this->assertTrue( $store->deleteByLinkTarget( new TitleValue( 0, 'T2' ) ) );
		$this->assertNull( $store->getByPageId( $pageIds['T2'] ) );
		$this->assertNotNull( $store->getByPageId( $pageIds['T1'] ) );
		$this->assertNotNull( $store->getByPageId( $pageIds['T4'] ) );

		$this->assertSame( 4, $store->deleteByPageIds( [ $pageIds['T1'], $pageIds['T4'], $pageIds['T5'] ] ) );
		$this->assertNull( $store->getByPageId( $pageIds['T1'] ) );
		$this->assertNull( $store->getByPageId( $pageIds['T4'] ) );
		$this->assertSame(
			LinkRecommendationStore::RECOMMENDATION_UNKNOWN,
			$store->getRecommendationStateByRevision( $revisionIds['T5']['r3'] )
		);
	}
}
