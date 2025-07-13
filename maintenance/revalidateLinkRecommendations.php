<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationLink;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\NullLinkRecommendation;
use GrowthExperiments\WikiConfigException;
use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use UnexpectedValueException;
use Wikimedia\Rdbms\DBReadOnlyError;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

/**
 * Iterate through the growthexperiments_link_recommendations table and regenerate the ones which
 * do not match the specified criteria. If a valid task cannot be generated, the existing task will
 * be discarded.
 * This is mainly meant for updating tasks after the recommendation algorithm changes.
 */
class RevalidateLinkRecommendations extends Maintenance {

	private TitleFactory $titleFactory;
	private LinkRecommendationStore $linkRecommendationStore;
	private LinkRecommendationHelper $linkRecommendationHelper;
	private LinkRecommendationUpdater $linkRecommendationUpdater;
	private Config $growthConfig;

	/** @var string[] */
	private array $allowedChecksums = [];

	private ?int $olderThanTimestamp = null;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'Iterate through the growthexperiments_link_recommendations table and '
			. 'regenerate the ones which do not match the specified criteria. If a valid task cannot be '
			. 'generated, the existing task will be discarded.' );
		$this->addOption( 'fromPageId', 'Start iterating upwards from this page ID.', false, true );
		$this->addOption( 'all', 'Regenerate all tasks.' );
		$this->addOption( 'exceptDatasetChecksums', 'Regenerate a task unless its '
			. 'model checksum appears in the given file (one checksum per line)', false, true );
		$this->addOption( 'olderThan', 'Regenerate a task which was generated '
			. 'before this date', false, true );
		$this->addOption( 'scoreLessThan', 'Regenerate a task when any suggested link has '
			. 'a lower score than this one.', false, true );
		$this->addOption( 'limit', 'Limit the number of changes.', false, true );
		$this->addOption( 'force', 'Store the new recommendation even if it fails quality criteria.' );

		$this->addOption(
			'deleteNullRecommendations',
			// phpcs:ignore Generic.Files.LineLength.TooLong
			'Delete the data about which pages have no valid recommendations. Use when the configuration has become more permissive.'
		);
		$this->addOption( 'dry-run', 'Do not actually make any changes.' );
		$this->addOption( 'verbose', 'Show debug output.' );
		$this->setBatchSize( 500 );
	}

	public function checkRequiredExtensions(): void {
		// Hack: must be early enough for requireExtension to work but late enough for config
		// to be available.
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		if ( $growthServices->getGrowthConfig()->get( 'GELinkRecommendationsUseEventGate' ) ) {
			$this->requireExtension( 'EventBus' );
		} else {
			$this->requireExtension( 'CirrusSearch' );
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

		$isDeleteNullRecommendationsEnabled = $this->getOption( 'deleteNullRecommendations' );
		$isDryRun = (bool)$this->getOption( 'dry-run' );

		$this->output( "Revalidating link recommendations:\n" );

		$replaced = $discarded = $nullRecommendationsDeleted = 0;
		$fromPageId = (int)$this->getOption( 'fromPageId', 0 );
		while ( $fromPageId !== false ) {
			$this->output( "  fetching task batch starting with page $fromPageId\n" );
			if ( $isDeleteNullRecommendationsEnabled ) {
				$linkRecommendations = $this->linkRecommendationStore->getAllRecommendationEntries(
					$this->getBatchSize(),
					$fromPageId,
				);
			} else {
				$linkRecommendations = $this->linkRecommendationStore->getAllExistingRecommendations(
					$this->getBatchSize(),
					$fromPageId
				);
			}
			foreach ( $linkRecommendations as $linkRecommendation ) {
				if ( $replaced + $discarded + $nullRecommendationsDeleted == $this->getOption( 'limit', -1 ) ) {
					$this->verboseLog( "Limit reached, aborting.\n" );
					break 2;
				}
				if ( $linkRecommendation instanceof NullLinkRecommendation ) {
					if ( !$isDeleteNullRecommendationsEnabled ) {
						throw new LogicException(
							'NullLinkRecommendation encountered but deleteNullRecommendations is not enabled'
						);
					}
					$this->handleNullRecommendation( $linkRecommendation, $isDryRun );
					$nullRecommendationsDeleted += 1;
					continue;
				}
				if ( !$this->validateRecommendation( $linkRecommendation ) ) {
					$this->verboseLog( '  ' . $this->getTitle( $linkRecommendation )->getPrefixedText()
						. ' is outdated, regenerating... ' );
					if ( $isDryRun ) {
						$replaced++;
						$this->verboseLog( "(dry-run)\n" );
					} else {
						$status = $this->regenerateRecommendation( $linkRecommendation );
						$this->verboseLog( $status->isOK() ? "success\n"
							: $status->getWikiText( false, false, 'en' ) . "\n" );
						$replaced += $status->isOK() ? 1 : 0;
						$discarded += $status->isOK() ? 0 : 1;
					}
				}
			}
		}
		$this->output(
			"Done; replaced $replaced, discarded $discarded, null recommendations deleted $nullRecommendationsDeleted\n"
		);
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
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->titleFactory = $services->getTitleFactory();
		$this->linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$this->linkRecommendationHelper = $growthServices->getLinkRecommendationHelper();
		$this->linkRecommendationUpdater = $growthServices->getLinkRecommendationUpdater();
	}

	/**
	 * Check whether the recommendation still meets our standards.
	 */
	private function validateRecommendation( LinkRecommendation $linkRecommendation ): bool {
		if ( $this->hasOption( 'all' ) ) {
			return false;
		}
		if ( $this->hasOption( 'exceptDatasetChecksums' ) ) {
			$allowedChecksums = $this->getAllowedChecksums();
			$actualChecksum = $linkRecommendation->getMetadata()->getDatasetChecksums()['model'] ?? 'wrong';

			// Abort if the recommendation is invalid and give chance to other checks
			if ( !in_array( $actualChecksum, $allowedChecksums, true ) ) {
				return false;
			}
		}
		if ( $this->hasOption( 'olderThan' ) ) {
			// Abort if the recommendation is invalid and give chance to other checks
			if (
				$linkRecommendation->getMetadata()->getTaskTimestamp() <
				$this->getOlderThanTimestamp()
			) {
				return false;
			}
		}
		if ( $this->hasOption( 'scoreLessThan' ) ) {
			$recommendationScore = min( array_map( static function ( LinkRecommendationLink $link ) {
				return $link->getScore();
			}, $linkRecommendation->getLinks() ) );

			// Abort if the recommendation is invalid and give chance to other checks
			if ( $recommendationScore < (float)$this->getOption( 'scoreLessThan' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Discard the existing recommendation and try to fetch a new one.
	 */
	private function regenerateRecommendation( LinkRecommendation $linkRecommendation ): Status {
		$title = $this->titleFactory->newFromLinkTarget( $linkRecommendation->getTitle() );
		// Deletion and addition from/to the search index should be instantaneous (subject to the
		// Search's SLO).
		$this->linkRecommendationHelper->deleteLinkRecommendation( $title->toPageIdentity(), true );
		try {
			$force = $this->hasOption( 'force' );
			return Status::wrap( $this->linkRecommendationUpdater->processCandidate(
				$title->toPageIdentity(),
				$force
			) );
		} catch ( DBReadOnlyError ) {
			$this->fatalError( 'DB is readonly, aborting' );
		} catch ( WikiConfigException $e ) {
			$this->fatalError( $e->getMessage() );
		}
		throw new LogicException( 'Cannot reach here' );
	}

	private function getTitle( LinkRecommendation $linkRecommendation ): Title {
		// The title is already cached by this point so no need for a LinkBatch.
		return $this->titleFactory->newFromLinkTarget( $linkRecommendation->getTitle() );
	}

	private function verboseLog( string $message ): void {
		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( $message );
		}
	}

	/**
	 * Helper method to handle caching of the checksum file.
	 * @return string[]
	 */
	private function getAllowedChecksums(): array {
		if ( !$this->allowedChecksums ) {
			$filename = $this->getOption( 'exceptDatasetChecksums' );
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$content = @file_get_contents( $filename );
			if ( $content === false ) {
				throw new UnexpectedValueException( "File $filename could not be opened" );
			}
			$this->allowedChecksums = array_filter(
				array_map(
					'trim',
					file( $filename, FILE_IGNORE_NEW_LINES )
				)
			);
			if ( !$this->allowedChecksums ) {
				throw new UnexpectedValueException( "File $filename did not contain checksums" );
			}
		}
		return $this->allowedChecksums;
	}

	/**
	 * Helper method to handle caching/fetching of the older than timestamp
	 */
	private function getOlderThanTimestamp(): int {
		if ( !$this->olderThanTimestamp ) {
			$rawTS = wfTimestamp(
				TS_UNIX,
				$this->getOption( 'olderThan' )
			);
			if ( !$rawTS ) {
				throw new UnexpectedValueException( "Parameter olderThan does not contain a valid timestamp" );
			}
			$this->olderThanTimestamp = (int)$rawTS;
		}
		return $this->olderThanTimestamp;
	}

	private function handleNullRecommendation( NullLinkRecommendation $nullLinkRecommendation, bool $isDryRun ): void {
		$this->verboseLog(
			'  scheduling deleting null recommendation for page ID ' . $nullLinkRecommendation->getPageId() . '... '
		);

		if ( $isDryRun ) {
			$this->verboseLog( "(dry-run)\n" );
			return;
		}

		$this->linkRecommendationStore->deleteByPageIds( [ $nullLinkRecommendation->getPageId() ] );
		$this->verboseLog( "done.\n" );
	}

}

$maintClass = RevalidateLinkRecommendations::class;
require_once RUN_MAINTENANCE_IF_MAIN;
