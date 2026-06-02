<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Cleaner\MentorListCleaner;
use MediaWiki\Context\RequestContext;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\StatusFormatter;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class CleanMentorList extends Maintenance {

	private MentorListCleaner $mentorListCleaner;
	private StatusFormatter $statusFormatter;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'Apply community-defined rules to clean the mentor list' );
	}

	private function initServices(): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		$this->mentorListCleaner = $geServices->getMentorListCleaner();
		$this->statusFormatter = $this->getServiceContainer()
			->getFormatterFactory()
			->getStatusFormatter( RequestContext::getMain() );
	}

	public function execute() {
		if ( !$this->getConfig()->get( 'GEMentorshipCleanupEnabled' ) ) {
			$this->output( 'Mentor list cleanup is not enabled on this wiki!' . PHP_EOL );
			return;
		}

		$this->initServices();

		$status = $this->mentorListCleaner->processMentors( RequestContext::getMain() );
		if ( $status->isOK() ) {
			$this->output( 'Completed successfully!' . PHP_EOL );
		} else {
			$this->output( $this->statusFormatter->getWikiText( $status, [ 'lang' => 'en' ] ) );
		}
	}
}
// @codeCoverageIgnoreStart
$maintClass = CleanMentorList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
