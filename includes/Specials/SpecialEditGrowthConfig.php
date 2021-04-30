<?php

namespace GrowthExperiments\Specials;

use Config;
use FormSpecialPage;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\Validation\NewcomerTasksValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use HTMLForm;
use MediaWiki\Revision\RevisionLookup;
use MWTimestamp;
use PageProps;
use PermissionsError;
use Status;
use Title;
use TitleFactory;
use User;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class SpecialEditGrowthConfig extends FormSpecialPage {
	/** @var string[] */
	private const SUGGESTED_EDITS_INTRO_LINKS = [ 'create', 'image' ];

	/** @var string[] Keys that will be present in $configPages */
	private const CONFIG_PAGES_KEYS = [ 'geconfig', 'newcomertasks' ];

	/** @var TitleFactory */
	private $titleFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var PageProps */
	private $pageProps;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var WikiPageConfigLoader */
	private $configLoader;

	/** @var WikiPageConfigWriterFactory */
	private $configWriterFactory;

	/** @var GrowthExperimentsMultiConfig */
	private $growthWikiConfig;

	/** @var string|null */
	private $errorMsgKey;

	/**
	 * @var Title[]
	 *
	 * All keys listed in CONFIG_PAGES_KEYS will be present,
	 * unless $errorMsgKey is not null (in which case the special page
	 * short-circuits anyway).
	 */
	private $configPages = [];

	/**
	 * @param Config $config We can't use getConfig() in constructor, as context is not yet set
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 * @param PageProps $pageProps
	 * @param ILoadBalancer $loadBalancer
	 * @param WikiPageConfigLoader $configLoader
	 * @param WikiPageConfigWriterFactory $configWriterFactory
	 * @param GrowthExperimentsMultiConfig $growthWikiConfig
	 */
	public function __construct(
		Config $config,
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup,
		PageProps $pageProps,
		ILoadBalancer $loadBalancer,
		WikiPageConfigLoader $configLoader,
		WikiPageConfigWriterFactory $configWriterFactory,
		GrowthExperimentsMultiConfig $growthWikiConfig
	) {
		parent::__construct( 'EditGrowthConfig', 'editinterface' );

		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
		$this->pageProps = $pageProps;
		$this->loadBalancer = $loadBalancer;
		$this->configLoader = $configLoader;
		$this->configWriterFactory = $configWriterFactory;
		$this->growthWikiConfig = $growthWikiConfig;

		$this->setConfigPage(
			'geconfig',
			$config->get( 'GEWikiConfigPageTitle' )
		);
		$this->setConfigPage(
			'newcomertasks',
			$config->get( 'GENewcomerTasksConfigTitle' )
		);
	}

	/**
	 * Register a config page
	 *
	 * This validates the config page has proper content model
	 * and that it can be used to store config.
	 *
	 * @param string $key One of keys listed in CONFIG_PAGES_KEYS
	 * @param string $configPage
	 */
	private function setConfigPage( string $key, string $configPage ): void {
		Assert::parameter(
			in_array( $key, self::CONFIG_PAGES_KEYS ),
			'$key',
			'must be one of keys listed in SpecialEditGrowthConfig::CONFIG_PAGES_KEYS'
		);

		$configTitle = $this->titleFactory->newFromText( $configPage );
		if (
			$configTitle === null ||
			!$configTitle->hasContentModel( CONTENT_MODEL_JSON )
		) {
			$this->errorMsgKey = 'growthexperiments-edit-config-error-invalid-title';
			return;
		}

		$this->configPages[$key] = $configTitle;
	}

	/**
	 * Determines if wiki config is enabled
	 *
	 * @return bool
	 */
	private function isWikiConfigEnabled(): bool {
		return $this->growthWikiConfig->isWikiConfigEnabled();
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		// Require both enabled wiki config and user-specific access level to
		// be able to use the special page.
		return $this->isWikiConfigEnabled() && parent::userCanExecute( $user );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError() {
		if ( !$this->isWikiConfigEnabled() ) {
			// Wiki config is disabled, display a meaningful restriction error
			throw new PermissionsError(
				null,
				[ 'growthexperiments-edit-config-disabled' ]
			);
		}

		// Otherwise, defer to the default logic
		parent::displayRestrictionError();
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
	protected function preText() {
		if ( $this->errorMsgKey !== null ) {
			return $this->msg( $this->errorMsgKey )->text();
		}
		return '';
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
			return;
		}

		// Add last updated data
		/** @var Title[] */
		$configTitles = [];
		foreach ( $this->configPages as $configType => $configTitle ) {
			$revision = $this->revisionLookup->getRevisionByTitle( $configTitle );
			if ( $revision !== null ) {
				$lastRevisionUser = $revision->getUser();
				if ( $lastRevisionUser !== null ) {
					$form->addPreText( $this->msg(
						'growthexperiments-edit-config-last-edit',
						$lastRevisionUser,
						MWTimestamp::getInstance( $revision->getTimestamp() )
							->getRelativeTimestamp(),
						$configTitle->getPrefixedText()
					)->parseAsBlock() );
				} else {
					$form->addPreText( $this->msg(
						'growthexperiments-edit-config-last-edit-unknown-user',
						MWTimestamp::getInstance( $revision->getTimestamp() )
							->getRelativeTimestamp(),
						$configTitle->getPrefixedText()
					)->parseAsBlock() );
				}
			}

			$configTitles[] = $configTitle;
		}

		$form->addPreText( $this->msg(
			'growthexperiments-edit-config-pretext',
			\Message::listParam( array_map( function ( Title $title ) {
				return '[[' . $title->getPrefixedText() . ']]';
			}, $configTitles ) )
		)->parseAsBlock() );
	}

	private function getRawDescriptors(): array {
		$descriptors = [
			// Growth experiments config (stored in MediaWiki:GrowthExperimentsConfig.json)
			'geconfig-GEHelpPanelReadingModeNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-reading-namespaces',
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelExcludedNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-disabled-namespaces',
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelHelpDeskTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-help-panel-helpdesk-title',
				'required' => false,
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelHelpDeskPostOnTop' => [
				'type' => 'radio',
				'label-message' => 'growthexperiments-edit-config-help-panel-post-on-top',
				'options-messages' => [
					'growthexperiments-edit-config-help-panel-post-on-top-true' => 'true',
					'growthexperiments-edit-config-help-panel-post-on-top-false' => 'false',
				],
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelViewMoreTitle' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-help-panel-view-more',
				'required' => false,
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelSearchNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-searched-namespaces',
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelAskMentor' => [
				'type' => 'radio',
				'label-message' => 'growthexperiments-edit-config-help-panel-ask-mentor',
				'options-messages' => [
					'growthexperiments-edit-config-help-panel-ask-mentor-true' => 'true',
					'growthexperiments-edit-config-help-panel-ask-mentor-false' => 'false',
				],
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelLinks-0-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-mos-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'geconfig-GEHelpPanelLinks-0-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-mos-label',
				'section' => 'help-panel-links',
			],
			'geconfig-GEHelpPanelLinks-1-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-editing-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'geconfig-GEHelpPanelLinks-1-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-editing-label',
				'section' => 'help-panel-links',
			],
			'geconfig-GEHelpPanelLinks-2-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-images-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'geconfig-GEHelpPanelLinks-2-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-images-label',
				'section' => 'help-panel-links',
			],
			'geconfig-GEHelpPanelLinks-3-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-references-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'geconfig-GEHelpPanelLinks-3-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-references-label',
				'section' => 'help-panel-links',
			],
			'geconfig-GEHelpPanelLinks-4-title' => [
				'type' => 'title',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-articlewizard-title',
				'section' => 'help-panel-links',
				'required' => false,
				'exists' => true,
			],
			'geconfig-GEHelpPanelLinks-4-label' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-articlewizard-label',
				'section' => 'help-panel-links',
			],
			'geconfig-GEHomepageSuggestedEditsIntroLinks-create' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-intro-links-create',
				'required' => true,
				'section' => 'homepage',
			],
			'geconfig-GEHomepageSuggestedEditsIntroLinks-image' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-intro-links-image',
				'required' => true,
				'section' => 'homepage',
			],
			'geconfig-GEMentorshipEnabled' => [
				'type' => 'radio',
				'label-message' => 'growthexperiments-edit-config-mentorship-enabled',
				'options-messages' => [
					'growthexperiments-edit-config-mentorship-enabled-true' => 'true',
					'growthexperiments-edit-config-mentorship-enabled-false' => 'false',
				],
				'section' => 'mentorship',
			],
			'geconfig-GEHomepageMentorsList' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-mentorship-list-of-auto-assigned-mentors',
				'required' => false,
				'section' => 'mentorship',
			],
			'geconfig-GEHomepageManualAssignmentMentorsList' => [
				'type' => 'title',
				'exists' => true,
				'label-message' => 'growthexperiments-edit-config-mentorship-list-of-manually-assigned-mentors',
				'required' => false,
				'section' => 'mentorship',
			],
		];

		// Add fields for suggested edits config (stored in MediaWiki:NewcomerTasks.json)
		foreach ( NewcomerTasksValidator::SUGGESTED_EDITS_TASK_TYPES as $taskType => $group ) {
			$descriptors["newcomertasks-${taskType}Templates"] = [
				'type' => 'titlesmultiselect',
				'exists' => true,
				'namespace' => NS_TEMPLATE,
				'relative' => true,
				'label-message' => "growthexperiments-edit-config-newcomer-tasks-$taskType-templates",
				'required' => false,
				'section' => 'newcomertasks'
			];
			$descriptors["newcomertasks-${taskType}Learnmore"] = [
				'type' => 'title',
				'exists' => true,
				'label-message' => "growthexperiments-edit-config-newcomer-tasks-$taskType-learnmore",
				'required' => false,
				'section' => 'newcomertasks'
			];
		}

		return $descriptors;
	}

	/**
	 * Provide current value for a GrowthExperimentsMultiConfig variable
	 *
	 * @param string $name
	 * @return string|null
	 */
	private function getValueGeConfig( string $name ): ?string {
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

		return $default;
	}

	/**
	 * Get (uncached) newcomer tasks config
	 *
	 * @return array
	 */
	private function getNewcomerTasksConfig(): array {
		$title = $this->titleFactory->newFromText(
			$this->getConfig()->get( 'GENewcomerTasksConfigTitle' )
		);
		if ( $title === null || !$title->exists() ) {
			return [];
		}

		$res = $this->configLoader->load(
			$title,
			WikiPageConfigLoader::READ_LATEST
		);
		if ( !is_array( $res ) ) {
			// TODO: Maybe log the failure?
			return [];
		}

		return $res;
	}

	/**
	 * Get config type from a form field name
	 *
	 * Form field names are always $configType-$configName, where
	 * $configType refers to the config page the variable is set in and
	 * $configName is the variable name.
	 *
	 * @param string $nameRaw
	 * @return string[]
	 */
	private function getPrefixAndName( string $nameRaw ): array {
		return explode( '-', $nameRaw, 2 );
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

		// Add default values for geconfig variables
		foreach ( $descriptors as $nameRaw => $descriptor ) {
			list( $prefix, $name ) = $this->getPrefixAndName( $nameRaw );
			if ( strpos( $name, '-' ) !== false ) {
				// Non-standard field, will be handled later in this method
				continue;
			}

			if ( $prefix === 'geconfig' ) {
				$default = $this->getValueGeConfig( $name );
				if ( $default !== null ) {
					$descriptors[$nameRaw]['default'] = $default;
				}
			}
		}
		// Add default values for newcomertasks variables
		$newcomerTasksConfig = $this->getNewcomerTasksConfig();
		foreach ( NewcomerTasksValidator::SUGGESTED_EDITS_TASK_TYPES as $taskType => $group ) {
			$descriptors["newcomertasks-${taskType}Templates"]['default'] = implode(
				"\n",
				$newcomerTasksConfig[$taskType]['templates'] ?? []
			);
			$descriptors["newcomertasks-${taskType}Learnmore"]['default'] =
				$newcomerTasksConfig[$taskType]['learnmore'] ?? '';
		}

		// Add default values for intro links
		$linkValues = $this->growthWikiConfig->getWithFlags(
			'GEHomepageSuggestedEditsIntroLinks',
			GrowthExperimentsMultiConfig::READ_LATEST
		);
		foreach ( self::SUGGESTED_EDITS_INTRO_LINKS as $link ) {
			$descriptors["geconfig-GEHomepageSuggestedEditsIntroLinks-$link"]['default'] =
				$linkValues[$link];
		}

		// Add default values for help panel links
		$helpPanelLinks = $this->growthWikiConfig->get( 'GEHelpPanelLinks' );
		for ( $i = 0; $i < count( $helpPanelLinks ); $i++ ) {
			if (
				isset( $descriptors["geconfig-GEHelpPanelLinks-$i-title"] ) &&
				isset( $descriptors["geconfig-GEHelpPanelLinks-$i-label"] )
			) {
				$descriptors["geconfig-GEHelpPanelLinks-$i-title"]['default'] = $helpPanelLinks[$i]['title'];
				$descriptors["geconfig-GEHelpPanelLinks-$i-label"]['default'] = $helpPanelLinks[$i]['text'];
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
			$links[$link] = $data["geconfig-GEHomepageSuggestedEditsIntroLinks-$link"];
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
				$data["geconfig-GEHelpPanelLinks-$i-title"] == '' ||
				$data["geconfig-GEHelpPanelLinks-$i-label"] == ''
			) {
				continue;
			}

			$title = $this->titleFactory->newFromText( $data["geconfig-GEHelpPanelLinks-$i-title"] );
			$props = ( $title !== null && $title->exists() && !$title->isExternal() ) ?
				$this->pageProps->getProperties( $title, 'wikibase_item' ) :
				[];
			$linkId = null;
			$pageId = $title->getId();
			if ( in_array( $pageId, $props ) ) {
				$linkId = $props[$pageId];
			}
			$res[] = [
				'title' => $data["geconfig-GEHelpPanelLinks-$i-title"],
				'text' => $data["geconfig-GEHelpPanelLinks-$i-label"],
				'id' => $linkId ?? $title->getPrefixedDBkey(),
			];
		}
		return $res;
	}

	/**
	 * Helper function that preprocesses submitted data
	 *
	 * This function:
	 *   * normalizes namespaces into arrays
	 *   * normalizes string true/false variables to actual booleans
	 *   * ignores "complex fields" (fields having - in their name) and field for edit summary
	 *   * splits variables by config type (one for each config page)
	 *
	 * @param array $data
	 * @return array
	 */
	private function preprocessSubmittedData( array $data ): array {
		$dataToSave = [];
		foreach ( $this->getFormFields() as $nameRaw => $descriptor ) {
			if ( $nameRaw === 'summary' ) {
				continue;
			}

			list( $prefix, $name ) = $this->getPrefixAndName( $nameRaw );

			if ( $descriptor['type'] === 'namespacesmultiselect' ) {
				if ( $data[$nameRaw] === '' ) {
					$data[$nameRaw] = [];
				} else {
					$data[$nameRaw] = array_map(
						'intval',
						explode( "\n", $data[$nameRaw] )
					);
				}
			}

			if ( strpos( $name, '-' ) === false ) {
				$dataToSave[$prefix][$name] = $data[$nameRaw];

				// Basic normalization
				if ( $dataToSave[$prefix][$name] === 'true' ) {
					$dataToSave[$prefix][$name] = true;
				} elseif ( $dataToSave[$prefix][$name] === 'false' ) {
					$dataToSave[$prefix][$name] = false;
				}
			}
		}
		return $dataToSave;
	}

	/**
	 * Normalize configuration used in NewcomerTasks.json config file
	 *
	 * This function converts form fields into array that's then stored
	 * in the JSON file.
	 *
	 * @param array $data
	 * @return array
	 */
	private function normalizeSuggestedEditsConfig( array $data ): array {
		$normalizedData = [];
		foreach ( NewcomerTasksValidator::SUGGESTED_EDITS_TASK_TYPES as $taskType => $group ) {
			$templates = array_filter( array_map( function ( string $template ) {
				$title = $this->titleFactory->newFromText( $template );
				if ( $title === null ) {
					return null;
				}
				return $title->getText();
			}, explode( "\n", $data["${taskType}Templates"] ) ) );
			if ( $templates === [] ) {
				// Do not save tasks with no templates
				continue;
			}
			$normalizedData[$taskType] = [
				'group' => $group,
				'templates' => $templates
			];

			// Add learnmore link if specified
			if ( $data["${taskType}Learnmore"] !== '' ) {
				$normalizedData[$taskType]['learnmore'] = $data["${taskType}Learnmore"];
			}
		}
		return $normalizedData;
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$dataToSave = $this->preprocessSubmittedData( $data );

		// Normalize complex variables
		$dataToSave['geconfig']['GEHomepageSuggestedEditsIntroLinks'] =
			$this->normalizeSuggestedEditsIntroLinks( $data );
		$dataToSave['geconfig']['GEHelpPanelLinks'] = $this->normalizeHelpPanelLinks( $data );

		// Normalize suggested edits configuration
		$dataToSave['newcomertasks'] = $this->normalizeSuggestedEditsConfig( $dataToSave['newcomertasks'] );

		// Start atomic section; we can end up editing multiple pages here,
		// with some edits failing and other succeeding. We want to either save everything,
		// or nothing.
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__, IDatabase::ATOMIC_CANCELABLE );

		// Actually save the edits
		$status = Status::newGood();
		foreach ( $dataToSave as $configType => $configData ) {
			$configWriter = $this->configWriterFactory
				->newWikiPageConfigWriter( $this->configPages[$configType], $this->getUser() );
			$configWriter->setVariables( $configData );
			$status->merge( $configWriter->save( $data['summary'] ) );
		}

		// End atomic section if all edits succeeded, cancel it otherwise
		if ( $status->isOK() ) {
			$dbw->endAtomic( __METHOD__ );
		} else {
			$dbw->cancelAtomic( __METHOD__ );
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'growthexperiments-edit-config-config-changed' );
		$this->getOutput()->addWikiMsg( 'growthexperiments-edit-config-return-to-form' );
	}
}
