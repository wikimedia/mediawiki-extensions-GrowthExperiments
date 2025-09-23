<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationLink;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationMetadata;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\PruningLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\StaticLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddLink\PruningLinkRecommendationProvider
 * @group medium
 * @group Database
 */
class PruningLinkRecommendationProviderTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param LinkRecommendation|StatusValue $recommendation
	 * @param string[] $existingPages List of pages to create as a fixture.
	 * @param string[] $excludedPages List of pages which should be forbidden as link targets.
	 * @param bool $pruneRedLinks
	 * @param LinkRecommendation|StatusValue $expectedValue
	 * @dataProvider provideGet
	 */
	public function testGet(
		$recommendation,
		array $existingPages,
		array $excludedPages,
		bool $pruneRedLinks,
		$expectedValue
	) {
		// The title the recommendation is about. Not used in the tested code so we can just hardcode it.
		$title = new TitleValue( NS_MAIN, 'Foo' );
		$titleKey = '0:Foo';

		// Create the pages which have been specified in the fixture, and convert the exclusion list
		// from titles to page IDs.
		$pageIds = [];
		foreach ( $existingPages as $page ) {
			$result = $this->editPage( $page, 'x' );
			$this->assertTrue( $result->isOK() );
			/** @var RevisionRecord $revision */
			$revision = $result->getValue()['revision-record'];
			$pageIds[$page] = $revision->getPageId();
		}
		$excludedLinkIds = [];
		foreach ( $excludedPages as $page ) {
			if ( $pageIds[$page] ?? null ) {
				$excludedLinkIds[] = $pageIds[$page];
			} else {
				$this->fail( "Can't exclude $page because it does not exist" );
			}
		}

		$taskType = new LinkRecommendationTaskType( 'link-recommendation', 'easy', [] );
		/** @var LinkRecommendationStore|MockObject $linkRecommendationStore */
		$linkRecommendationStore = $this->createNoOpMock( LinkRecommendationStore::class,
			[ 'getExcludedLinkIds' ] );
		if ( $recommendation instanceof LinkRecommendation ) {
			$linkRecommendationStore->expects( $this->exactly( 2 ) )->method( 'getExcludedLinkIds' )
				->with( $recommendation->getPageId() )
				->willReturn( $excludedLinkIds );
		} else {
			$linkRecommendationStore->expects( $this->never() )->method( 'getExcludedLinkIds' );
		}
		$provider = new PruningLinkRecommendationProvider(
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getLinkBatchFactory(),
			$linkRecommendationStore,
			new StaticLinkRecommendationProvider( [ $titleKey => $recommendation ] ),
			$pruneRedLinks
		);

		$actualValue = $provider->get( $title, $taskType );
		$actualDetailedStatus = $provider->getDetailed( $title, $taskType );
		if ( $expectedValue instanceof LinkRecommendation ) {
			if ( $actualValue instanceof StatusValue ) {
				$this->fail( 'Provider returned error: ' . Status::wrap( $actualValue )->getWikiText() );
			}
			$this->assertInstanceOf( LinkRecommendation::class, $actualValue );
			$this->assertSame( $expectedValue->toArray(), $actualValue->toArray() );
			$this->assertStatusGood( $actualDetailedStatus );
			$this->assertSame( $expectedValue->toArray(), $actualDetailedStatus->getValue()->toArray() );
		} else {
			$this->assertInstanceOf( StatusValue::class, $actualValue );
			$this->assertSame( $expectedValue->isOK(), $actualValue->isOK() );
			$this->assertStatusNotGood( $actualDetailedStatus );
		}
	}

	public static function provideGet() {
		// T289953 ugly hack to make page names unique
		$keys = array_keys( self::provideGetReal( 0 ) );
		foreach ( $keys as $i => $key ) {
			$all = self::provideGetReal( $i );
			yield $key => $all[$key];
		}
	}

	public static function provideGetReal( int $i ) {
		$links = [
			new LinkRecommendationLink( 'Foo', "Project:X$i", 2, 100, 0.75, 'pre', 'post', 0 ),
			new LinkRecommendationLink( 'Bar', "Project:Y$i", 1, 200, 0.8, 'pre2', 'post2', 1 ),
		];
		$title = new TitleValue( NS_MAIN, 'Foo' );
		$metadata = new LinkRecommendationMetadata( 'v1', 1, [], time() );
		$linkRecommendationFull = new LinkRecommendation( $title, 1, 1, $links, $metadata );
		$linkRecommendationHalfPruned = new LinkRecommendation( $title, 1, 1,
			array_slice( $links, 0, 1 ), $metadata );
		$linkRecommendationOtherHalfPruned = new LinkRecommendation( $title, 1, 1,
			array_slice( $links, 1 ), $metadata );
		$warning = StatusValue::newGood()->warning( 'foo' );
		$error = StatusValue::newFatal( 'foo' );

		return [
			// LinkRecommendation, str[] existingPages, str[] excludedPages, bool pruneRedLinks, expected
			'no pruning' => [
				$linkRecommendationFull,
				[ "Project:X$i", "Project:Y$i" ],
				[],
				true,
				$linkRecommendationFull,
			],
			'exclusion list' => [
				$linkRecommendationFull,
				[ "Project:X$i", "Project:Y$i" ],
				[ "Project:Y$i" ],
				true,
				$linkRecommendationHalfPruned,
			],
			'exclusion list #2' => [
				$linkRecommendationFull,
				[ "Project:X$i", "Project:Y$i" ],
				[ "Project:X$i" ],
				true,
				$linkRecommendationOtherHalfPruned,
			],
			'redlink, enabled' => [
				$linkRecommendationFull,
				[ "Project:X$i" ],
				[],
				true,
				$linkRecommendationHalfPruned,
			],
			'redlink, disabled' => [
				$linkRecommendationFull,
				[ "Project:X$i" ],
				[],
				false,
				$linkRecommendationFull,
			],
			'all pruned' => [
				$linkRecommendationFull,
				[ "Project:X$i" ],
				[ "Project:X$i" ],
				true,
				$warning,
			],
			'error' => [
				$error,
				[ "Project:X$i", "Project:Y$i" ],
				[],
				true,
				$error,
			],
		];
	}

}
