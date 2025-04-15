<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Stats\StatsFactory;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateMenteeData extends Maintenance {

	private MenteeOverviewDataUpdater $menteeOverviewDataUpdater;
	private MentorProvider $mentorProvider;
	private ILoadBalancer $growthLoadBalancer;
	private StatsFactory $statsFactory;
	private array $detailedProfilingInfo = [];

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 100 );
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'Update growthexperiments_mentee_data database table' );
		$this->addOption( 'mentor', 'Username of the mentor to update the data for', false, true );
		$this->addOption( 'statsd', 'Send timing information to statsd' );
		$this->addOption( 'verbose', 'Output detailed profiling information' );
		$this->addOption(
			'dbshard',
			'ID of the DB shard this script runs at',
			false,
			true
		);
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->menteeOverviewDataUpdater = $geServices->getMenteeOverviewDataUpdater();
		$this->menteeOverviewDataUpdater->setBatchSize( $this->getBatchSize() );
		$this->mentorProvider = $geServices->getMentorProvider();
		$this->growthLoadBalancer = $geServices->getLoadBalancer();
		$this->statsFactory = $services->getStatsFactory();
	}

	private function addProfilingInfoForMentor( array $mentorProfilingInfo ): void {
		foreach ( $mentorProfilingInfo as $section => $seconds ) {
			if ( !array_key_exists( $section, $this->detailedProfilingInfo ) ) {
				$this->detailedProfilingInfo[$section] = 0;
			}

			$this->detailedProfilingInfo[$section] += $seconds;
		}
	}

	private function getSummarizedProfilingInfoInSeconds(): array {
		$res = [];
		foreach ( $this->detailedProfilingInfo as $section => $seconds ) {
			$res[$section] = round( $seconds, 2 );
		}
		return $res;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		$startTime = time();

		if ( $this->hasOption( 'mentor' ) ) {
			$mentors = [ $this->getOption( 'mentor' ) ];
		} else {
			$mentors = $this->mentorProvider->getMentors();
		}

		$allUpdatedMenteeIds = [];
		$dbw = $this->growthLoadBalancer->getConnection( DB_PRIMARY );
		foreach ( $mentors as $mentor ) {
			$updatedMenteeIds = $this->menteeOverviewDataUpdater->updateDataForMentor( $mentor );
			$allUpdatedMenteeIds = array_merge( $allUpdatedMenteeIds, $updatedMenteeIds );
			$this->addProfilingInfoForMentor(
				$this->menteeOverviewDataUpdater->getMentorProfilingInfo()
			);
		}

		// Delete all mentees recorded in the table which were not updated
		// This cannot happen when --mentor was passed, as that would delete
		// most of the data.
		if ( !$this->hasOption( 'mentor' ) ) {
			$menteeIdsToDelete = array_diff(
				array_map(
					'intval',
					$dbw->newSelectQueryBuilder()
						->select( 'mentee_id' )
						->from( 'growthexperiments_mentee_data' )
						->caller( __METHOD__ )->fetchFieldValues()
				),
				$allUpdatedMenteeIds
			);
			if ( $menteeIdsToDelete !== [] ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'growthexperiments_mentee_data' )
					->where( [
						'mentee_id' => $menteeIdsToDelete
					] )
					->caller( __METHOD__ )
					->execute();
			}
		}

		$totalTimeInSeconds = time() - $startTime;
		$profilingInfo = $this->getSummarizedProfilingInfoInSeconds();

		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( "Profiling data:\n" );
			foreach ( $profilingInfo as $section => $seconds ) {
				$this->output( "  * {$section}: {$seconds} seconds\n" );
			}
			$this->output( "===============\n" );
		}

		if ( $this->hasOption( 'statsd' ) && $this->hasOption( 'dbshard' ) ) {
			$this->statsFactory->withComponent( 'GrowthExperiments' )
				->getTiming( 'update_mentee_data_seconds' )
				->setLabel( 'shard', $this->getOption( 'dbshard' ) )
				->setLabel( 'type', 'total' )
				->observeSeconds( $totalTimeInSeconds );

			// Stay backward compatible with the legacy Graphite-based dashboard
			// feeding on this data.
			// TODO: remove after switching to Prometheus-based dashboards
			$statsdDataFactory = MediaWikiServices::getInstance()->getStatsdDataFactory();
			$statsdDataFactory->timing(
				'timing.growthExperiments.updateMenteeData.' . $this->getOption( 'dbshard' ) . '.total',
				$totalTimeInSeconds
			);

			foreach ( $profilingInfo as $section => $seconds ) {
				$this->statsFactory->withComponent( 'GrowthExperiments' )
					->getTiming( 'update_mentee_data_section_seconds' )
					->setLabel( 'shard', $this->getOption( 'dbshard' ) )
					->setLabel( 'section', $section )
					->observeSeconds( $seconds );

				// Stay backward compatible with the legacy Graphite-based dashboard
				// feeding on this data.
				// TODO: remove after switching to Prometheus-based dashboards
				$statsdDataFactory->timing(
					'timing.growthExperiments.updateMenteeData.' . $this->getOption( 'dbshard' ) . '.' . $section,
					$seconds
				);
			}
		}

		$this->output( "Done. Took {$totalTimeInSeconds} seconds.\n" );
	}
}

$maintClass = UpdateMenteeData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
