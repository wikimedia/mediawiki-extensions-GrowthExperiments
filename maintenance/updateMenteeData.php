<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

class UpdateMenteeData extends Maintenance {
	/** @var MenteeOverviewDataProvider */
	private $menteeOverviewDataProvider;

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorStore */
	private $mentorStore;

	/** @var UserFactory */
	private $userFactory;

	/** @var ILoadBalancer */
	private $growthLoadBalancer;

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 200 );
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'Update growthexperiments_mentee_data database table' );
		$this->addOption( 'mentor', 'Username of the mentor to update the data for', false, true );
	}

	private function initServices() {
		$services = MediaWikiServices::getInstance();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->menteeOverviewDataProvider = $geServices->getUncachedMenteeOverviewDataProvider();
		$this->mentorManager = $geServices->getMentorManager();
		$this->mentorStore = $geServices->getMentorStore();
		$this->userFactory = $services->getUserFactory();
		$this->growthLoadBalancer = $geServices->getLoadBalancer();
	}

	public function execute() {
		$this->initServices();

		if ( $this->hasOption( 'mentor' ) ) {
			$mentors = [ $this->getOption( 'mentor' ) ];
		} else {
			try {
				$mentors = $this->mentorManager->getMentors();
			} catch ( WikiConfigException $e ) {
				$this->fatalError( 'List of mentors cannot be fetched.' );
				return;
			}
		}

		$thisBatch = 0;
		$batchSize = $this->getBatchSize();
		$allUpdatedMenteeIds = [];
		$dbw = $this->growthLoadBalancer->getConnection( DB_PRIMARY );
		foreach ( $mentors as $mentorRaw ) {
			$mentor = $this->userFactory->newFromName( $mentorRaw );
			if ( $mentor === null ) {
				$this->output( 'Skipping ' . $mentorRaw . ", invalid user\n" );
				continue;
			}

			$data = $this->menteeOverviewDataProvider->getFormattedDataForMentor( $mentor );
			$updatedMenteeIds = [];
			foreach ( $data as $menteeId => $menteeData ) {
				$encodedData = FormatJson::encode( $menteeData );
				$dbw->begin( __METHOD__ );
				$dbw->upsert(
					'growthexperiments_mentee_data',
					[
						'mentee_id' => $menteeId,
						'mentee_data' => $encodedData
					],
					[ [ 'mentee_id' ] ],
					[
						'mentee_data' => $encodedData
					],
					__METHOD__
				);
				$dbw->commit( __METHOD__ );
				$thisBatch++;

				$updatedMenteeIds[] = $menteeId;
				$allUpdatedMenteeIds[] = $menteeId;

				if ( $thisBatch >= $batchSize ) {
					$thisBatch = 0;
					$this->waitForReplication();
				}
			}

			// Delete all mentees of $mentor we did not update
			$menteeIdsToDelete = array_diff(
				array_filter(
					$this->mentorStore->getMenteesByMentor( $mentor ),
					static function ( $mentee ) {
						return $mentee->getId();
					}
				),
				$updatedMenteeIds
			);
			if ( $menteeIdsToDelete !== [] ) {
				$dbw->begin( __METHOD__ );
				$dbw->delete(
					'growthexperiments_mentee_data',
					[
						'mentee_id' => $menteeIdsToDelete
					],
					__METHOD__
				);
				$dbw->commit( __METHOD__ );
				$this->waitForReplication();
			}
		}

		// Delete all mentees recorded in the table which were not updated
		// This cannot happen when --mentor was passed, as that would delete
		// most of the data.
		if ( !$this->hasOption( 'mentor' ) ) {
			$menteeIdsToDelete = array_diff(
				array_map(
					'intval',
					$dbw->selectFieldValues(
						'growthexperiments_mentee_data',
						'mentee_id',
						'',
						__METHOD__
					)
				),
				$allUpdatedMenteeIds
			);
			if ( $menteeIdsToDelete !== [] ) {
				$dbw->delete(
					'growthexperiments_mentee_data',
					[
						'mentee_id' => $menteeIdsToDelete
					]
				);
			}
		}
	}
}

$maintClass = UpdateMenteeData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
