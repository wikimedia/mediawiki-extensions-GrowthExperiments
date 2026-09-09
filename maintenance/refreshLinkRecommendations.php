<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationEvalStatus;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
use StatusValue;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\LockManager\ILockManager;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Stats\StatsFactory;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Update the growthexperiments_link_recommendations table. The script iterates through all
 * articles of the wiki and checks each article for link recommendations.
 */
class RefreshLinkRecommendations extends Maintenance {

	private Config $growthConfig;
	private StatusFormatter $statusFormatter;
	private TitleFactory $titleFactory;
	private ILockManager $lockManager;
	private LinkRecommendationUpdater $linkRecommendationUpdater;
	private StatsFactory $statsFactory;
	private array $metrics = [];
	private array $seen = [];

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->requireExtension( 'CirrusSearch' );

		$this->addDescription( 'Update the growthexperiments_link_recommendations table. The script '
			. 'iterates through all articles of the wiki and checks them for link recommendations.' );
		$this->addOption( 'page', 'Only update a specific page.', false, true );
		$this->addOption( 'force', 'Generate recommendations even if they fail quality criteria.' );
		$this->addOption(
			'limit',
			'Approximate number of pages to process overall. Default: 5000',
			false,
			true,
		);
		$this->addOption(
			'setLastPageIdInStash',
			'Only set the lastPageId in the stash. The next run starts at this page ID.',
			false,
			true,
		);
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
		$this->initServices();
		if ( !$this->growthConfig->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
			$this->output( "Disabled\n" );
			return;
		} elseif ( $this->growthConfig->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$this->output( "Local tasks disabled\n" );
			return;
		}

		$lock = $this->lockManager->scopedLock( 'GrowthExperiments-RefreshLinkRecommendations' );
		if ( !$lock ) {
			$this->output( "Previous invocation of the script is still running\n" );
			return;
		}

		$setLastPageIdInStash = $this->getOption( 'setLastPageIdInStash', null );
		if ( $setLastPageIdInStash !== null ) {
			$this->setLastPageIdInStash( (int)$setLastPageIdInStash );
			return;
		}

		$force = $this->hasOption( 'force' );
		$this->output( "Refreshing link recommendations...\n" );

		$pageName = $this->getOption( 'page' );
		if ( $pageName ) {
			$title = $this->titleFactory->newFromText( $pageName );
			if ( $title ) {
				$this->processCandidate( $title->toPageIdentity(), $force );
			} else {
				$this->fatalError( 'Invalid title: ' . $pageName );
			}
			return;
		}

		$sessionDurationCounter = $this->statsFactory->getCounter( 'refreshLinks_session_seconds_total' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			// Only one refresh mode is left. Keep the label, because dashboards use it.
			->setLabel( 'type', 'by_iterating_pages' );
		$startNanoSeconds = hrtime( true );
		$this->refreshByIteratingThroughAllPages( $force );
		$durationSeconds = ceil( ( hrtime( true ) - $startNanoSeconds ) / 1e9 );
		$sessionDurationCounter->incrementBy( $durationSeconds );

		$this->sendMetricsToStatslib();
		$this->verboseLog( "    duration: $durationSeconds seconds\n" );
	}

	private function setLastPageIdInStash( int $lastPageId ): void {
		$this->output( 'Setting lastPageId in stash: ' . $lastPageId . "\n" );
		$services = $this->getServiceContainer();
		$mainStash = $services->getMainObjectStash();
		// TODO: Migrate to a proper keygroup. May need consideration for existing data.
		$lastPageIdKey = $mainStash->makeKey(
			'GrowthExperiments',
			'RefreshLinkRecommendations',
			'lastPageId'
		);
		$success = $mainStash->set( $lastPageIdKey, $lastPageId, ExpirationAwareness::TTL_INDEFINITE );
		if ( !$success ) {
			$this->output( 'Failed to set lastPageId in stash!' . "\n" );
		} else {
			$this->output( 'Successfully set lastPageId in stash' . "\n" );
		}
		$this->output( 'Exiting.' . "\n" );
	}

	private function refreshByIteratingThroughAllPages( bool $force ): void {
		$batchSize = $this->getBatchSize();

		$limit = $this->getOption( 'limit', 5000 );

		$services = $this->getServiceContainer();

		$mainStash = $services->getMainObjectStash();
		$lastPageIdKey = $mainStash->makeKey(
			'GrowthExperiments',
			'RefreshLinkRecommendations',
			'lastPageId'
		);
		$this->verboseLog( 'Getting last used page-id from stash with: ' . $lastPageIdKey . "\n" );
		$lastPageId = $mainStash->get( $lastPageIdKey ) ?: 0;
		$this->verboseLog( 'Iterating through pages in the wiki, starting at page-id ' . $lastPageId . "\n" );

		$pageStore = $services->getPageStore();

		$pageRowsProcessed = 0;

		while ( $pageRowsProcessed < $limit ) {
			$pageRecordsIterator = $pageStore->newSelectQueryBuilder()
				->whereNamespace( 0 )
				->andWhere( 'page_is_redirect = 0' )
				->andWhere( 'page_id > ' . $lastPageId )
				->orderByPageId()
				->limit( $batchSize )
				->caller( __METHOD__ )
				->fetchPageRecords();

			$this->beginTransactionRound( __METHOD__ );
			foreach ( $pageRecordsIterator as $pageRecord ) {
				$pageRowsProcessed++;
				$title = $this->titleFactory->newFromID( $pageRecord->getId() );
				if ( $title ) {
					$this->processCandidate( $title->toPageIdentity(), $force );
				}
			}
			$this->commitTransactionRound( __METHOD__ );

			if ( !isset( $pageRecord ) ) {
				$this->verboseLog( 'Finished processing all pages in the wiki. Resetting lastPageId.' . "\n" );
				$mainStash->delete( $lastPageIdKey );
				return;
			}

			$lastPageId = $pageRecord->getId();
			unset( $pageRecord );
		}
		$this->verboseLog( 'Limit reached, handover-point is at page-id ' . $lastPageId . "\n" );
		$mainStash->set( $lastPageIdKey, $lastPageId, ExpirationAwareness::TTL_INDEFINITE );
	}

	private function sendMetricsToStatslib(): void {
		$counter = $this->statsFactory->getCounter( 'refreshLinks_total' );
		$wiki = WikiMap::getCurrentWikiId();
		$this->verboseLog( "Outcomes:\n" );
		foreach ( $this->metrics as $outcomeName => $outcomeCount ) {
			$counter->setLabel( 'wiki', $wiki );
			$counter->setLabel( 'outcome', $outcomeName );
			$counter->incrementBy( $outcomeCount );
			$this->verboseLog( "    $outcomeName: $outcomeCount\n" );
		}
	}

	protected function initServices(): void {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->growthConfig = $growthServices->getGrowthConfig();
		$this->statusFormatter = $services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() );
		$this->titleFactory = $services->getTitleFactory();
		$this->lockManager = $services->getLockManager();
		$this->linkRecommendationUpdater = $growthServices->getLinkRecommendationUpdater();
		$this->statsFactory = $services->getStatsFactory()->withComponent( 'GrowthExperiments' );
	}

	/**
	 * Evaluate a task candidate and potentially generate the task.
	 * @param ProperPageIdentity $pageIdentity
	 * @param bool $force Ignore all failed conditions that can be safely ignored.
	 * @return bool Whether a new task was generated.
	 */
	private function processCandidate( ProperPageIdentity $pageIdentity, bool $force = false ): bool {
		$this->verboseLog( "    checking candidate " . $pageIdentity->__toString() . "... " );
		try {
			$status = $this->linkRecommendationUpdater->processCandidate( $pageIdentity, $force );
			$this->trackProcessingOutcome( $pageIdentity, $status );
			if ( $status->isOK() ) {
				$this->verboseLog( "success, updating index\n" );
				return true;
			} else {
				$error = $this->statusFormatter->getWikiText( $status, [ 'lang' => 'en' ] );
				if ( $this->hasOption( 'verbose' ) ) {
					// The "checking candidate" entry was printed, print the status itself too
					$this->error( $error );
				} else {
					$this->error(
						'    while processing ' . $pageIdentity->__toString()
						. ', an error occured: ' . $error
					);
				}
			}
		} catch ( DBReadOnlyError ) {
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
	private function trackProcessingOutcome( PageIdentity $page, StatusValue $candidateStatus ): void {
		if ( isset( $this->seen[$page->__toString()] ) ) {
			// don't double-count
			return;
		}
		$this->seen[$page->__toString()] = true;

		if ( $candidateStatus->isGood() ) {
			$metricKey = 'success';
		} elseif ( $candidateStatus instanceof LinkRecommendationEvalStatus ) {
			$metricKey = $candidateStatus->getNotGoodCause();
		} else {
			$metricKey = LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_OTHER;
		}

		$this->metrics[$metricKey] ??= 0;
		$this->metrics[$metricKey]++;
	}

	private function verboseLog( string $message ): void {
		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( $message );
		}
	}

}

// @codeCoverageIgnoreStart
$maintClass = RefreshLinkRecommendations::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
