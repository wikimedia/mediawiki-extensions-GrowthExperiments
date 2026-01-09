<?php

namespace GrowthExperiments\UserImpact;

use DateTime;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\Util;
use LogicException;
use MediaWiki\ChangeTags\ChangeTags;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\PageViewInfo\PageViewService;
use MediaWiki\Extension\Thanks\ThanksQueryHelper;
use MediaWiki\MainConfigNames;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use PageImages\PageImages;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Stats\StatsFactory;

class ComputedUserImpactLookup implements UserImpactLookup {

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::LocalTZoffset,
		'GEUserImpactMaxArticlesToProcessForPageviews',
		'GEUserImpactMaximumProcessTimeSeconds',
		'GEUserImpactMaxEdits',
		'GEUserImpactMaxThanks',
		'GEHomepageSuggestedEditsEnabled',
	];

	/**
	 * Size in pixels of the thumb image to request to PageImages. Matches the Codex
	 * thumbnail component size it is rendered in. Used in the articles list (ArticlesList.vue)
	 * in the impact module.
	 */
	private const THUMBNAIL_SIZE = 40;

	/** How many articles to use for $priorityTitles in getPageViewData(). */
	private const PRIORITY_ARTICLES_LIMIT = 5;

	/** How many days of pageview data to get. PageViewInfo supports up to 60. */
	public const PAGEVIEW_DAYS = 60;

	private ServiceOptions $config;
	private IConnectionProvider $connectionProvider;
	private NameTableStore $changeTagDefStore;
	private UserFactory $userFactory;
	private UserEditTracker $userEditTracker;
	private TitleFormatter $titleFormatter;
	private TitleFactory $titleFactory;
	private StatsFactory $statsFactory;
	private ?LoggerInterface $logger;
	private ?PageImages $pageImages;
	private ?PageViewService $pageViewService;
	private ?ThanksQueryHelper $thanksQueryHelper;
	private TaskTypeHandlerRegistry $taskTypeHandlerRegistry;
	private ConfigurationLoader $configurationLoader;

	/**
	 * @param ServiceOptions $config
	 * @param IConnectionProvider $connectionProvider
	 * @param NameTableStore $changeTagDefStore
	 * @param UserFactory $userFactory
	 * @param UserEditTracker $userEditTracker
	 * @param TitleFormatter $titleFormatter
	 * @param TitleFactory $titleFactory
	 * @param StatsFactory $statsFactory
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ConfigurationLoader $configurationLoader
	 * @param LoggerInterface|null $loggerFactory
	 * @param PageImages|null $pageImages
	 * @param PageViewService|null $pageViewService
	 * @param ThanksQueryHelper|null $thanksQueryHelper
	 */
	public function __construct(
		ServiceOptions $config,
		IConnectionProvider $connectionProvider,
		NameTableStore $changeTagDefStore,
		UserFactory $userFactory,
		UserEditTracker $userEditTracker,
		TitleFormatter $titleFormatter,
		TitleFactory $titleFactory,
		StatsFactory $statsFactory,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ConfigurationLoader $configurationLoader,
		?LoggerInterface $loggerFactory,
		?PageImages $pageImages,
		?PageViewService $pageViewService,
		?ThanksQueryHelper $thanksQueryHelper
	) {
		$this->config = $config;
		$this->connectionProvider = $connectionProvider;
		$this->changeTagDefStore = $changeTagDefStore;
		$this->userFactory = $userFactory;
		$this->userEditTracker = $userEditTracker;
		$this->titleFormatter = $titleFormatter;
		$this->titleFactory = $titleFactory;
		$this->statsFactory = $statsFactory;
		$this->logger = $loggerFactory ?? new NullLogger();
		$this->pageImages = $pageImages;
		$this->pageViewService = $pageViewService;
		$this->thanksQueryHelper = $thanksQueryHelper;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->configurationLoader = $configurationLoader;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user, int $flags = IDBAccessObject::READ_NORMAL ): ?UserImpact {
		$user = $this->userFactory->newFromUserIdentity( $user );
		if ( !$user->isRegistered() || $user->isHidden() ) {
			return null;
		}

		$editData = $this->getEditData( $user, $flags );
		$thanksReceivedCount = $this->getThanksReceivedCount( $user, $flags );
		$thanksGivenCount = $this->getThanksGivenCount( $user, $flags );

		return new UserImpact(
			$user,
			$thanksReceivedCount,
			$thanksGivenCount,
			$editData->getEditCountByNamespace(),
			$editData->getEditCountByDay(),
			$editData->getEditCountByTaskType(),
			$editData->getRevertedEditCount(),
			$editData->getNewcomerTaskEditCount(),
			wfTimestampOrNull( TS_UNIX, $editData->getLastEditTimestamp() ),
			ComputeEditingStreaks::getLongestEditingStreak( $editData->getEditCountByDay() ),
			$editData->getCreatedArticlesCount(),
			$this->userEditTracker->getUserEditCount( $user )
		);
	}

	/** @inheritDoc */
	public function getExpensiveUserImpact(
		UserIdentity $user,
		int $flags = IDBAccessObject::READ_NORMAL,
		array $priorityArticles = []
	): ?ExpensiveUserImpact {
		$start = microtime( true );
		if ( !$this->pageViewService ) {
			return null;
		}
		$user = $this->userFactory->newFromUserIdentity( $user );
		if ( !$user->isRegistered() || $user->isHidden() ) {
			return null;
		}

		$editData = $this->getEditData( $user, $flags );
		$thanksReceivedCount = $this->getThanksReceivedCount( $user, $flags );
		$thanksGivenCount = $this->getThanksGivenCount( $user, $flags );
		// Use priority articles if known, otherwise make use of the last edited articles
		// as "top articles" .
		// This won't exclude retrieving data for other articles, but ensures that we fetch page
		// view data for priority (as defined by the caller) articles first.
		if ( $priorityArticles ) {
			$priorityArticles = array_intersect_key( $editData->getEditedArticles(), $priorityArticles );
		} else {
			$priorityArticles = $editData->getEditedArticles();
		}
		$pageViewData = $this->getPageViewData(
			$user,
			$editData->getEditedArticles(),
			array_slice( $priorityArticles, 0, self::PRIORITY_ARTICLES_LIMIT, true ),
			self::PAGEVIEW_DAYS
		);
		if ( $pageViewData === null ) {
			return null;
		}

		$expensiveUserImpact = new ExpensiveUserImpact(
			$user,
			$thanksReceivedCount,
			$thanksGivenCount,
			$editData->getEditCountByNamespace(),
			$editData->getEditCountByDay(),
			$editData->getEditCountByTaskType(),
			$editData->getRevertedEditCount(),
			$editData->getNewcomerTaskEditCount(),
			wfTimestampOrNull( TS_UNIX, $editData->getLastEditTimestamp() ),
			$pageViewData['dailyTotalViews'],
			$pageViewData['dailyArticleViews'],
			ComputeEditingStreaks::getLongestEditingStreak( $editData->getEditCountByDay() ),
			$editData->getCreatedArticlesCount(),
			$this->userEditTracker->getUserEditCount( $user )
		);
		$userImpactLookupDurationInSeconds = microtime( true ) - $start;
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getTiming( 'computed_user_impact_lookup_expensive_seconds' )
			->observeSeconds( $userImpactLookupDurationInSeconds );

		return $expensiveUserImpact;
	}

	/**
	 * Run a SQL query to fetch edit data for the user.
	 *
	 * @param User $user
	 * @param int $flags
	 * @return EditData
	 * @throws \Exception
	 */
	private function getEditData( User $user, int $flags ): EditData {
		$db = DBAccessObjectUtils::getDBFromRecency( $this->connectionProvider, $flags );

		$queryBuilder = $db->newSelectQueryBuilder()
			->table( 'revision' )
			->join( 'page', null, 'rev_page = page_id' );

		$taskChangeTagNames = $this->taskTypeHandlerRegistry->getUniqueChangeTags();
		$additionalChangeTagNames = [
			ChangeTags::TAG_REVERTED,
		];

		$changeTagNames = array_merge( $taskChangeTagNames, $additionalChangeTagNames );
		$changeTagIds = [];
		$changeTagIdToName = [];
		foreach ( $changeTagNames as $changeTagName ) {
			try {
				// Presume the tag is not related to a task; set $taskTypeId to the task type ID
				// if it is.
				$taskTypeId = null;
				if ( in_array( $changeTagName, $taskChangeTagNames ) ) {
					$taskTypeHandlerId = $this->taskTypeHandlerRegistry->getTaskTypeHandlerIdByChangeTagName(
						$changeTagName
					);
					if ( !$taskTypeHandlerId ) {
						// In theory shouldn't be possible, given that the change tag names originate from the
						// task type handler registry. Adding this to make phan happy.
						throw new LogicException(
							"Unable to find task type handler ID for change tag \"$changeTagName\""
						);
					}
					$taskTypeHandler = $this->taskTypeHandlerRegistry->get( $taskTypeHandlerId );
					$taskTypeId = $taskTypeHandler->getTaskTypeIdByChangeTagName( $changeTagName );
				}

				$tagId = $this->changeTagDefStore->getId( $changeTagName );
				$changeTagIds[$tagId] = $taskTypeId;
				$changeTagIdToName[$tagId] = $changeTagName;
			} catch ( NameTableAccessException ) {
				// Some tags won't exist in test scenarios, and possibly in some small wikis where
				// no suggested edits have been done yet. We can safely ignore the exception,
				// it will mean that 'newcomerTaskEditCount' is 0 in the result.
			}
		}

		if ( $changeTagIds ) {
			$queryBuilder->leftJoin( 'change_tag', null, [
				'rev_id = ct_rev_id',
				'ct_tag_id' => array_keys( $changeTagIds ),
			] );
			$queryBuilder->field( 'ct_tag_id' );
		}

		$queryBuilder->fields( [ 'page_namespace', 'page_title', 'rev_timestamp' ] );
		$queryBuilder->where( [ 'rev_actor' => $user->getActorId() ] );
		$queryBuilder->where( $db->bitAnd( 'rev_deleted', RevisionRecord::DELETED_USER ) . ' = 0' );
		$queryBuilder->where( [ 'page_is_redirect' => 0 ] );
		// hopefully able to use the rev_actor_timestamp index for an efficient query
		$queryBuilder->orderBy( 'rev_timestamp', 'DESC' );
		$queryBuilder->limit( $this->config->get( 'GEUserImpactMaxEdits' ) );
		$queryBuilder->recency( $flags );
		$queryBuilder->caller( __METHOD__ );
		// T331264
		$queryBuilder->straightJoinOption();

		$editCountByNamespace = [];
		$editCountByDay = [];
		$revertedEditCount = 0;
		$taskTypes = [];
		// Only try to load task types if SE enabled
		if ( Util::isNewcomerTasksAvailable() ) {
			$taskTypes = $this->configurationLoader->getTaskTypes();
		} else {
			$this->logger->debug( 'Newcomer tasks are not available, no "editCountByTaskType" will be computed.' );
		}
		$editCountByTaskType = array_fill_keys( array_keys( $taskTypes ), 0 );
		$newcomerTaskEditCount = 0;
		$lastEditTimestamp = null;
		$editedArticles = [];

		foreach ( $queryBuilder->fetchResultSet() as $row ) {
			$linkTarget = new TitleValue( (int)$row->page_namespace, $row->page_title );
			$titleDbKey = $this->titleFormatter->getPrefixedDBkey( $linkTarget );
			$editTime = new MWTimestamp( $row->rev_timestamp );
			// Avoid using registered user timezone preference which can be used to de-anonymize users.
			// Use anonymous UserIdentity instead which will fall back to use the wiki's default
			// timezone and local tz offset.
			$editTime->offsetForUser( $this->userFactory->newAnonymous() );
			$day = $editTime->format( 'Ymd' );

			$editCountByNamespace[$row->page_namespace]
				= ( $editCountByNamespace[$row->page_namespace] ?? 0 ) + 1;
			$editCountByDay[$day] = ( $editCountByDay[$day] ?? 0 ) + 1;
			if ( $row->ct_tag_id ?? null ) {
				$taskTypeId = $changeTagIds[$row->ct_tag_id];
				if ( $taskTypeId ) {
					$newcomerTaskEditCount++;
					if ( !isset( $editCountByTaskType[$taskTypeId] ) ) {
						$editCountByTaskType[$taskTypeId] = 0;
					}
					$editCountByTaskType[$taskTypeId]++;
				}

				$changeTagName = $changeTagIdToName[$row->ct_tag_id];
				if ( $changeTagName === ChangeTags::TAG_REVERTED ) {
					$revertedEditCount++;
				}
			}
			$lastEditTimestamp ??= $row->rev_timestamp;
			// Computed values $editCountByNamespace, $editCountByDay, $newcomerTaskEditCount and $lastEditTimestamp
			// use data from all namespaces. Filter out non-article pages from the collection of returned articles
			// ($editedArticles) since they are not relevant for the user article list of recent edits.
			if ( (int)$row->page_namespace !== NS_MAIN ) {
				continue;
			}
			// We're iterating over the result set, newest edits to oldest edits in descending order. The same
			// article can have been edited multiple times. We'll stash the revision timestamp of the oldest
			// edit made by the user to the article; we will use that later to calculate the "start date"
			// for the impact of the user for a particular article, e.g. when making a pageviews tool URL
			// or choosing the date range for page view data to display for an article.
			$editedArticles[$titleDbKey]['oldestEdit'] = $row->rev_timestamp;
			$editedArticles[$titleDbKey]['newestEdit'] ??= $row->rev_timestamp;
		}

		$createdArticleCount = $this->getCreatedArticleCount( $db, $user );

		return new EditData(
			$editCountByNamespace,
			array_reverse( $this->updateToIso8601DateKeys( $editCountByDay ) ),
			$editCountByTaskType,
			$revertedEditCount,
			$newcomerTaskEditCount,
			$lastEditTimestamp,
			$editedArticles,
			$createdArticleCount
		);
	}

	/**
	 * Return the number of NS_MAIN pages created by a user.
	 *
	 * @param IReadableDatabase $db
	 * @param User $user
	 * @return int The count of pages created by the user. Note that the maximum value
	 *  for this is the value of GEUserImpactMaxEdits
	 */
	private function getCreatedArticleCount( IReadableDatabase $db, User $user ): int {
		$queryBuilder = $db->newSelectQueryBuilder();
		$queryBuilder->select( 'log_page' )
			->where(
				[
					'log_type' => 'create',
					'log_actor' => $user->getActorId(),
					// TODO: Eventually we might want to accept an Authority to count deleted
					// logs if the user has the right to view them.
					'log_deleted' => 0,
					// FIXME: This should have a clause for log_namespace => NS_MAIN,
					// but that would require creating a new index to include the namespace
				]
			)
			->from( 'logging' )
			// Optimizer doesn't pick the right index when a user has lots of log actions (T303089)
			->useIndex( 'log_actor_type_time' )
			->caller( __METHOD__ )
			// FIXME: Since we can't efficiently query on log_namespace, collect a larger sample of
			// log entries for the create action, in the hopes that we'll able to include within that
			// a roughly accurate count of NS_MAIN page creations
			->limit( max( 10_000, $this->config->get( 'GEUserImpactMaxEdits' ) ) )
			// Start with newer log entries first, as these are more relevant to the features
			// that consume this data
			->orderBy( 'log_timestamp', $queryBuilder::SORT_DESC );
		$result = $queryBuilder->fetchResultSet();
		$pageIds = [];
		foreach ( $result as $row ) {
			$pageIds[] = (int)$row->log_page;
		}
		if ( !count( $pageIds ) ) {
			return 0;
		}
		$queryBuilder = $db->newSelectQueryBuilder();
		$queryBuilder->from( 'page' )
			->where( [
				'page_namespace' => NS_MAIN,
				'page_is_redirect' => false,
				'page_id' => $pageIds,
			] )
			// No need for a LIMIT on this query, since we'll have at most $wgGEUserImpactMaxEdits
			// page IDs from above.
			->caller( __METHOD__ );
		return $queryBuilder->fetchRowCount();
	}

	/**
	 * @param User $user
	 * @param int $flags
	 * @return int Number of thanks received for the user ID
	 */
	private function getThanksReceivedCount( User $user, int $flags ): int {
		return $this->thanksQueryHelper
			? $this->thanksQueryHelper->getThanksReceivedCount(
				$user, $this->config->get( 'GEUserImpactMaxThanks' ), $flags )
			: 0;
	}

	/**
	 * @param UserIdentity $user
	 * @param int $flags
	 * @return int Number of thanks given for the user
	 */
	private function getThanksGivenCount( UserIdentity $user, int $flags ): int {
		return $this->thanksQueryHelper
			? $this->thanksQueryHelper->getThanksGivenCount(
				$user, $this->config->get( 'GEUserImpactMaxThanks' ), $flags )
			: 0;
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
	 *     - views: (int[]) daily article views, keyed by ISO 8601 date. Might be 0 for the last day
	 *       if it's still being processed.
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
				'dailyTotalViews' => [],
				'dailyArticleViews' => [],
			];
		}

		// $priorityTitles is a subset of $titles but putting it to the front makes sure the data
		// for those titles is fetched even if PageViewInfo cuts off the list of titles at some
		// point, which it is allowed to do.
		$allTitles = $priorityTitles + $titles;
		$allTitleObjects = [];

		foreach ( $allTitles as $title => $data ) {
			$allTitleObjects[$title] = [
				'title' => $this->titleFactory->newFromTextThrow( $title ),
				// rev_timestamp is in TS_MW format (e.g. 20210406200220), we only want
				// the first 8 characters for comparison with Ymd format date strings.
				'rev_timestamp' => substr( $data['oldestEdit'], 0, 8 ),
				'newestEdit' => $data['newestEdit'],
				'oldestEdit' => $data['oldestEdit'],
			];
		}
		if ( defined( 'MEDIAWIKI_JOB_RUNNER' ) || MW_ENTRY_POINT === 'cli' ) {
			$pageViewData = $this->getPageViewDataInJobContext( $allTitleObjects, $user, $days );
		} else {
			$pageViewData = $this->getPageViewDataInWebRequestContext( $allTitleObjects, $user, $days );
		}

		$dailyTotalViews = [];
		$dailyArticleViews = [];
		foreach ( $pageViewData as $title => $days ) {
			// Normalize titles as PageViewInfo does not define which title format it uses :(
			$title = str_replace( ' ', '_', $title );
			if ( !isset( $allTitleObjects[$title] ) ) {
				// There still seems to be a title normalization issue, see T400574 and T401005
				$this->logger->warning(
					'Title {title} not found in allTitleObjects',
					[
						'title' => $title,
						'allTitleObjectsKeys' => implode( ',', array_keys( $allTitleObjects ) ),
						'exception' => new \RuntimeException,
					],
				);
				continue;
			}
			$mwTitle = $this->titleFactory->newFromTextThrow( $title );
			$imageUrl = $this->getImage( $mwTitle );
			if ( $imageUrl ) {
				$dailyArticleViews[$title]['imageUrl'] = $imageUrl;
			}
			$firstEditDate = new DateTime( $allTitleObjects[$title]['rev_timestamp'] );
			$dailyArticleViews[$title]['firstEditDate'] = $firstEditDate->format( 'Y-m-d' );
			$dailyArticleViews[$title]['newestEdit'] = $allTitleObjects[$title]['newestEdit'];

			foreach ( $days as $day => $views ) {
				// NOTE: Do not insert the data if it is a zero due to JSON blob size issues (T351898)

				$todayTotalViews = ( ( $dailyTotalViews[$day] ?? 0 ) + $views );
				if ( $todayTotalViews > 0 ) {
					$dailyTotalViews[$day] = $todayTotalViews;
				}

				$todayArticleViews = ( $views ?? 0 );
				if ( $todayArticleViews > 0 ) {
					$dailyArticleViews[$title]['views'][$day] = $todayArticleViews;
				}
			}
		}

		return [
			'dailyTotalViews' => $dailyTotalViews,
			'dailyArticleViews' => $dailyArticleViews,
		];
	}

	private function getPageViewDataInJobContext( array $allTitleObjects, UserIdentity $user, int $days ): array {
		$pageViewData = [];
		$titleObjects = $allTitleObjects;
		$loopStartTime = microtime( true );
		while ( count( $titleObjects ) ) {
			$titleObjectsCount = count( $titleObjects );
			if ( count( $pageViewData ) > $this->config->get( 'GEUserImpactMaxArticlesToProcessForPageviews' ) ) {
				$this->logger->info(
					'Reached article count limit while fetching page view data for {count} titles for user {user}.',
					[ 'user' => $user->getName(), 'count' => count( $allTitleObjects ) ]
				);
				break;
			}
			if ( microtime( true ) - $loopStartTime > $this->config->get( 'GEUserImpactMaximumProcessTimeSeconds' ) ) {
				$this->logger->info(
					"Reached maximum process time while fetching page view data for {count} titles for user {user}",
					[ 'user' => $user->getName(), 'count' => count( $allTitleObjects ) ]
				);
				break;
			}
			$pageDataStatus = $this->pageViewService->getPageData(
				array_column( $titleObjects, 'title' ), $days
			);
			if ( !$pageDataStatus->isGood() ) {
				$this->logPageDataBadStatus( $pageDataStatus );
			}
			if ( $pageDataStatus->isOK() ) {
				$successful = array_filter( $pageDataStatus->success );
				$pageViewData += array_intersect_key( $pageDataStatus->getValue(), $successful );
			}
			$titleObjects = array_diff_key( $titleObjects, $pageViewData );
			if ( count( $titleObjects ) === $titleObjectsCount ) {
				// Received no new data. Abort to avoid a loop - errors are cached for a short time
				// so re-requesting them wouldn't help.
				return $pageViewData;
			}
		}
		return $pageViewData;
	}

	private function getPageViewDataInWebRequestContext(
		array $allTitleObjects, UserIdentity $user, int $days
	): array {
		$status = $this->pageViewService->getPageData( array_column( $allTitleObjects, 'title' ), $days );
		if ( !$status->isGood() ) {
			$this->logPageDataBadStatus( $status );
			if ( !$status->isOK() ) {
				return [];
			}
		} elseif ( $status->successCount < count( $allTitleObjects ) ) {
			$failedTitles = array_keys( array_diff_key( $allTitleObjects, $status->success ) );
			$this->logger->info( "Failed to get page view data for {count} titles for user {user}",
				[
					'user' => $user->getName(),
					'count' => count( $failedTitles ),
					'failedTitles' => substr( implode( ',', $failedTitles ), 0, 250 ),
				]
			);
		}
		return $status->getValue();
	}

	/**
	 * Don't log pvi-cached-error-title messages (T328945) but track it in statsd,
	 * and log any other message that occurs.
	 *
	 * @param StatusValue $status
	 * @return void
	 */
	private function logPageDataBadStatus( StatusValue $status ) {
		if ( $status->hasMessagesExcept( 'pvi-cached-error-title' ) ) {
			$this->logger->error(
				Status::wrap( $status )->getWikiText( false, false, 'en' )
			);
		} else {
			$this->statsFactory->withComponent( 'GrowthExperiments' )
				->getCounter( 'computed_user_impact_lookup_errors_total' )
				->setLabel( 'type', 'pvi_cached_error_title' )
				->incrementBy( $status->failCount );
		}
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
	 * Get image URL for a page
	 * Depends on the PageImages extension.
	 *
	 * @param Title $title
	 * @return ?string
	 */
	private function getImage( Title $title ): ?string {
		if ( !$this->pageImages ) {
			return null;
		}

		$imageFile = $this->pageImages->getImage( $title );
		if ( $imageFile ) {
			$options = [
				'width' => self::THUMBNAIL_SIZE,
			];
			$height = $imageFile->getHeight();
			if ( $height ) {
				$ratio = $imageFile->getWidth() / $height;
				if ( $ratio > 1 ) {
					// Avoid decimals in the width because it makes the thumb url construction fail
					$options[ 'width' ] = floor(
						self::THUMBNAIL_SIZE / $height * $imageFile->getWidth()
					);
				}
			} else {
				$this->logger->warning( 'PageImages returned 0-height image for page {title}, {fileName}', [
					'title' => $title->getDBkey(),
					'fileName' => $imageFile->getName(),
				] );
			}

			$thumb = $imageFile->transform( $options );
			if ( $thumb ) {
				return $thumb->getUrl() ?: null;
			}
		}

		return null;
	}

}
