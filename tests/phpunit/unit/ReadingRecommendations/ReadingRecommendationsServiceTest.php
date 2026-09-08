<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ReadingRecommendations\FeaturedArticlePool;
use GrowthExperiments\ReadingRecommendations\InterestArticlesLookup;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendation;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsCachePolicy;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearcher;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearchResult;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsService;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\ReadingRecommendations\ReadingRecommendationsService
 */
class ReadingRecommendationsServiceTest extends MediaWikiUnitTestCase {

	private UserIdentityValue $user;

	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$this->user = new UserIdentityValue( 7, 'Alice' );
	}

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	public function testNoInterestsFillsAllSlotsFromThePool() {
		$service = $this->newService( [], [], $this->titles( 'F1', 'F2', 'F3', 'F4', 'F5', 'F6' ) );
		$recommendations = $service->getRecommendations( $this->user );

		$this->assertCount( 4, $recommendations );
		$dbkeys = [];
		foreach ( $recommendations as $recommendation ) {
			$this->assertTrue( $recommendation->isGeneral() );
			$dbkeys[] = $recommendation->getTitle()->getDBkey();
		}
		$this->assertSame( [ 'F1', 'F2', 'F3', 'F4' ], $dbkeys );
	}

	public function testGeneralSlotsAreTheSameForEveryUser() {
		$pool = $this->titles( 'F1', 'F2', 'F3', 'F4', 'F5', 'F6' );
		$forAlice = $this->newService( [], [], $pool )->getRecommendations( $this->user );
		$forBob = $this->newService( [], [], $pool )
			->getRecommendations( new UserIdentityValue( 8, 'Bob' ) );

		$dbkeys = static fn ( array $recommendations ) => array_map(
			static fn ( $recommendation ) => $recommendation->getTitle()->getDBkey(),
			$recommendations
		);
		$this->assertSame( $dbkeys( $forAlice ), $dbkeys( $forBob ) );
	}

	public function testSameInterestGivesTheSameRecommendationForEveryUser() {
		$interests = $this->titles( 'New_York_City' );
		$related = [ 'New_York_City' => $this->titles( 'Brooklyn', 'Manhattan', 'Queens' ) ];

		$forAlice = $this->newService( $interests, $related, [] )
			->getRecommendations( $this->user );
		$forBob = $this->newService( $interests, $related, [] )
			->getRecommendations( new UserIdentityValue( 8, 'Bob' ) );

		$this->assertSame(
			$forAlice[0]->getTitle()->getDBkey(),
			$forBob[0]->getTitle()->getDBkey()
		);
	}

	public function testSharedInterestGivesTheSamePickAcrossDifferentInterestSets() {
		$lookup = $this->createMock( InterestArticlesLookup::class );
		$lookup->method( 'getInterests' )->willReturnOnConsecutiveCalls(
			$this->titles( 'Music' ),
			$this->titles( 'Music', 'Painting' )
		);
		$related = [
			'Music' => $this->titles( 'Jazz', 'Blues', 'Opera' ),
			'Painting' => $this->titles( 'Watercolor' ),
		];
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		// Music is searched once and served from the shared per-interest cache
		// for the second user; only Painting needs another search.
		$searcher->expects( $this->exactly( 2 ) )
			->method( 'findRelated' )
			->willReturnCallback(
				static fn ( LinkTarget $interest ) => ReadingRecommendationsSearchResult::newSuccess(
					$related[$interest->getDBkey()] ?? []
				)
			);
		$service = $this->newService( [], [], [], null, $searcher, $lookup );

		$forAlice = $service->getRecommendations( $this->user );
		$forBob = $service->getRecommendations( new UserIdentityValue( 8, 'Bob' ) );

		$musicPick = static function ( array $recommendations ): ?string {
			foreach ( $recommendations as $recommendation ) {
				$interest = $recommendation->getInterest();
				if ( $interest && $interest->getDBkey() === 'Music' ) {
					return $recommendation->getTitle()->getDBkey();
				}
			}
			return null;
		};
		$this->assertNotNull( $musicPick( $forAlice ) );
		$this->assertSame( $musicPick( $forAlice ), $musicPick( $forBob ) );
	}

	public function testUsersWithTheSameInterestsShareTheCacheEntry() {
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->once() )
			->method( 'findRelated' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( $this->titles( 'Felidae' ) ) );
		$service = $this->newService( $this->titles( 'Cat' ), [], [], null, $searcher );

		$forAlice = $service->getRecommendations( $this->user );
		$forBob = $service->getRecommendations( new UserIdentityValue( 8, 'Bob' ) );
		$this->assertEquals( $forAlice, $forBob );
	}

	public function testNoInterestsUsesOnlyThePoolCache() {
		$pool = $this->createMock( FeaturedArticlePool::class );
		$pool->expects( $this->exactly( 2 ) )
			->method( 'getPool' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess(
				$this->titles( 'F1', 'F2', 'F3', 'F4', 'F5' )
			) );

		$service = $this->newService( [], [], [], $pool );
		$service->getRecommendations( $this->user );
		$service->getRecommendations( $this->user );
	}

	public static function provideHasInterests(): array {
		return [
			'none' => [ [], false ],
			'one' => [ [ 'Cat' ], true ],
		];
	}

	/**
	 * @dataProvider provideHasInterests
	 * @param string[] $interestDbkeys
	 */
	public function testHasInterests( array $interestDbkeys, bool $expected ): void {
		$service = $this->newService( $this->titles( ...$interestDbkeys ), [], [] );

		$this->assertSame( $expected, $service->hasInterests( $this->user ) );
	}

	public function testOneInterestGivesOneRelatedAndThreeGeneral() {
		$service = $this->newService(
			$this->titles( 'Cat' ),
			[ 'Cat' => $this->titles( 'Felidae', 'Lion', 'Tiger' ) ],
			$this->titles( 'F1', 'F2', 'F3', 'F4', 'F5' )
		);
		$recommendations = $service->getRecommendations( $this->user );

		$this->assertCount( 4, $recommendations );
		$this->assertEquals( new TitleValue( NS_MAIN, 'Cat' ), $recommendations[0]->getInterest() );
		$this->assertContains(
			$recommendations[0]->getTitle()->getDBkey(),
			[ 'Felidae', 'Lion', 'Tiger' ]
		);
		for ( $i = 1; $i < 4; $i++ ) {
			$this->assertTrue( $recommendations[$i]->isGeneral() );
		}
	}

	public function testRequestsFiftyRelatedCandidates() {
		$interest = new TitleValue( NS_MAIN, 'New_York_City' );
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->once() )
			->method( 'findRelated' )
			->with( $interest, 50 )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess(
				$this->titles( 'Brooklyn' )
			) );

		$service = $this->newService( [ $interest ], [], [], null, $searcher );
		$service->getRecommendations( $this->user );
	}

	public function testFourInterestsNeedNoPool() {
		$interests = $this->titles( 'A', 'B', 'C', 'D' );
		$related = [];
		foreach ( [ 'A', 'B', 'C', 'D' ] as $dbkey ) {
			$related[$dbkey] = $this->titles( "Rel{$dbkey}1", "Rel{$dbkey}2" );
		}
		$pool = $this->createNoOpMock( FeaturedArticlePool::class );
		$service = $this->newService( $interests, $related, [], $pool );

		$recommendations = $service->getRecommendations( $this->user );

		$this->assertCount( 4, $recommendations );
		foreach ( $recommendations as $recommendation ) {
			$this->assertFalse( $recommendation->isGeneral() );
		}
	}

	public function testExcludesInterestArticlesAndAlreadySelected() {
		// For interest A, candidate B is excluded as another interest article;
		// for interest B, candidate X is excluded as already selected.
		$service = $this->newService(
			$this->titles( 'A', 'B' ),
			[
				'A' => $this->titles( 'B', 'X' ),
				'B' => $this->titles( 'X', 'Y' ),
			],
			[]
		);
		$recommendations = $service->getRecommendations( $this->user );

		// The processing order of the two interests depends on the day, but the
		// exclusions force the same interest-to-title pairs either way.
		$this->assertCount( 2, $recommendations );
		$byInterest = [];
		foreach ( $recommendations as $recommendation ) {
			$byInterest[$recommendation->getInterest()->getDBkey()] =
				$recommendation->getTitle()->getDBkey();
		}
		ksort( $byInterest );
		$this->assertSame( [ 'A' => 'X', 'B' => 'Y' ], $byInterest );
	}

	public function testInterestRotationDiffersOnConsecutiveDays() {
		$interests = $this->titles( 'I0', 'I1', 'I2', 'I3', 'I4', 'I5' );
		$related = [];
		foreach ( [ 'I0', 'I1', 'I2', 'I3', 'I4', 'I5' ] as $dbkey ) {
			$related[$dbkey] = $this->titles( "Rel{$dbkey}" );
		}

		$firstDay = $this->getUsedInterests(
			$this->newService( $interests, $related, [] )->getRecommendations( $this->user )
		);
		ConvertibleTimestamp::setFakeTime( '2026-09-02T12:00:00Z' );
		$secondDay = $this->getUsedInterests(
			$this->newService( $interests, $related, [] )->getRecommendations( $this->user )
		);

		$this->assertCount( 4, $firstDay );
		$this->assertCount( 4, $secondDay );
		$this->assertNotEquals( $firstDay, $secondDay );
	}

	public function testRelatedArticleRotationDiffersOnConsecutiveDays() {
		$service = $this->newService(
			$this->titles( 'New_York_City' ),
			[
				'New_York_City' => $this->titles( 'Brooklyn', 'Manhattan', 'Queens' ),
			],
			[]
		);

		$firstDay = $service->getRecommendations( $this->user );
		ConvertibleTimestamp::setFakeTime( '2026-09-02T12:00:00Z' );
		$secondDay = $service->getRecommendations( $this->user );

		$this->assertCount( 1, $firstDay );
		$this->assertCount( 1, $secondDay );
		$this->assertSame( 'New_York_City', $firstDay[0]->getInterest()->getDBkey() );
		$this->assertSame( 'New_York_City', $secondDay[0]->getInterest()->getDBkey() );
		$this->assertNotSame(
			$firstDay[0]->getTitle()->getDBkey(),
			$secondDay[0]->getTitle()->getDBkey()
		);
	}

	public function testBothSourcesEmpty() {
		$service = $this->newService( [], [], [] );
		$this->assertSame( [], $service->getRecommendations( $this->user ) );
	}

	public function testCacheDisabledRecomputesEveryRequest() {
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->exactly( 2 ) )
			->method( 'findRelated' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( $this->titles( 'Felidae' ) ) );
		$service = $this->newService(
			$this->titles( 'Cat' ), [], [], null, $searcher, cacheEnabled: false );

		$first = $service->getRecommendations( $this->user );
		$second = $service->getRecommendations( $this->user );
		$this->assertEquals( $first, $second );
	}

	public function testCacheDisabledSettingIgnoredWithoutDeveloperSetup() {
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->once() )
			->method( 'findRelated' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( $this->titles( 'Felidae' ) ) );
		$service = $this->newService(
			$this->titles( 'Cat' ), [], [], null, $searcher,
			cacheEnabled: false, developerSetup: false );

		$first = $service->getRecommendations( $this->user );
		$this->assertEquals( $first, $service->getRecommendations( $this->user ) );
	}

	public function testSecondCallIsACacheHit() {
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->once() )
			->method( 'findRelated' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( $this->titles( 'Felidae' ) ) );
		$service = $this->newService( $this->titles( 'Cat' ), [], [], null, $searcher );

		$first = $service->getRecommendations( $this->user );
		$second = $service->getRecommendations( $this->user );
		$this->assertEquals( $first, $second );
	}

	public function testChangedInterestsRefreshTheListAtOnce() {
		$lookup = $this->createMock( InterestArticlesLookup::class );
		$lookup->method( 'getInterests' )->willReturnOnConsecutiveCalls(
			$this->titles( 'Cat' ),
			$this->titles( 'Dog' )
		);
		$service = $this->newService(
			[],
			[
				'Cat' => $this->titles( 'Felidae' ),
				'Dog' => $this->titles( 'Canidae' ),
			],
			[],
			null,
			null,
			$lookup
		);

		$first = $service->getRecommendations( $this->user );
		$second = $service->getRecommendations( $this->user );
		$this->assertSame( 'Felidae', $first[0]->getTitle()->getDBkey() );
		$this->assertSame( 'Canidae', $second[0]->getTitle()->getDBkey() );
	}

	public function testSuccessfulUnderFullListIsCachedForTheDay() {
		$cacheTime = microtime( true );
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$wanCache->setMockTime( $cacheTime );
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->once() )
			->method( 'findRelated' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( $this->titles( 'Felidae' ) ) );
		$service = $this->newService( $this->titles( 'Cat' ), [], [], null, $searcher, null, $wanCache );

		$first = $service->getRecommendations( $this->user );
		$cacheTime += 5 * WANObjectCache::TTL_MINUTE + 1;
		$second = $service->getRecommendations( $this->user );

		$this->assertEquals( $first, $second );
	}

	public function testSearchErrorUsesShortCacheLifetime() {
		$cacheTime = microtime( true );
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$wanCache->setMockTime( $cacheTime );
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->exactly( 2 ) )
			->method( 'findRelated' )
			->willReturn( ReadingRecommendationsSearchResult::newError() );
		$service = $this->newService( $this->titles( 'Cat' ), [], [], null, $searcher, null, $wanCache );

		$service->getRecommendations( $this->user );
		$cacheTime += 5 * WANObjectCache::TTL_MINUTE + 1;
		$service->getRecommendations( $this->user );
	}

	/**
	 * @param string ...$dbkeys
	 * @return TitleValue[]
	 */
	private function titles( string ...$dbkeys ): array {
		return array_map(
			static fn ( string $dbkey ) => new TitleValue( NS_MAIN, $dbkey ),
			$dbkeys
		);
	}

	/**
	 * @param ReadingRecommendation[] $recommendations
	 * @return string[] Sorted DB keys of the interests the items came from.
	 */
	private function getUsedInterests( array $recommendations ): array {
		$dbkeys = array_map(
			static fn ( ReadingRecommendation $recommendation ) =>
				$recommendation->getInterest()->getDBkey(),
			$recommendations
		);
		sort( $dbkeys );
		return $dbkeys;
	}

	/**
	 * @param TitleValue[] $interests
	 * @param array<string,TitleValue[]> $relatedByDbkey Candidates per interest DB key
	 * @param TitleValue[] $poolTitles
	 * @param FeaturedArticlePool|MockObject|null $pool
	 * @param ReadingRecommendationsSearcher|MockObject|null $searcher
	 * @param InterestArticlesLookup|MockObject|null $lookup
	 * @param WANObjectCache|null $wanCache
	 * @param bool $cacheEnabled
	 * @param bool $developerSetup
	 * @return ReadingRecommendationsService
	 */
	private function newService(
		array $interests,
		array $relatedByDbkey,
		array $poolTitles,
		$pool = null,
		$searcher = null,
		$lookup = null,
		?WANObjectCache $wanCache = null,
		bool $cacheEnabled = true,
		bool $developerSetup = true
	): ReadingRecommendationsService {
		if ( !$lookup ) {
			$lookup = $this->createMock( InterestArticlesLookup::class );
			$lookup->method( 'getInterests' )->willReturn( $interests );
		}
		if ( !$searcher ) {
			$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
			$searcher->method( 'findRelated' )->willReturnCallback(
				static fn ( LinkTarget $interest ) => ReadingRecommendationsSearchResult::newSuccess(
					$relatedByDbkey[$interest->getDBkey()] ?? []
				)
			);
		}
		if ( !$pool ) {
			$pool = $this->createMock( FeaturedArticlePool::class );
			$pool->method( 'getPool' )->willReturn(
				ReadingRecommendationsSearchResult::newSuccess( $poolTitles )
			);
		}
		return new ReadingRecommendationsService(
			new ServiceOptions(
				ReadingRecommendationsService::CONSTRUCTOR_OPTIONS,
				[ MainConfigNames::Localtimezone => 'UTC' ]
			),
			$wanCache ?? new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$lookup,
			$pool,
			$searcher,
			new ReadingRecommendationsCachePolicy( new ServiceOptions(
				ReadingRecommendationsCachePolicy::CONSTRUCTOR_OPTIONS,
				[
					'GEReadingRecommendationsCacheEnabled' => $cacheEnabled,
					'GEDeveloperSetup' => $developerSetup,
				]
			) )
		);
	}
}
