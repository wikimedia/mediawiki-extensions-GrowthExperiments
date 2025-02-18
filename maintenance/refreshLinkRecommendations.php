<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Maintenance;

use CirrusSearch\Query\ArticleTopicFeature;
use Generator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationEvalStatus;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\AbstractDataConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\TopicDecorator;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\NullTaskTypeHandler;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Config\Config;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use RuntimeException;
use StatusValue;
use Wikimedia\Rdbms\DBReadOnlyError;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Update the growthexperiments_link_recommendations table to ensure there are enough
 * recommendations for all topics
 */
class RefreshLinkRecommendations extends Maintenance {

	private Config $growthConfig;
	private TitleFactory $titleFactory;
	private LinkBatchFactory $linkBatchFactory;
	private ConfigurationLoader $configurationLoader;
	private TaskSuggester $taskSuggester;
	private LinkRecommendationStore $linkRecommendationStore;
	private LinkRecommendationUpdater $linkRecommendationUpdater;
	private LinkRecommendationTaskType $recommendationTaskType;
	private User $searchUser;
	private array $metrics = [];
	private array $seen = [];

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

	public function checkRequiredExtensions(): void {
		// Hack: must be early enough for requireExtension to work but late enough for config
		// to be available.
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		if ( $growthServices->getGrowthConfig()->get( 'GELinkRecommendationsUseEventGate' ) ) {
			$this->requireExtension( 'EventBus' );
		}
		parent::checkRequiredExtensions();
	}

	public function execute(): void {
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
				new TaskSetFilters(
					[ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ],
					[ $oresTopic ]
				),
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

			$totalExistingSuggestionsCount = $suggestions->getTotalCount();
			$recommendationsNeeded = $this->recommendationTaskType->getMinimumTasksPerTopic()
				- $totalExistingSuggestionsCount;

			if ( $recommendationsNeeded <= 0 ) {
				$this->output( "    no new tasks needed, $totalExistingSuggestionsCount existing suggestions\n" );
				continue;
			}
			$this->output( "    $recommendationsNeeded new tasks needed\n" );
			foreach ( $this->findArticlesInTopic( $oresTopic ) as $titleBatch ) {
				$recommendationsFound = 0;
				foreach ( $titleBatch as $title ) {
					// TODO filter out protected pages. Needs to be batched. Or wait for T259346.
					$success = $this->processCandidate( $title, $force );
					if ( $success ) {
						$recommendationsFound++;
						$recommendationsNeeded--;
						if ( $recommendationsNeeded <= 0 ) {
							break 2;
						}
					}
				}
				$this->waitForReplication();
				// findArticlesInTopic() picks articles at random, so we need to abort the loop
				// at some point. Do it when no new tasks were generated from the current batch.
				if ( $recommendationsFound === 0 ) {
					break;
				}
			}
			$this->output( ( $recommendationsNeeded === 0 ) ? "    task pool filled\n"
				: "    topic exhausted, $recommendationsNeeded tasks still needed\n" );
		}

		$this->sendMetricsToStatslib();
	}

	private function sendMetricsToStatslib(): void {
		$services = $this->getServiceContainer();
		$statsFactory = $services->getStatsFactory()->withComponent( 'GrowthExperiments' );
		$counter = $statsFactory->getCounter( 'refreshLinks_total' );
		$wiki = WikiMap::getCurrentWikiId();
		foreach ( $this->metrics as $outcomeName => $outcomeCount ) {
			$counter->setLabel( 'wiki', $wiki );
			$counter->setLabel( 'outcome', $outcomeName );
			$counter->incrementBy( $outcomeCount );
			// TODO: print summary with --verbose option?
			$this->verboseLog( "    $outcomeName: $outcomeCount\n" );
		}
	}

	protected function initGrowthConfig(): void {
		// Needs to be separate from initServices/initConfig as checking whether the script
		// should run on a given wiki relies on this, but initServices/initConfig will break
		// on some wikis where the script is not supposed to run and the task configuration
		// is missing.
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->growthConfig = $growthServices->getGrowthConfig();
	}

	protected function initServices(): void {
		// Extend the task type configuration with a custom "candidate" task type, which
		// finds articles which do not have link recommendations.
		$linkRecommendationCandidateTaskType = NullTaskTypeHandler::getNullTaskType(
			'_nolinkrecommendations', '-hasrecommendation:link' );

		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$newcomerTaskConfigurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		if ( $newcomerTaskConfigurationLoader instanceof AbstractDataConfigurationLoader ) {
			// Pretend link-recommendation is enabled (T371316)
			// Task suggester cannot be adapted to query disabled task types, because it is also
			// used in Homepage (where the disabled flag has to be honored).
			$newcomerTaskConfigurationLoader->enableTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}
		$this->configurationLoader = new TopicDecorator(
			$newcomerTaskConfigurationLoader,
			true,
			[ $linkRecommendationCandidateTaskType ]
		);
		$this->titleFactory = $services->getTitleFactory();
		$this->linkBatchFactory = $services->getLinkBatchFactory();
		$this->taskSuggester = $growthServices->getTaskSuggesterFactory()->create( $this->configurationLoader );
		$this->linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$this->linkRecommendationUpdater = $growthServices->getLinkRecommendationUpdater();
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
	private function findArticlesInTopic( string $oresTopic ) {
		$batchSize = $this->getBatchSize();
		do {
			$this->output( "    fetching $batchSize tasks...\n" );
			$candidates = $this->taskSuggester->suggest(
				$this->searchUser,
				new TaskSetFilters(
					[ '_nolinkrecommendations' ],
					[ $oresTopic ]
				),
				$batchSize,
				null,
				[ 'debug' => true ]
			);
			if ( $candidates instanceof StatusValue ) {
				// FIXME exiting will make the cronjob unreliable. Not exiting might result
				//  in an infinite error loop. Neither looks like a great option.
				throw new RuntimeException( 'Search error: '
					. Status::wrap( $candidates )->getWikiText( false, false, 'en' ) );
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
	 * @return bool Whether a new task was generated.
	 */
	private function processCandidate( Title $title, bool $force = false ): bool {
		$this->verboseLog( "    checking candidate " . $title->getPrefixedDBkey() . "... " );
		try {
			$status = $this->linkRecommendationUpdater->processCandidate( $title, $force );
			$this->trackProcessingOutcome( $title, $status );
			if ( $status->isOK() ) {
				$this->verboseLog( "success, updating index\n" );
				return true;
			} else {
				$error = Status::wrap( $status )->getWikiText( false, false, 'en' );
				$this->verboseLog( "$error\n" );
			}
		} catch ( DBReadOnlyError $e ) {
			// This is a long-running script, read-only state can change in the middle.
			// It's run frequently so just do the easy thing and abort.
			$this->fatalError( 'DB is readonly, aborting' );
		} catch ( WikiConfigException $e ) {
			// Link recommendations are not configured correctly.
			$this->fatalError( $e->getMessage() );
		}
		return false;
	}

	/**
	 * The metrics will be sent to statslib when the script is done in
	 * the private ::sendMetricsToStatslib method.
	 */
	private function trackProcessingOutcome( Title $title, StatusValue $candidateStatus ): void {
		if ( isset( $this->seen[$title->getPrefixedDBkey()] ) ) {
			// don't double-count
			return;
		}
		$this->seen[$title->getPrefixedDBkey()] = true;
		if ( $candidateStatus->isGood() ) {
			$this->metrics['success']++;
			return;
		}
		if ( $candidateStatus instanceof LinkRecommendationEvalStatus ) {
			$cause = $candidateStatus->getNotGoodCause();
			$this->metrics[$cause]++;
			return;
		}

		$this->metrics[LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_OTHER]++;
	}

	private function verboseLog( string $message ): void {
		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( $message );
		}
	}

}

$maintClass = RefreshLinkRecommendations::class;
require_once RUN_MAINTENANCE_IF_MAIN;
