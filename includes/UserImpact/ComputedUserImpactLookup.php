<?php

namespace GrowthExperiments\UserImpact;

use DateTime;
use ExtensionRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use IBufferingStatsdDataFactory;
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

	/** Cutoff for edit statistics. See also DATA_ROWS_LIMIT in ScoreCards.vue. */
	private const MAX_EDITS = 1000;

	/** Cutoff for thanks count. See also DATA_ROWS_LIMIT in ScoreCards.vue. */
	private const MAX_THANKS = 1000;

	/** How many articles to use for $priorityTitles in getPageViewData(). */
	private const PRIORITY_ARTICLES_LIMIT = 5;

	/** How many days of pageview data to get. PageViewInfo supports up to 60. */
	public const PAGEVIEW_DAYS = 60;

	/** @var ServiceOptions */
	private $config;

	/** @var IDatabase */
	private $dbr;

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

	private IBufferingStatsdDataFactory $statsdDataFactory;

	/**
	 * @param ServiceOptions $config
	 * @param IDatabase $dbr
	 * @param NameTableStore $changeTagDefStore
	 * @param UserFactory $userFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param TitleFormatter $titleFormatter
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface|null $loggerFactory
	 * @param PageViewService|null $pageViewService
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	public function __construct(
		ServiceOptions $config,
		IDatabase $dbr,
		NameTableStore $changeTagDefStore,
		UserFactory $userFactory,
		UserOptionsLookup $userOptionsLookup,
		TitleFormatter $titleFormatter,
		TitleFactory $titleFactory,
		?LoggerInterface $loggerFactory,
		?PageViewService $pageViewService,
		IBufferingStatsdDataFactory $statsdDataFactory
	) {
		$this->config = $config;
		$this->dbr = $dbr;
		$this->changeTagDefStore = $changeTagDefStore;
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->titleFormatter = $titleFormatter;
		$this->titleFactory = $titleFactory;
		$this->logger = $loggerFactory ?? new NullLogger();
		$this->pageViewService = $pageViewService;
		$this->statsdDataFactory = $statsdDataFactory;
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
			$editData->getEditCountByNamespace(),
			$editData->getEditCountByDay(),
			$editData->getUserTimeCorrection(),
			$editData->getNewcomerTaskEditCount(),
			wfTimestampOrNull( TS_UNIX, $editData->getLastEditTimestamp() ),
			ComputeEditingStreaks::getLongestEditingStreak( $editData->getEditCountByDay() )
		);
	}

	/** @inheritDoc */
	public function getExpensiveUserImpact( UserIdentity $user ): ?ExpensiveUserImpact {
		$start = microtime( true );
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
			$editData->getEditedArticles(),
			// Just use the last edited articles as "top articles" for now. This won't
			// exclude retrieving data for other articles, it just prioritizes the most
			// recent ones.
			array_slice( $editData->getEditedArticles(), 0, self::PRIORITY_ARTICLES_LIMIT ),
			self::PAGEVIEW_DAYS
		);
		if ( $pageViewData === null ) {
			return null;
		}

		$expensiveUserImpact = new ExpensiveUserImpact(
			$user,
			$thanksCount,
			$editData->getEditCountByNamespace(),
			$editData->getEditCountByDay(),
			$editData->getUserTimeCorrection(),
			$editData->getNewcomerTaskEditCount(),
			wfTimestampOrNull( TS_UNIX, $editData->getLastEditTimestamp() ),
			$pageViewData['dailyTotalViews'],
			$pageViewData['dailyArticleViews'],
			ComputeEditingStreaks::getLongestEditingStreak( $editData->getEditCountByDay() )
		);
		$this->statsdDataFactory->timing(
			'timing.growthExperiments.ComputedUserImpactLookup.getExpensiveUserImpact', microtime( true ) - $start
		);
		return $expensiveUserImpact;
	}

	/**
	 * Run a SQL query to fetch edit data for the user.
	 *
	 * @param User $user
	 * @return EditData
	 */
	private function getEditData( User $user ): EditData {
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

		$queryBuilder->fields( [ 'page_namespace', 'page_title', 'rev_timestamp' ] );
		$queryBuilder->where( [ 'rev_actor' => $user->getActorId() ] );
		// hopefully able to use the rev_actor_timestamp index for an efficient query
		$queryBuilder->orderBy( 'rev_timestamp', 'DESC' );
		$queryBuilder->andWhere( [ 'page_namespace' => NS_MAIN ] );
		$queryBuilder->limit( self::MAX_EDITS );
		$queryBuilder->caller( __METHOD__ );

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
			// We're iterating over the result set, newest edits to oldest edits in descending order. The same
			// article can have been edited multiple times. We'll stash the revision timestamp of the oldest
			// edit made by the user to the article; we will use that later to calculate the "start date"
			// for the impact of the user for a particular article, e.g. when making a pageviews tool URL
			// or choosing the date range for page view data to display for an article.
			$editedArticles[$titleDbKey]['oldestEdit'] = $row->rev_timestamp;
			$editedArticles[$titleDbKey]['newestEdit'] =
				$editedArticles[$titleDbKey]['newestEdit'] ?? $row->rev_timestamp;
		}

		return new EditData(
			$editCountByNamespace,
			array_reverse( $this->updateToIso8601DateKeys( $editCountByDay ) ),
			$newcomerTaskEditCount,
			$lastEditTimestamp,
			$editedArticles,
			$userTimeCorrection
		);
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
	 * Returns page views and other data, or null on error during data fetching.
	 * Must not be called when $this->pageViewService is null.
	 * @param User $user
	 * @param array[] $titles Data about edited articles. See {@see EditData::getEditedArticles()}
	 *   for format.
	 * @param array[] $priorityTitles A subset of $titles that should get priority treatment
	 *   (in case not all the pageview data can be retrieved due to resource limits).
	 * @param int $days How many days to query. No more than 60.
	 * @return array|null
	 *   - dailyTotalViews: (array<string, int>) daily number of total views of articles in $titles,
	 *     keyed by ISO 8601 date.
	 *   - dailyArticleViews: (array[]) Daily article views and other data. Keyed by
	 *     prefixed DBkey; values are arrays with the following fields:
	 *     - views: (int[]) daily article views, keyed by ISO 8601 date. 0 for days before the
	 *       user's first edit to the article.
	 *     - firstEditDate: (string) ISO 8601 date of the user's first edit to the article.
	 *       If the user made a very high number of total edits, it might just be some edit the
	 *       user made to the article, not necessarily the first.
	 *     - newestEdit: (string) MW_TS timestamp of the user's most recent edit.
	 *     - imageUrl: (string|null) URL of a thumbnail of the article's main image.
	 * @phan-return array{dailyTotalViews:array<string,int>,dailyArticleViews:array<string,array{views:array<string,int>,firstEditDate:string,newestEdit:string,imageUrl:?string}>}|null
	 * @throws MalformedTitleException
	 */
	private function getPageViewData( User $user, array $titles, array $priorityTitles, int $days ): ?array {
		// Short-circuit if the user has no edits.
		if ( !$titles ) {
			return [
				'dailyTotalViews' => array_fill_keys( $this->getDatesForLastDays( $days ), 0 ),
				'dailyArticleViews' => [],
			];
		}

		// $priorityTitles is a subset of $titles but putting it to the front makes sure the data
		// for those titles is fetched even if PageViewInfo cuts off the list of titles at some
		// point, which it is allowed to do.
		$allTitles = array_merge( $priorityTitles, $titles );
		$allTitleObjects = [];

		foreach ( $allTitles as $title => $data ) {
			$allTitleObjects[$title] = [
				'title' => $this->titleFactory->newFromTextThrow( $title ),
				// rev_timestamp is in TS_MW format (e.g. 20210406200220), we only want
				// the first 8 characters for comparison with Ymd format date strings.
				'rev_timestamp' => substr( $data['oldestEdit'], 0, 8 ),
				'newestEdit' => $data['newestEdit'],
				'oldestEdit' => $data['oldestEdit']
			];
		}
		$status = $this->pageViewService->getPageData( array_column( $allTitleObjects, 'title' ), $days );
		if ( !$status->isOK() ) {
			$this->logger->error( Status::wrap( $status )->getWikiText( false, false, 'en' ) );
			return null;
		}
		$pageViewData = $status->getValue();

		$dailyTotalViews = [];
		$dailyArticleViews = [];
		foreach ( $pageViewData as $title => $days ) {
			// Normalize titles as PageViewInfo does not define which title format it uses :(
			$title = str_replace( ' ', '_', $title );
			$mwTitle = $this->titleFactory->newFromTextThrow( $title );
			$imageUrl = $this->getImage( $mwTitle );
			if ( $imageUrl ) {
				$dailyArticleViews[$title]['imageUrl'] = $imageUrl;
			}
			$firstEditDate = new DateTime( $allTitleObjects[$title]['rev_timestamp'] );
			$dailyArticleViews[$title]['firstEditDate'] = $firstEditDate->format( 'Y-m-d' );
			$dailyArticleViews[$title]['newestEdit'] = $allTitleObjects[$title]['newestEdit'];

			foreach ( $days as $day => $views ) {
				$dailyTotalViews[$day] = ( $dailyTotalViews[$day] ?? 0 ) + $views;
				$dailyArticleViews[$title]['views'][$day] = ( $dailyArticleViews[$title][$day] ?? 0 ) + $views;
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
