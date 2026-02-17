<?php

namespace GrowthExperiments\Maintenance;

use Generator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorProvider;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MessageLocalizer;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Purge expired rows related to mentor status from user_properties
 */
class PurgeExpiredMentorStatus extends Maintenance {

	private IReadableDatabase $dbr;
	private IDatabase $dbw;
	private CommunityStructuredMentorProvider $mentorProvider;
	private IConfigurationProvider $mentorListProvider;
	private MessageLocalizer $messageLocalizer;
	private UserFactory $userFactory;
	private StatusFormatter $statusFormatter;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription(
			'Remove expired values of MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF from user_properties or config'
		);
		$this->addOption( 'dry-run', 'Do not actually change anything.' );
		$this->setBatchSize( 100 );
	}

	private function initServices(): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$ccServices = CommunityConfigurationServices::wrap( $this->getServiceContainer() );
		$this->userFactory = $this->getServiceContainer()->getUserFactory();
		$this->mentorProvider = $geServices->getMentorProviderStructured();
		$this->mentorListProvider = $ccServices->getConfigurationProviderFactory()
			->newProvider( 'GrowthMentorList' );
		$this->dbr = $this->getReplicaDB();
		$this->dbw = $this->getPrimaryDB();
		$this->messageLocalizer = RequestContext::getMain();
		$this->statusFormatter = $this->getServiceContainer()->getFormatterFactory()
			->getStatusFormatter( $this->messageLocalizer );
	}

	private function getRows(): Generator {
		yield from $this->dbr->newSelectQueryBuilder()
			->select( [ 'up_user', 'up_value' ] )
			->from( 'user_properties' )
			->where( [ 'up_property' => MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF ] )
			->caller( __METHOD__ )->fetchResultSet();
	}

	private function filterAndBatch(): Generator {
		$batch = [];
		foreach ( $this->getRows() as $row ) {
			if (
				$row->up_value === null ||
				ConvertibleTimestamp::convert( TS_UNIX, $row->up_value ) < wfTimestamp( TS_UNIX )
			) {
				$batch[] = $row->up_user;

				if ( count( $batch ) >= $this->getBatchSize() ) {
					yield $batch;
					$batch = [];
				}
			}
		}

		if ( $batch !== [] ) {
			yield $batch;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();
		$this->purgeUserOptions();
		$this->purgeConfig();
	}

	private function deleteTimestamps( array $toDelete ): void {
		if ( $this->getOption( 'dry-run' ) ) {
			return;
		}
		$this->beginTransaction( $this->dbw, __METHOD__ );
		$this->dbw->newDeleteQueryBuilder()
			->deleteFrom( 'user_properties' )
			->where( [
				'up_property' => MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF,
				'up_user' => $toDelete,
			] )
			->caller( __METHOD__ )
			->execute();
		$this->commitTransaction( $this->dbw, __METHOD__ );
	}

	private function purgeUserOptions(): void {
		$deletedCount = 0;
		foreach ( $this->filterAndBatch() as $batch ) {
			$this->deleteTimestamps( $batch );
			$deletedCount += count( $batch );
		}

		if ( $this->getOption( 'dry-run' ) ) {
			$this->output( "Would delete $deletedCount rows from user_properties.\n" );
		} else {
			$this->output( "Deleted $deletedCount rows from user_properties.\n" );
		}
	}

	private function purgeConfig(): void {
		$configStatus = $this->mentorListProvider->loadValidConfiguration();
		if ( !$configStatus->isOK() ) {
			$err = $this->statusFormatter->getWikiText( $configStatus, [ 'lang' => 'en' ] );
			if ( $configStatus->hasMessage( 'growthexperiments-mentor-list-missing-key' ) ) {
				$this->output( "Initial config is invalid, skipping because: " );
				$this->output( $err );
				$this->output( ".\n" );
				return;
			}
			$this->fatalError( $err );
		}
		$deletedCount = 0;
		$config = $configStatus->getValue();
		foreach ( $this->filterConfigAndBatch( $config ) as $batch ) {
			$deletedCount += count( $batch );
			if ( $this->getOption( 'dry-run' ) ) {
				continue;
			}
			$this->deleteTimestampsFromConfig( $config, $batch );
		}

		if ( $this->getOption( 'dry-run' ) ) {
			$this->output( "Would delete $deletedCount rows from config\n" );
		} else {
			$this->output( "Deleted $deletedCount rows from config.\n" );
		}
	}

	private function filterConfigAndBatch( array $config ): Generator {
		$batch = [];
		foreach ( $this->getMentorEntries( $config ) as $mentorUserIdentity ) {
			$userId = $mentorUserIdentity->getUser()->getId();
			// $mentorUserIdentity->getUser()->getId() and $mentor->getId() are returning 0, is the user mutated?
			$mentor = $this->mentorProvider->newMentorFromUserIdentity( $mentorUserIdentity->getUser() );
			$awayTimestamp = $mentor->getStatusAwayTimestamp();

			if (
				$awayTimestamp &&
				ConvertibleTimestamp::convert( TS_UNIX, $awayTimestamp ) < wfTimestamp( TS_UNIX )
			) {
				$batch[] = $userId;

				if ( count( $batch ) >= $this->getBatchSize() ) {
					yield $batch;
					$batch = [];
				}
			}
		}

		if ( $batch !== [] ) {
			yield $batch;
		}
	}

	/**
	 * @throws MaintenanceFatalError
	 */
	private function getMentorEntries( array $config ): array {
		return array_map( function ( int $userId ) {
			return $this->userFactory->newFromId( $userId );
		}, array_keys( $config[CommunityStructuredMentorWriter::CONFIG_KEY] ) );
	}

	/**
	 * @throws MaintenanceFatalError
	 */
	private function deleteTimestampsFromConfig( array $config, array $batch ): void {
		foreach ( $batch as $mentorId ) {
			unset( $config[CommunityStructuredMentorWriter::CONFIG_KEY][$mentorId]['awayTimestamp'] );
		}
		$summary = $this->messageLocalizer->msg(
			'growthexperiments-maintenance-config-change-summary-purge-timestamps'
		)->inContentLanguage()->text();
		$storeStatus = $this->mentorListProvider->storeValidConfiguration(
			$config,
			new UltimateAuthority( User::newSystemUser( User::MAINTENANCE_SCRIPT_USER ) ),
			$summary,
		);
		if ( !$storeStatus->isOK() ) {
			$this->fatalError( $this->statusFormatter->getWikiText( $storeStatus, [ 'lang' => 'en' ] ) );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = PurgeExpiredMentorStatus::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
