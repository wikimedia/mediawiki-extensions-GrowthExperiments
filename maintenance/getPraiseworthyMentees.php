<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\UserIdentityLookup;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class GetPraiseworthyMentees extends Maintenance {

	private UserIdentityLookup $userIdentityLookup;
	private PraiseworthyMenteeSuggester $suggester;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Get list of all praiseworthy mentees for a given mentor' );

		$this->addOption(
			'uncached',
			'Ignore cached values; calculate list of praiseworthy mentees (can take several minutes)'
		);
		$this->addArg(
			'mentor',
			'Username of the mentor to get praiseworthy mentees for'
		);
	}

	private function initServices() {
		$services = $this->getServiceContainer();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->suggester = $geServices->getPraiseworthyMenteeSuggester();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		$mentor = $this->userIdentityLookup->getUserIdentityByName( $this->getArg( 0 ) );
		if ( !$mentor ) {
			$this->fatalError( 'Mentor not found. Maybe a typo in the username?' );
		}

		if ( $this->hasOption( 'uncached' ) ) {
			$praiseworthyMentees = $this->suggester->getPraiseworthyMenteesForMentorUncached( $mentor );
		} else {
			$praiseworthyMentees = $this->suggester->getPraiseworthyMenteesForMentor( $mentor );
		}

		$this->output( "List of praiseworthy mentees for User:" . $mentor->getName() . PHP_EOL );
		foreach ( $praiseworthyMentees as $mentee ) {
			$this->output( "* User:" . $mentee->getUser()->getName() . PHP_EOL );
		}
	}
}
// @codeCoverageIgnoreStart
$maintClass = GetPraiseworthyMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
