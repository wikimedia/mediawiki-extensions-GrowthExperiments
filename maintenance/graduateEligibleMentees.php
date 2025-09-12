<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\MenteeGraduationProcessor;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\UserIdentityLookup;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class GraduateEligibleMentees extends Maintenance {

	private UserIdentityLookup $userIdentityLookup;
	private MenteeGraduationProcessor $menteeGraduationProcessor;
	private MentorProvider $mentorProvider;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Graduate all eligible mentees' );

		$this->addOption(
			'mentor',
			'Mentor name (if present, only reassign mentees by this mentor)',
			false,
			true
		);
		$this->addOption( 'dry-run', 'Only do a dry-run' );
	}

	private function initServices() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		$this->userIdentityLookup = $this->getServiceContainer()->getUserIdentityLookup();
		$this->menteeGraduationProcessor = $geServices->getMenteeGraduationProcessor();
		$this->mentorProvider = $geServices->getMentorProvider();
	}

	public function execute() {
		$this->initServices();

		if ( !$this->menteeGraduationProcessor->isEnabled() ) {
			$this->fatalError( 'Mentee graduation is not enabled!' );
		}

		if ( $this->hasOption( 'mentor' ) ) {
			$mentorName = $this->getOption( 'mentor' );
			$mentor = $this->userIdentityLookup->getUserIdentityByName( $mentorName );
			if ( !$mentor ) {
				$this->fatalError( 'Unable to find mentor ' . $mentorName );
			}
			$mentors = [ $mentor ];
		} else {
			$mentors = $this->mentorProvider->getMentors();
		}

		$graduatedNo = 0;
		$dryRun = $this->hasOption( 'dry-run' );
		$verb = $dryRun ? 'Would graduate' : 'Graduated';
		foreach ( $mentors as $mentor ) {
			$this->output( 'Processing mentor ' . $mentor->getName() . '...' );
			if ( $dryRun ) {
				$graduatedNo += $this->menteeGraduationProcessor->calculateEligibleMenteesByMentor( $mentor );
			} else {
				$graduatedNo += $this->menteeGraduationProcessor->graduateEligibleMenteesByMentor( $mentor );
			}
			$this->output( '    done! ' . $verb . ' ' . $graduatedNo . ' mentees so far.' . PHP_EOL );
		}

		$this->output( 'Done! ' . $verb . ' ' . $graduatedNo . ' mentees in total.' . PHP_EOL );
	}
}

// @codeCoverageIgnoreStart
$maintClass = GraduateEligibleMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
