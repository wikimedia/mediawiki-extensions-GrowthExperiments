<?php

namespace GrowthExperiments\Specials;

use FormSpecialPage;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use HTMLForm;
use MediaWiki\Revision\RevisionLookup;
use MWTimestamp;
use PageProps;
use Title;
use TitleFactory;

class SpecialEditGrowthConfig extends FormSpecialPage {
	/** @var string[] */
	private const SUGGESTED_EDITS_INTRO_LINKS = [ 'create', 'image' ];

	/** @var TitleFactory */
	private $titleFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var PageProps */
	private $pageProps;

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
	 * @param PageProps $pageProps
	 * @param WikiPageConfigWriterFactory $configWriterFactory
	 * @param GrowthExperimentsMultiConfig $growthWikiConfig
	 */
	public function __construct(
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup,
		PageProps $pageProps,
		WikiPageConfigWriterFactory $configWriterFactory,
		GrowthExperimentsMultiConfig $growthWikiConfig
	) {
		parent::__construct( 'EditGrowthConfig', 'editinterface' );

		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
		$this->pageProps = $pageProps;
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
				'type' => 'radio',
				'label-message' => 'growthexperiments-edit-config-help-panel-post-on-top',
				'options-messages' => [
					'growthexperiments-edit-config-help-panel-post-on-top-true' => 'true',
					'growthexperiments-edit-config-help-panel-post-on-top-false' => 'false',
				],
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
				'type' => 'radio',
				'label-message' => 'growthexperiments-edit-config-help-panel-ask-mentor',
				'options-messages' => [
					'growthexperiments-edit-config-help-panel-ask-mentor-true' => 'true',
					'growthexperiments-edit-config-help-panel-ask-mentor-false' => 'false',
				],
				'section' => 'help-panel',
			],
			'GEHelpPanelLinks-0-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-mos-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'GEHelpPanelLinks-0-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-mos-label',
				'section' => 'help-panel-links',
			],
			'GEHelpPanelLinks-1-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-editing-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'GEHelpPanelLinks-1-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-editing-label',
				'section' => 'help-panel-links',
			],
			'GEHelpPanelLinks-2-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-images-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'GEHelpPanelLinks-2-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-images-label',
				'section' => 'help-panel-links',
			],
			'GEHelpPanelLinks-3-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-references-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'GEHelpPanelLinks-3-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-references-label',
				'section' => 'help-panel-links',
			],
			'GEHelpPanelLinks-4-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-articlewizard-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'GEHelpPanelLinks-4-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-articlewizard-label',
				'section' => 'help-panel-links',
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
				'type' => 'radio',
				'label-message' => 'growthexperiments-edit-config-mentorship-enabled',
				'options-messages' => [
					'growthexperiments-edit-config-mentorship-enabled-true' => 'true',
					'growthexperiments-edit-config-mentorship-enabled-false' => 'false',
				],
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
			if ( is_bool( $default ) ) {
				$default = $default ? 'true' : 'false';
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

		// Add default values for help panel links
		$helpPanelLinks = $this->growthWikiConfig->get( 'GEHelpPanelLinks' );
		for ( $i = 0; $i < count( $helpPanelLinks ); $i++ ) {
			if (
				isset( $descriptors["GEHelpPanelLinks-$i-title"] ) &&
				isset( $descriptors["GEHelpPanelLinks-$i-label"] )
			) {
				$descriptors["GEHelpPanelLinks-$i-title"]['default'] = $helpPanelLinks[$i]['title'];
				$descriptors["GEHelpPanelLinks-$i-label"]['default'] = $helpPanelLinks[$i]['text'];
			}
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

	private function normalizeHelpPanelLinks( array $data ): array {
		$res = [];
		// Right now, we support up to 5 help panel links
		// If you want to change this, don't forget to update
		// SpecialEditGrowthConfig::getRawDescriptors as well.
		$supportedHelpPanelLinks = 5;
		for ( $i = 0; $i < $supportedHelpPanelLinks; $i++ ) {
			if (
				$data["GEHelpPanelLinks-$i-title"] == '' ||
				$data["GEHelpPanelLinks-$i-label"] == ''
			) {
				continue;
			}

			$title = $this->titleFactory->newFromText( $data["GEHelpPanelLinks-$i-title"] );
			$props = ( $title !== null && $title->exists() && !$title->isExternal() ) ?
				$this->pageProps->getProperties( $title, 'wikibase_item' ) :
				[];
			$linkId = null;
			$pageId = $title->getId();
			if ( in_array( $pageId, $props ) ) {
				$linkId = $props[$pageId];
			}
			$res[] = [
				'title' => $data["GEHelpPanelLinks-$i-title"],
				'text' => $data["GEHelpPanelLinks-$i-label"],
				'id' => $linkId ?? $title->getPrefixedDBkey(),
			];
		}
		return $res;
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

				// Basic normalization
				if ( $dataToSave[$name] === 'true' ) {
					$dataToSave[$name] = true;
				} elseif ( $dataToSave[$name] === 'false' ) {
					$dataToSave[$name] = false;
				}
			}
		}

		// Normalize complex variables
		$dataToSave['GEHomepageSuggestedEditsIntroLinks'] =
			$this->normalizeSuggestedEditsIntroLinks( $data );
		$dataToSave['GEHelpPanelLinks'] = $this->normalizeHelpPanelLinks( $data );

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
