<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\MentorDashboard\Modules\MenteeOverview;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserOptionsManager;
use Wikimedia\Rdbms\IDatabase;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MigrateMenteeOverviewFiltersToPresets extends Maintenance {

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var IDatabase */
	private $dbr;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription(
			'Migrate growthexperiments-mentee-overview-filters property to growthexperiments-mentee-overview-presets'
		);

		$this->addOption( 'update', 'Actually perform the update' );

		$this->setBatchSize( 100 );
	}

	public function initServices() {
		$services = MediaWikiServices::getInstance();

		$this->userOptionsManager = $services->getUserOptionsManager();
		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
	}

	/**
	 * @param UserIdentity $user
	 * @param string $option
	 * @return array
	 */
	private function getMenteeOverviewOptionValue( UserIdentity $user, string $option ): array {
		return FormatJson::decode(
			$this->userOptionsManager->getOption(
				$user,
				$option
			),
			true
		) ?? FormatJson::decode(
			$this->userOptionsManager->getDefaultOption( $option ),
			true
		);
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();
		$dryRun = !$this->hasOption( 'update' );

		$rowsUpdated = 0;
		$processedUsers = 0;
		$lastUserId = 0;
		while ( true ) {
			$userBatch = $this->dbr->selectFieldValues(
				'user_properties',
				'up_user',
				[
					'up_property' => MenteeOverview::FILTERS_PREF,
					'up_user > ' . $this->dbr->addQuotes( $lastUserId )
				],
				__METHOD__,
				[
					'ORDER BY' => 'up_user',
					'LIMIT' => $this->getBatchSize()
				]
			);

			if ( $userBatch === [] ) {
				break;
			}

			foreach ( $userBatch as $userId ) {
				$lastUserId = (int)$userId;

				$user = $this->userIdentityLookup->getUserIdentityByUserId( (int)$userId );
				if ( !$user ) {
					$this->output( "User with ID $userId does not exist. Skipping.\n" );
					continue;
				}

				$presets = $this->getMenteeOverviewOptionValue(
					$user,
					MenteeOverview::PRESETS_PREF
				);
				if ( !array_key_exists( 'filters', $presets ) ) {
					$presets['filters'] = $this->getMenteeOverviewOptionValue(
						$user,
						MenteeOverview::FILTERS_PREF
					);

					if ( !$dryRun ) {
						$this->userOptionsManager->setOption(
							$user,
							MenteeOverview::PRESETS_PREF,
							FormatJson::encode( $presets )
						);
						$this->userOptionsManager->setOption(
							$user,
							MenteeOverview::FILTERS_PREF,
							null
						);
						$this->userOptionsManager->saveOptions( $user );
					}

					$rowsUpdated++;
				}

				$processedUsers++;
			}

			if ( !$dryRun ) {
				$this->waitForReplication();
			}
		}

		if ( !$dryRun ) {
			$this->output( "Done. Updated $rowsUpdated rows and processed $processedUsers users.\n" );
		} else {
			$this->output( "Done. Would update $rowsUpdated rows and process $processedUsers users.\n" );
		}

		return !$dryRun;
	}
}

$maintClass = MigrateMenteeOverviewFiltersToPresets::class;
require_once RUN_MAINTENANCE_IF_MAIN;
