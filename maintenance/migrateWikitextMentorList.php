<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use GrowthExperiments\Mentorship\Provider\WikitextMentorProvider;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityLookup;
use Status;
use TitleFactory;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MigrateWikitextMentorList extends Maintenance {

	/** @var WikitextMentorProvider */
	private $wikitextMentorProvider;

	/** @var WikiPageConfigWriterFactory */
	private $configWriterFactory;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Migrate wikitext-based mentor list to a structured mentor list' );

		$this->addOption( 'dry-run', 'Print the structured mentor list that would be saved on-wiki.' );
		$this->addOption( 'override', 'Override the existing structured mentor list.' );
	}

	private function initServices(): void {
		$services = MediaWikiServices::getInstance();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->wikitextMentorProvider = $geServices->getMentorProviderWikitext();
		$this->configWriterFactory = $geServices->getWikiPageConfigWriterFactory();
		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->titleFactory = $services->getTitleFactory();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		$structuredMentorListTitle = $this->titleFactory->newFromText(
			$this->getConfig()->get( 'GEStructuredMentorList' )
		);
		if ( !$structuredMentorListTitle ) {
			$this->fatalError( 'Value of $wgGEStructuredMentorList is not valid.' );
		}
		if (
			!$this->hasOption( 'override' ) &&
			!$this->hasOption( 'dry-run' ) &&
			$structuredMentorListTitle->exists()
		) {
			$this->fatalError(
				"Structured mentor list already exists ({$structuredMentorListTitle->getPrefixedText()}. " .
				'You can bypass this validation by using --override.'
			);
		}

		$mentorList = [];
		foreach ( $this->wikitextMentorProvider->getMentorsSafe() as $mentorName ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByName( $mentorName );
			if ( !$mentorUser ) {
				$this->output( "Skipping ${mentorName}, invalid username.\n" );
				continue;
			}
			$mentor = $this->wikitextMentorProvider->newMentorFromUserIdentity( $mentorUser );

			$mentorArray = StructuredMentorWriter::serializeMentor( $mentor );

			$messageValidationStatus = Status::wrap(
				StructuredMentorListValidator::validateMentorMessage( $mentorArray )
			);
			if ( !$messageValidationStatus->isGood() ) {
				$this->error(
					'WARNING: Message for "' . $mentorName . '" did not validate (error: ' .
					$messageValidationStatus->getWikiText() .
					')'
				);
			}

			$mentorList[$mentor->getUserIdentity()->getId()] = $mentorArray;
		}

		if ( !$this->hasOption( 'dry-run' ) ) {
			$configWriter = $this->configWriterFactory
				->newWikiPageConfigWriter( $structuredMentorListTitle );
			$configWriter->setVariable( StructuredMentorWriter::CONFIG_KEY, $mentorList );
			$status = $configWriter->save( 'Migrate wikitext mentor list to a structured form ([[:phab:T264343]])' );
			if ( !$status->isOK() ) {
				$this->fatalError( $status->getWikiText( false, false, 'en' ) );
			}

			$this->output( "Done!\n" );
		} else {
			$this->output( FormatJson::encode(
				[ StructuredMentorWriter::CONFIG_KEY => $mentorList ],
				true
			) . "\n" );
		}
	}
}

$maintClass = MigrateWikitextMentorList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
