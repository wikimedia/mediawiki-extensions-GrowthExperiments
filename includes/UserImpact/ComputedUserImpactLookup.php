<?php

namespace GrowthExperiments\UserImpact;

use ActorMigration;
use DateTime;
use ExtensionRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use MalformedTitleException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\PageViewInfo\PageViewService;
use MediaWiki\MainConfigNames;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserTimeCorrection;
use MWTimestamp;
use PageImages\PageImages;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Status;
use Title;
use TitleFactory;
use TitleFormatter;
use TitleValue;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class ComputedUserImpactLookup implements UserImpactLookup {

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::LocalTZoffset
	];

	/**
	 * Size in pixels of the thumb image to request to PageImages. Matches the Codex
	 * thumbnail component size it is rendered in. Used in the articles list (ArticlesList.vue)
	 * in the impact module.
	 */
	private const THUMBNAIL_SIZE = 40;

	/** Cutoff for edit statistics. */
	private const MAX_EDITS = 1000;

	/** Cutoff for thanks count. */
	private const MAX_THANKS = 1000;

	/** How many articles to use for $topTitles in getPageViewData(). */
	private const TOP_ARTICLES = 5;

	/** How many days of pageview data to get. PageViewInfo supports up to 60. */
	public const PAGEVIEW_DAYS = 60;

	/** @var ServiceOptions */
	private $config;

	/** @var IDatabase */
	private $dbr;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var NameTableStore */
	private $changeTagDefStore;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LoggerInterface|null */
	private $logger;

	/** @var PageViewService|null */
	private $pageViewService;

	/**
	 * @param ServiceOptions $config
	 * @param IDatabase $dbr
	 * @param ActorMigration $actorMigration
	 * @param NameTableStore $changeTagDefStore
	 * @param UserFactory $userFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param TitleFormatter $titleFormatter
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface|null $loggerFactory
	 * @param PageViewService|null $pageViewService
	 */
	public function __construct(
		ServiceOptions $config,
		IDatabase $dbr,
		ActorMigration $actorMigration,
		NameTableStore $changeTagDefStore,
		UserFactory $userFactory,
		UserOptionsLookup $userOptionsLookup,
		TitleFormatter $titleFormatter,
		TitleFactory $titleFactory,
		?LoggerInterface $loggerFactory,
		?PageViewService $pageViewService
	) {
		$this->config = $config;
		$this->dbr = $dbr;
		$this->actorMigration = $actorMigration;
		$this->changeTagDefStore = $changeTagDefStore;
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->titleFormatter = $titleFormatter;
		$this->titleFactory = $titleFactory;
		$this->logger = $loggerFactory ?? new NullLogger();
		$this->pageViewService = $pageViewService;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user ): ?UserImpact {
		$user = $this->userFactory->newFromUserIdentity( $user );
		if ( $user->isAnon() || $user->isHidden() ) {
			return null;
		}

		$editData = $this->getEditData( $user );
		$thanksCount = $this->getThanksCount( $user );

		return new UserImpact(
			$user,
			$thanksCount,
			$editData['editCountByNamespace'],
			$editData['editCountByDay'],
			new UserTimeCorrection(
				$this->userOptionsLookup->getOption( $user, 'timecorrection' ),
				// Make the time correction object testing friendly - otherwise it would contain a
				// current-time DateTime object.
				new DateTime( '@' . ConvertibleTimestamp::time() ),
				$this->config->get( MainConfigNames::LocalTZoffset )
			),
			$editData['newcomerTaskEditCount'],
			wfTimestampOrNull( TS_UNIX, $editData['lastEditTimestamp'] ),
			ComputeEditingStreaks::getLongestEditingStreak( $editData['editCountByDay'] )
		);
	}

	/** @inheritDoc */
	public function getExpensiveUserImpact( UserIdentity $user ): ?ExpensiveUserImpact {
		if ( !$this->pageViewService ) {
			return null;
		}
		$user = $this->userFactory->newFromUserIdentity( $user );
		if ( $user->isAnon() || $user->isHidden() ) {
			return null;
		}

		$editData = $this->getEditData( $user );
		$thanksCount = $this->getThanksCount( $user );
		$pageViewData = $this->getPageViewData(
			$user,
			$editData['editedArticles'],
			// Just use the last edited articles as "top articles" for now.
			array_slice( $editData['editedArticles'], 0, self::TOP_ARTICLES ),
			self::PAGEVIEW_DAYS
		);
		if ( $pageViewData === null ) {
			return null;
		}

		return new ExpensiveUserImpact(
			$user,
			$thanksCount,
			$editData['editCountByNamespace'],
			$editData['editCountByDay'],
			new UserTimeCorrection(
				$this->userOptionsLookup->getOption( $user, 'timecorrection' ),
				// Make the time correction object testing friendly - otherwise it would contain a
				// current-time DateTime object.
				new DateTime( '@' . ConvertibleTimestamp::time() ),
				$this->config->get( MainConfigNames::LocalTZoffset )
			),
			$editData['newcomerTaskEditCount'],
			wfTimestampOrNull( TS_UNIX, $editData['lastEditTimestamp'] ),
			$pageViewData['dailyTotalViews'],
			$pageViewData['dailyArticleViews'],
			ComputeEditingStreaks::getLongestEditingStreak( $editData['editCountByDay'] )
		);
	}

	/**
	 * @param User $user
	 * @return array
	 *   - editCountByNamespace: (array<int, int>) number of edits made by the user pernamespace
	 *   - editCountByDay: (array<string, int>) number of article-space edits made by the user
	 *     by day. The format matches UserImpact::getEditCountByDay().
	 *   - newcomerTaskEditCount: (int) number of edits with "newcomer task" tag (suggested edits)
	 * 	 - lastEditTimestamp: (string|null) MW_TS date of last article-space edit
	 *   - editedArticles: (string[]) list of article-space titles the user has edited, sorted from
	 *     most recently edited to least recently edited.
	 *   - userTimeCorrection: (UserTimeCorrection) the timezone used for defining what "day" means
	 *     in editCountByDay, based on the user's timezone preference.
	 * @phan-return array{editCountByNamespace:array<int,int>,editCountByDay:array<string,int>,newcomerTaskEditCount:int,lastEditTimestamp:string|null,editedArticles:string[],userTimeCorrection:UserTimeCorrection}
	 */
	private function getEditData( User $user ): array {
		$queryBuilder = new SelectQueryBuilder( $this->dbr );
		$queryBuilder->table( 'revision' )
			->join( 'page', null, 'rev_page = page_id' );
		try {
			$queryBuilder->leftJoin( 'change_tag', null, [
				'rev_id = ct_rev_id',
				'ct_tag_id' => $this->changeTagDefStore->getId( TaskTypeHandler::NEWCOMER_TASK_TAG ),
			] );
			$queryBuilder->field( 'ct_id' );
		} catch ( NameTableAccessException $nameTableAccessException ) {
			// The tag won't exist in test scenarios, and possibly in some small wikis where
			// no suggested edits have been done yet. We can safely ignore the exception,
			// it will mean that 'newcomerTaskEditCount' is 0 in the result.
		}

		// actor-migrated version of where( [ 'rev_user' => $user->getId() ] )
		$actorQuery = $this->actorMigration->getWhere( $this->dbr, 'rev_user', $user );
		$queryBuilder->tables( $actorQuery['tables'] )
			->joinConds( $actorQuery['joins'] )
			->conds( $actorQuery['conds'] );
		$queryBuilder->fields( [ 'page_namespace', 'page_title', 'rev_timestamp' ] );
		// hopefully able to use the rev_actor_timestamp index for an efficient query
		$queryBuilder->orderBy( 'rev_timestamp', 'DESC' );
		$queryBuilder->limit( self::MAX_EDITS );

		$userTimeCorrection = new UserTimeCorrection(
			$this->userOptionsLookup->getOption( $user, 'timecorrection' ),
			// Make the time correction object testing friendly - otherwise it would contain a
			// current-time DateTime object.
			new DateTime( '@' . ConvertibleTimestamp::time() ),
			$this->config->get( MainConfigNames::LocalTZoffset )
		);

		$editCountByNamespace = [];
		$editCountByDay = [];
		$newcomerTaskEditCount = 0;
		$lastEditTimestamp = null;
		$editedArticles = [];

		foreach ( $queryBuilder->fetchResultSet() as $row ) {
			$linkTarget = new TitleValue( (int)$row->page_namespace, $row->page_title );
			$titleDbKey = $this->titleFormatter->getPrefixedDBkey( $linkTarget );
			$editTime = new MWTimestamp( $row->rev_timestamp );
			$editTime->offsetForUser( $user );
			$day = $editTime->format( 'Ymd' );

			$editCountByNamespace[$row->page_namespace]
				= ( $editCountByNamespace[$row->page_namespace] ?? 0 ) + 1;
			$editCountByDay[$day] = ( $editCountByDay[$day] ?? 0 ) + 1;
			if ( $row->ct_id ?? null ) {
				$newcomerTaskEditCount++;
			}
			if ( $lastEditTimestamp === null ) {
				$lastEditTimestamp = $row->rev_timestamp;
			}
			$editedArticles[$titleDbKey] = true;
		}

		return [
			'editCountByNamespace' => $editCountByNamespace,
			'editCountByDay' => array_reverse( $this->updateToIso8601DateKeys( $editCountByDay ) ),
			'newcomerTaskEditCount' => $newcomerTaskEditCount,
			'lastEditTimestamp' => $lastEditTimestamp,
			'editedArticles' => array_merge( array_keys( $editedArticles ) ),
			'userTimeCorrection' => $userTimeCorrection,
		];
	}

	/**
	 * @param User $user
	 * @return int Number of thanks received for the user ID
	 */
	private function getThanksCount( User $user ): int {
		$userPage = $user->getUserPage();
		$queryBuilder = new SelectQueryBuilder( $this->dbr );
		$queryBuilder->table( 'logging' );
		$queryBuilder->field( '1' );
		// There is no type + target index, but there's a target index (log_page_time)
		// and it's unlikely the user's page has many other log events than thanks,
		// so the query should be okay.
		$queryBuilder->conds( [
			'log_type' => 'thanks',
			'log_action' => 'thank',
			'log_namespace' => $userPage->getNamespace(),
			'log_title' => $userPage->getDBkey(),
		] );
		$queryBuilder->orderBy( 'log_timestamp', 'DESC' );
		$queryBuilder->limit( self::MAX_THANKS );
		return $queryBuilder->fetchRowCount();
	}

	/**
	 * Must not be called when $this->pageViewService is null.
	 * @param User $user
	 * @param string[] $titles List of pages in prefixed DBkey format.
	 * @param string[] $topTitles List of pages in prefixed DBkey format.
	 * @param int $days How many days to query. No more than 60.
	 * @return array|null
	 *   - dailyTotalViews: (array<string, int>) daily number of total views of articles in $titles,
	 *     keyed by ISO 8601 date.
	 *   - dailyArticleViews: (array<string, array<string, int>>) For each of $topTitles, daily
	 *     number of pageviews on each of the last $days days. Keyed by prefixed DBkey and then
	 *     by ISO 8601 date.
	 * @phan-return array{dailyTotalViews:array<string,int>,dailyArticleViews:array<string,array<string,int>>}|null
	 * @throws MalformedTitleException
	 */
	private function getPageViewData( User $user, array $titles, array $topTitles, int $days ): ?array {
		// Short-circuit if the user has no edits.
		if ( !$titles ) {
			return [
				'dailyTotalViews' => array_fill_keys( $this->getDatesForLastDays( $days ), 0 ),
				'dailyArticleViews' => [],
			];
		}

		// $topTitles is a subset of $titles but putting it to the front makes sure the data
		// for those titles is fetched even if PageViewInfo cuts off the list of titles at some
		// point, which it is allowed to do.
		$allTitles = array_unique( array_merge( $topTitles, $titles ) );
		$allTitleObjects = array_map( function ( string $title ) {
			return $this->titleFactory->newFromTextThrow( $title );
		}, $allTitles );
		$status = $this->pageViewService->getPageData( $allTitleObjects, $days );
		if ( !$status->isOK() ) {
			$this->logger->error( Status::wrap( $status )->getWikiText( false, false, 'en' ) );
			return null;
		}
		$pageViewData = $status->getValue();

		$dailyTotalViews = [];
		// Pre-fill with empty arrays so the order is preserved.
		$dailyArticleViews = array_fill_keys( $topTitles, [] );
		foreach ( $pageViewData as $title => $days ) {
			// Normalize titles as PageViewInfo does not define which title format it uses :(
			$title = str_replace( ' ', '_', $title );
			$mwTitle = $this->titleFactory->newFromTextThrow( $title );
			$imageUrl = $this->getImage( $mwTitle );
			if ( $imageUrl ) {
				$dailyArticleViews[$title]['imageUrl'] = $imageUrl;
			}

			foreach ( $days as $day => $views ) {
				$dailyTotalViews[$day] = ( $dailyTotalViews[$day] ?? 0 ) + $views;
				if ( in_array( $title, $topTitles, true ) ) {
					$dailyArticleViews[$title]['views'][$day] = ( $dailyArticleViews[$title][$day] ?? 0 ) + $views;
				}
			}
		}

		return [
			'dailyTotalViews' => $dailyTotalViews,
			'dailyArticleViews' => $dailyArticleViews,
		];
	}

	/**
	 * Change array keys from MW_TS date prefixes to ISO 8601 dates.
	 * @param array $mwTsArray
	 * @return array
	 */
	private function updateToIso8601DateKeys( array $mwTsArray ): array {
		$iso8601Array = [];
		foreach ( $mwTsArray as $mwTsKey => $value ) {
			$iso8601Key = substr( $mwTsKey, 0, 4 ) . '-' . substr( $mwTsKey, 4, 2 )
				. '-' . substr( $mwTsKey, 6, 2 );
			$iso8601Array[$iso8601Key] = $value;
		}
		return $iso8601Array;
	}

	/**
	 * Return ISO 8601 dates for the last $days days.
	 * These are not necessarily the exact same dates we would get from the pageview
	 * service, but this is only used when there are no edits, so it hardly matters.
	 * @param int $days
	 * @return string[]
	 */
	private function getDatesForLastDays( int $days ): array {
		$dates = [];
		for ( $i = 0; $i < $days; $i++ ) {
			$dates[] = date( 'Y-m-d', strtotime( "-$i days", ConvertibleTimestamp::time() ) );
		}
		return array_reverse( $dates );
	}

	/**
	 * Get image URL for a page
	 * Depends on the PageImages extension.
	 *
	 * @param Title $title
	 * @return ?string
	 */
	private function getImage( Title $title ): ?string {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			return null;
		}

		$imageFile = PageImages::getPageImage( $title );
		if ( $imageFile ) {
			$ratio = $imageFile->getWidth() / $imageFile->getHeight();
			$options = [
				'width' => $ratio > 1 ?
					// Avoid decimals in the width because it makes the thumb url construction fail
					floor( self::THUMBNAIL_SIZE / $imageFile->getHeight() * $imageFile->getWidth() ) :
					self::THUMBNAIL_SIZE
			];

			$thumb = $imageFile->transform( $options );
			if ( $thumb ) {
				return $thumb->getUrl() ?: null;
			}
		}

		return null;
	}

}
