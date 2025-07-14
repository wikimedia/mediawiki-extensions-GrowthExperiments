<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class RefreshPraiseworthyMentees extends Maintenance {

	private MentorProvider $mentorProvider;
	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription(
			'Refresh cache of praiseworthy mentees for all mentors active on the wiki'
		);
		$this->addOption( 'force', 'Do the update even if GEPersonalizedPraiseBackendEnabled is false' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->mentorProvider = $geServices->getMentorProvider();
		$this->praiseworthyMenteeSuggester = $geServices->getPraiseworthyMenteeSuggester();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		if (
			!$this->getConfig()->get( 'GEPersonalizedPraiseBackendEnabled' ) &&
			!$this->hasOption( 'force' )
		) {
			$this->output( "Personalized praise backend is disabled.\n" );
			return;
		}

		$mentors = $this->mentorProvider->getMentors();
		foreach ( $mentors as $mentor ) {
			$this->praiseworthyMenteeSuggester->refreshPraiseworthyMenteesForMentor( $mentor );
		}

		$this->output( "Done\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = RefreshPraiseworthyMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
