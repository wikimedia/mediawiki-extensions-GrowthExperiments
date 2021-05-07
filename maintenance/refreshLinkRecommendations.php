<?php

namespace GrowthExperiments\Maintenance;

use ChangeTags;
use CirrusSearch\Query\ArticleTopicFeature;
use Config;
use Generator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationLink;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\SearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\OresTopicTrait;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\NullTaskTypeHandler;
use IDBAccessObject;
use Maintenance;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\NameTableStore;
use Message;
use MWTimestamp;
use PageProps;
use RuntimeException;
use SearchEngineFactory;
use Status;
use StatusValue;
use Title;
use TitleFactory;
use User;
use WikiMap;
use Wikimedia\Rdbms\DBReadOnlyError;
use WikitextContent;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';
require_once dirname( __DIR__ ) . '/includes/NewcomerTasks/OresTopicTrait.php';

/**
 * Update the growthexperiments_link_recommendations table to ensure there are enough
 * recommendations for all topics
 */
class RefreshLinkRecommendations extends Maintenance {

	use OresTopicTrait;

	/** @var Config */
	private $growthConfig;

	/** @var SearchEngineFactory */
	protected $searchEngineFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var NameTableStore */
	private $changeDefNameTableStore;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var TaskSuggester */
	private $taskSuggester;

	/** @var LinkRecommendationProvider */
	protected $linkRecommendationProviderUncached;

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/** @var LinkRecommendationHelper */
	private $linkRecommendationHelper;

	/** @var SearchIndexUpdater */
	private $searchIndexUpdater;

	/** @var LinkRecommendationTaskType */
	private $recommendationTaskType;

	/** @var User */
	private $searchUser;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->requireExtension( 'CirrusSearch' );

		$this->addDescription( 'Update the growthexperiments_link_recommendations table to ensure '
			. 'there are enough recommendations for all topics.' );
		$this->addOption( 'topic', 'Only update articles in the given ORES topic.', false, true );
		$this->addOption( 'page', 'Only update a specific page.', false, true );
		$this->addOption( 'force', 'Generate recommendations even if they fail quality criteria.' );
		$this->addOption( 'verbose', 'Show debug output.' );
		$this->setBatchSize( 500 );
	}

	public function checkRequiredExtensions() {
		// Hack: must be early enough for requireExtension to work but late enough for config
		// to be available.
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		if ( $growthServices->getConfig()->get( 'GELinkRecommendationsUseEventGate' ) ) {
			$this->requireExtension( 'EventBus' );
		}
		parent::checkRequiredExtensions();
	}

	public function execute() {
		$this->initGrowthConfig();
		if ( !$this->growthConfig->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
			$this->output( "Disabled\n" );
			return;
		} elseif ( $this->growthConfig->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$this->output( "Local tasks disabled\n" );
			return;
		}
		$this->initServices();
		$this->initConfig();
		$lockName = 'GrowthExperiments-RefreshLinkRecommendations-' . WikiMap::getCurrentWikiId();
		if ( !$this->linkRecommendationStore->getDB( DB_PRIMARY )->lock( $lockName, __METHOD__, 0 ) ) {
			$this->output( "Previous invocation of the script is still running\n" );
			return;
		}

		$force = $this->hasOption( 'force' );
		$this->output( "Refreshing link recommendations...\n" );

		$pageName = $this->getOption( 'page' );
		if ( $pageName ) {
			$title = $this->titleFactory->newFromText( $pageName );
			if ( $title ) {
				$this->processCandidate( $title, $force );
			} else {
				$this->fatalError( 'Invalid title: ' . $pageName );
			}
			return;
		}

		$oresTopics = $this->getOresTopics();
		foreach ( $oresTopics as $oresTopic ) {
			$this->output( "  processing topic $oresTopic...\n" );
			$suggestions = $this->taskSuggester->suggest(
				$this->searchUser,
				[ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ],
				[ $oresTopic ],
				1,
				0,
				// Enabling the debug flag is relatively harmless, and disables all caching,
				// which we need here. useCache would prevent reading the cache, but would
				// still write it, which would be just a waste of space.
				[ 'debug' => true ]
			);

			// TaskSuggester::suggest() only returns StatusValue when there's an error.
			if ( $suggestions instanceof StatusValue ) {
				$this->error( Status::wrap( $suggestions )->getWikiText( false, false, 'en' ) );
				continue;
			}

			$recommendationsNeeded = $this->recommendationTaskType->getMinimumTasksPerTopic()
				- $suggestions->getTotalCount();

			// TODO can we reuse actual Suggester / SearchStrategy / etc code here?
			if ( $recommendationsNeeded <= 0 ) {
				$this->output( "    no new tasks needed\n" );
				continue;
			}
			$this->output( "    $recommendationsNeeded new tasks needed\n" );
			foreach ( $this->findArticlesInTopic( $oresTopic ) as $titleBatch ) {
				$recommendationsFound = 0;
				foreach ( $titleBatch as $title ) {
					// TODO filter out protected pages. Needs to be batched. Or wait for T259346.
					$this->processCandidate( $title, $force );
					$recommendationsFound++;
					$recommendationsNeeded--;
					if ( $recommendationsNeeded <= 0 ) {
						break 2;
					}
				}
				$this->waitForReplication();
				if ( $recommendationsFound === 0 ) {
					break;
				}
			}
			$this->output( ( $recommendationsNeeded === 0 ) ? "    task pool filled\n"
				: "    topic exhausted, $recommendationsNeeded tasks still needed\n" );
		}
	}

	protected function initGrowthConfig(): void {
		// Needs to be separate from initServices/initConfig as checking whether the script
		// should run on a given wiki relies on this, but initServices/initConfig will break
		// on some wikis where the script is not supposed to run and the task configuration
		// is missing.
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->growthConfig = $growthServices->getConfig();
	}

	protected function initServices(): void {
		// Extend the task type configuration with a custom "candidate" task type, which
		// finds articles which do not have link recommendations.
		$linkRecommendationCandidateTaskType = NullTaskTypeHandler::getNullTaskType(
			'_nolinkrecommendations', '-hasrecommendation:link' );
		$this->replaceConfigurationLoader( true, [ $linkRecommendationCandidateTaskType ] );

		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->searchEngineFactory = $services->getSearchEngineFactory();
		$this->titleFactory = $services->getTitleFactory();
		$this->linkBatchFactory = $services->getLinkBatchFactory();
		$this->revisionStore = $services->getRevisionStore();
		$this->changeDefNameTableStore = $services->getNameTableStoreFactory()->getChangeTagDef();
		$this->configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$this->taskSuggester = $growthServices->getTaskSuggesterFactory()->create();
		$this->linkRecommendationProviderUncached =
			$services->get( 'GrowthExperimentsLinkRecommendationProviderUncached' );
		$this->linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$this->linkRecommendationHelper = $growthServices->getLinkRecommendationHelper();
		$this->searchIndexUpdater = $growthServices->getSearchIndexUpdater();
	}

	protected function initConfig(): void {
		$taskTypes = $this->configurationLoader->getTaskTypes();
		$taskType = $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
		if ( !$taskType || !$taskType instanceof LinkRecommendationTaskType ) {
			$this->fatalError( sprintf( "'%s' is not a link recommendation task type",
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ) );
		} else {
			$this->recommendationTaskType = $taskType;
		}
		$this->searchUser = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
	}

	/**
	 * @return string[]
	 */
	private function getOresTopics(): array {
		$topic = $this->getOption( 'topic' );
		$oresTopics = array_keys( ArticleTopicFeature::TERMS_TO_LABELS );
		if ( $topic ) {
			$oresTopics = array_intersect( $oresTopics, [ $topic ] );
			if ( !$oresTopics ) {
				$this->fatalError( "invalid topic $topic" );
			}
		}
		return $oresTopics;
	}

	/**
	 * @param string $oresTopic
	 * @return Generator<Title[]>
	 */
	private function findArticlesInTopic( $oresTopic ) {
		$batchSize = $this->getBatchSize();
		do {
			$this->output( "    fetching $batchSize tasks...\n" );
			$candidates = $this->taskSuggester->suggest(
				$this->searchUser,
				[ '_nolinkrecommendations' ],
				[ $oresTopic ],
				$batchSize,
				null,
				[ 'debug' => true ]
			);
			if ( $candidates instanceof StatusValue ) {
				// FIXME exiting will make the cronjob unreliable. Not exiting might result
				//  in an infinite error loop. Neither looks like a great option.
				throw new RuntimeException( 'Search error: '
					. Status::wrap( $candidates )->getWikiText( null, null, 'en' ) );
			}

			$linkTargets = $titles = [];
			foreach ( $candidates as $candidate ) {
				$linkTargets[] = $candidate->getTitle();
			}
			$this->linkBatchFactory->newLinkBatch( $linkTargets )->execute();
			foreach ( $linkTargets as $linkTarget ) {
				$titles[] = $this->titleFactory->newFromLinkTarget( $linkTarget );
			}
			yield $titles;
		} while ( $candidates->count() );
	}

	/**
	 * Evaluate a task candidate and potentially generate the task.
	 * @param Title $title
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 */
	private function processCandidate( Title $title, bool $force = false ) {
		$this->verboseLog( "    checking candidate " . $title->getPrefixedDBkey() . "... " );
		$lastRevision = $this->revisionStore->getRevisionByTitle( $title );
		if ( !$this->evaluateTitle( $title, $lastRevision, $force ) ) {
			return;
		}

		// Prevent infinite loop. Cirrus updates are not realtime so pages we have
		// just created recommendations for will be included again in the next batch.
		// Skip them to ensure $recommendationsFound is only nonzero then we have
		// actually added a new recommendation.
		// FIXME there is probably a better way to do this via search offsets.
		if ( $this->linkRecommendationStore->getByRevId( $lastRevision->getId(),
			IDBAccessObject::READ_LATEST )
		) {
			$this->verboseLog( "link recommendation already stored\n" );
			return;
		}

		$recommendation = $this->linkRecommendationProviderUncached->get( $title,
			$this->recommendationTaskType );
		if ( !$this->evaluateRecommendation( $recommendation, $lastRevision, $force ) ) {
			return;
		}

		$this->verboseLog( "success, updating index\n" );
		try {
			// If the script gets interrupted, uncommitted DB writes get discarded, while
			// updateCirrusSearchIndex() is immediate. Minimize the likelyhood of the DB
			// and the search index getting out of sync by wrapping each insert into a
			// separate transaction. Use an explicit begin here to avoid Database::commit
			// complaining about "no transaction to commit". That should never happen as
			// the insert() call does an implicit begin, but it does occur somehow.
			$this->linkRecommendationStore->getDB( DB_PRIMARY )->begin( __METHOD__ );
			$this->linkRecommendationStore->insert( $recommendation );
			$this->linkRecommendationStore->getDB( DB_PRIMARY )->commit( __METHOD__ );
			$this->updateCirrusSearchIndex( $lastRevision );
		} catch ( DBReadOnlyError $e ) {
			// This is a long-running script, read-only state can change in the middle.
			// It's run frequently so just do the easy thing and abort.
			$this->fatalError( 'DB is readonly, aborting' );
		}
	}

	/**
	 * Check all conditions which are not related to the recommendation.
	 * @param Title $title
	 * @param RevisionRecord|null $revision
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return bool
	 */
	private function evaluateTitle( Title $title, ?RevisionRecord $revision, bool $force ): bool {
		// FIXME ideally most of this should be moved inside the search query

		// 1. the revision must exist and the mwaddlink service must be able to interpret it.
		if ( $revision === null ) {
			// Maybe the article has just been deleted and the search index is behind?
			$this->verboseLog( "page not found\n" );
			return false;
		}
		$content = $revision->getContent( SlotRecord::MAIN );
		if ( !$content || !$content instanceof WikitextContent ) {
			$this->verboseLog( "content not found\n" );
			return false;
		}

		if ( $force ) {
			return true;
		}

		// 2. the article must match size conditions.
		$wordCount = preg_match_all( '/\w+/', $content->getText() );
		if ( $wordCount < $this->recommendationTaskType->getMinimumWordCount() ) {
			$this->verboseLog( "word count too small ($wordCount)\n" );
			return false;
		} elseif ( $wordCount > $this->recommendationTaskType->getMaximumWordCount() ) {
			$this->verboseLog( "word count too large ($wordCount)\n" );
			return false;
		}

		// 3. exclude articles which have been edited very recently.
		$revisionTime = MWTimestamp::convert( TS_UNIX, $revision->getTimestamp() );
		if ( time() - $revisionTime < $this->recommendationTaskType->getMinimumTimeSinceLastEdit() ) {
			$this->verboseLog( "minimum time since last edit did not pass\n" );
			return false;
		}

		// 4. exclude disambiguation pages.
		if ( PageProps::getInstance()->getProperties( $title, 'disambiguation' ) ) {
			$this->verboseLog( "disambiguation page\n" );
			return false;
		}

		// 5. exclude pages where the last edit is a link recommendation edit or its revert.
		$db = $this->getDB( DB_REPLICA );
		$tags = ChangeTags::getTagsWithData( $db, null, $revision->getId() );
		if ( array_key_exists( LinkRecommendationTaskTypeHandler::CHANGE_TAG, $tags ) ) {
			$this->verboseLog( "last edit is a link recommendation\n" );
			return false;
		}
		$revertTags = array_intersect( ChangeTags::REVERT_TAGS, array_keys( $tags ) );
		if ( $revertTags ) {
			$linkRecommendationChangeTagId = $this->changeDefNameTableStore
				->acquireId( LinkRecommendationTaskTypeHandler::CHANGE_TAG );
			$tagData = json_decode( $tags[reset( $revertTags )], true );
			/** @var array $tagData */'@phan-var array $tagData';
			$revertedAddLinkEditCount = $db->selectRowCount(
				[ 'revision', 'change_tag' ],
				'1',
				[
					'rev_id = ct_rev_id',
					'rev_page' => $title->getArticleID(),
					'rev_id <=' . (int)$tagData['newestRevertedRevId'],
					'rev_id >=' . (int)$tagData['oldestRevertedRevId'],
					'ct_tag_id' => $linkRecommendationChangeTagId,
				],
				__METHOD__
			);
			if ( $revertedAddLinkEditCount > 0 ) {
				$this->verboseLog( "last edit reverts a link recommendation edit\n" );
				return false;
			}
		}
		return true;
	}

	/**
	 * @param LinkRecommendation|StatusValue $recommendation
	 * @param RevisionRecord $revision
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return bool
	 */
	private function evaluateRecommendation( $recommendation, RevisionRecord $revision, bool $force ): bool {
		if ( !( $recommendation instanceof LinkRecommendation ) ) {
			$this->verboseLog( "fetching recommendation failed\n" );
			$this->error( Status::wrap( $recommendation )->getWikiText( false, false, 'en' ) );
			return false;
		}
		if ( $recommendation->getRevisionId() !== $revision->getId() ) {
			// Some kind of race condition? Generating another task is easy so just discard this.
			$this->verboseLog( "revision ID mismatch\n" );
			return false;
		}
		// We could check here for more race conditions, ie. whether the revision in the
		// recommendation matches the live revision. But there are plenty of other ways for race
		// conditions to happen, so we'll have to deal with them on the client side anyway. No
		// point in getting a primary database connection just for that.

		$goodLinks = array_filter( $recommendation->getLinks(), function ( LinkRecommendationLink $link ) {
			return $link->getScore() >= $this->recommendationTaskType->getMinimumLinkScore();
		} );
		$recommendation = new LinkRecommendation(
			$recommendation->getTitle(),
			$recommendation->getPageId(),
			$recommendation->getRevisionId(),
			$goodLinks,
			$recommendation->getMetadata()
		);
		$recommendation = $this->linkRecommendationHelper->pruneLinkRecommendation( $recommendation );
		$prunedLinkCount = $recommendation ? count( $recommendation->getLinks() ) : 0;
		if ( $prunedLinkCount === 0
			 || !$force && $prunedLinkCount < $this->recommendationTaskType->getMinimumLinksPerTask()
		) {
			$this->verboseLog( "number of good links too small (" . $prunedLinkCount . ")\n" );
			return false;
		}

		return true;
	}

	private function updateCirrusSearchIndex( RevisionRecord $revision ): void {
		$status = $this->searchIndexUpdater->update( $revision );
		if ( !$status->isOK() ) {
			$errors = array_map( static function ( $error ) {
				$message = $error['params'];
				array_unshift( $message, $error['message'] );
				return Message::newFromSpecifier( $message );
			}, $status->getErrorsByType( 'error' ) );
			$this->error( "  Could not send search index update:\n    "
				. implode( "    \n", $errors ) );
		}
	}

	private function verboseLog( string $message ): void {
		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( $message );
		}
	}

}

$maintClass = RefreshLinkRecommendations::class;
require_once RUN_MAINTENANCE_IF_MAIN;
