<?php

namespace GrowthExperiments\Specials;

use FormSpecialPage;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use HTMLForm;
use MediaWiki\Revision\RevisionLookup;
use MWTimestamp;
use Title;
use TitleFactory;

class SpecialEditGrowthConfig extends FormSpecialPage {
	/** @var string[] */
	private const SUGGESTED_EDITS_INTRO_LINKS = [ 'create', 'image' ];

	/** @var TitleFactory */
	private $titleFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var WikiPageConfigWriterFactory */
	private $configWriterFactory;

	/** @var GrowthExperimentsMultiConfig */
	private $growthWikiConfig;

	/** @var WikiPageConfigWriter */
	private $configWriter;

	/** @var Title */
	private $configTitle;

	/** @var string|null */
	private $errorMsgKey;

	/** @var bool Set to false if directly accessed, to not show potentially confusing backlink */
	private $showBacklink = true;

	/**
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 * @param WikiPageConfigWriterFactory $configWriterFactory
	 * @param GrowthExperimentsMultiConfig $growthWikiConfig
	 */
	public function __construct(
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup,
		WikiPageConfigWriterFactory $configWriterFactory,
		GrowthExperimentsMultiConfig $growthWikiConfig
	) {
		parent::__construct( 'EditGrowthConfig', 'editinterface' );

		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
		$this->configWriterFactory = $configWriterFactory;
		$this->growthWikiConfig = $growthWikiConfig;
	}

	/**
	 * @inheritDoc
	 */
	public function requiresWrite() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessagePrefix() {
		return 'growthexperiments-edit-config';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-edit-config-title' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	protected function setParameter( $par ) {
		// If no parameter is passed, pretend the default is to be used;
		// there is normally only one GE config page.
		if ( $par === null || $par === '' ) {
			$par = $this->getConfig()->get( 'GEWikiConfigPageTitle' );
			$this->showBacklink = false;
		}

		parent::setParameter( $par );

		$this->configTitle = $this->titleFactory->newFromText( $par );
		if (
			$this->configTitle === null ||
			!$this->configTitle->hasContentModel( CONTENT_MODEL_JSON )
		) {
			$this->errorMsgKey = 'growthexperiments-edit-config-error-invalid-title';
			return;
		}

		$this->configWriter = $this->configWriterFactory->newWikiPageConfigWriter(
			$this->configTitle,
			$this->getUser()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function preText() {
		if ( $this->errorMsgKey !== null ) {
			return $this->msg( $this->errorMsgKey )->text();
		}
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function setHeaders() {
		parent::setHeaders();
		if ( $this->configTitle !== null && $this->showBacklink ) {
			$this->getOutput()->addBacklinkSubtitle( $this->configTitle );
		}
	}

	/**
	 * Customize the form used
	 *
	 * This:
	 * 	* Hides the form if there is an error
	 * 	* Displays "last edited by" message
	 * 	* Displays an introduction message
	 *
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		if ( $this->errorMsgKey !== null ) {
			$form->suppressDefaultSubmit( true );
		}

		$revision = $this->revisionLookup->getRevisionByTitle( $this->configTitle );
		if ( $revision !== null ) {
			$lastRevisionUser = $revision->getUser();
			if ( $lastRevisionUser !== null ) {
				$form->addPreText( $this->msg(
					'growthexperiments-edit-config-last-edit',
					$lastRevisionUser,
					MWTimestamp::getInstance( $revision->getTimestamp() )->getRelativeTimestamp()
				)->parseAsBlock() );
			} else {
				$form->addPreText( $this->msg(
					'growthexperiments-edit-config-last-edit-unknown-user',
					MWTimestamp::getInstance( $revision->getTimestamp() )->getRelativeTimestamp()
				)->parseAsBlock() );
			}
		}
		$form->addPreText( $this->msg(
			'growthexperiments-edit-config-pretext',
			$this->configTitle->getPrefixedText()
		)->parseAsBlock() );
	}

	private function getRawDescriptors(): array {
		return [
			'GEHelpPanelReadingModeNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-reading-namespaces',
				'section' => 'help-panel',
			],
			'GEHelpPanelExcludedNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-disabled-namespaces',
				'section' => 'help-panel',
			],
			'GEHelpPanelHelpDeskTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-help-panel-helpdesk-title',
				'required' => false,
				'section' => 'help-panel',
			],
			'GEHelpPanelHelpDeskPostOnTop' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-edit-config-help-panel-post-on-top',
				'section' => 'help-panel',
			],
			'GEHelpPanelViewMoreTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-help-panel-view-more',
				'required' => false,
				'section' => 'help-panel',
			],
			'GEHelpPanelSearchNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-searched-namespaces',
				'section' => 'help-panel',
			],
			'GEHelpPanelAskMentor' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-edit-config-help-panel-ask-mentor',
				'section' => 'help-panel',
			],
			'GEHomepageTutorialTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-tutorial-title',
				'required' => false,
				'section' => 'homepage',
			],
			'GEHomepageSuggestedEditsIntroLinks-create' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-intro-links-create',
				'required' => true,
				'section' => 'homepage',
			],
			'GEHomepageSuggestedEditsIntroLinks-image' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-intro-links-image',
				'required' => true,
				'section' => 'homepage',
			],
			'GEMentorshipEnabled' => [
				'type' => 'check',
				'label-message' => 'growthexperiments-edit-config-mentorship-enabled',
				'section' => 'mentorship',
			],
			'GEHomepageMentorsList' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-mentorship-list-of-auto-assigned-mentors',
				'required' => false,
				'section' => 'mentorship',
			],
			'GEHomepageManualAssignmentMentorsList' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-mentorship-list-of-manually-assigned-mentors',
				'required' => false,
				'section' => 'mentorship',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		if ( $this->errorMsgKey !== null ) {
			// Return an empty array when there is an error
			return [];
		}

		$descriptors = $this->getRawDescriptors();

		// Add default values
		foreach ( $descriptors as $name => $descriptor ) {
			if ( strpos( $name, '-' ) !== false ) {
				// Non-standard field, will be handled later in this method
				continue;
			}

			$default = $this->growthWikiConfig->getWithFlags(
				$name,
				GrowthExperimentsMultiConfig::READ_LATEST
			);
			if ( is_array( $default ) ) {
				$default = implode( "\n", $default );
			}

			$descriptors[$name]['default'] = $default;
		}

		// Add default values for intro links
		$linkValues = $this->growthWikiConfig->getWithFlags(
			'GEHomepageSuggestedEditsIntroLinks',
			GrowthExperimentsMultiConfig::READ_LATEST
		);
		foreach ( self::SUGGESTED_EDITS_INTRO_LINKS as $link ) {
			$descriptors["GEHomepageSuggestedEditsIntroLinks-$link"]['default'] = $linkValues[$link];
		}

		// Add edit summary field
		$descriptors['summary'] = [
			'type' => 'text',
			'label-message' => 'growthexperiments-edit-config-edit-summary',
		];
		return $descriptors;
	}

	private function normalizeSuggestedEditsIntroLinks( array $data ): array {
		$links = [];
		foreach ( self::SUGGESTED_EDITS_INTRO_LINKS as $link ) {
			$links[$link] = $data["GEHomepageSuggestedEditsIntroLinks-$link"];
		}
		return $links;
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		// Normalize data about namespaces + populate $dataToSave
		$dataToSave = [];
		foreach ( $this->getFormFields() as $name => $descriptor ) {
			if ( $descriptor['type'] === 'namespacesmultiselect' ) {
				if ( $data[$name] === '' ) {
					$data[$name] = [];
				} else {
					$data[$name] = array_map(
						'intval',
						explode( "\n", $data[$name] )
					);
				}
			}

			if ( strpos( $name, '-' ) === false && $name !== 'summary' ) {
				$dataToSave[$name] = $data[$name];
			}
		}

		// Normalize GEHomepageSuggestedEditsIntroLinks
		$dataToSave['GEHomepageSuggestedEditsIntroLinks'] =
			$this->normalizeSuggestedEditsIntroLinks( $data );

		$this->configWriter->setVariables( $dataToSave );
		return $this->configWriter->save( $data['summary'] );
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-edit-config-config-changed' );
		$this->getOutput()->addWikiMsg( 'growthexperiments-edit-config-return-to-form' );
	}
}
