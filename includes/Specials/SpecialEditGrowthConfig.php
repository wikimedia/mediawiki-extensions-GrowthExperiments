<?php

namespace GrowthExperiments\Specials;

use FormSpecialPage;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\Validation\GrowthConfigValidation;
use GrowthExperiments\Config\Validation\NewcomerTasksValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\HomepageModules\Banner;
use Html;
use HTMLForm;
use MediaWiki\Revision\RevisionLookup;
use MWTimestamp;
use OOUI\ButtonWidget;
use OOUI\IconWidget;
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
	/** @var string Right required to write */
	private const REQUIRED_RIGHT_TO_WRITE = 'editinterface';

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

	/** @var bool */
	private $userCanWrite;

	/**
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 * @param PageProps $pageProps
	 * @param ILoadBalancer $loadBalancer
	 * @param WikiPageConfigLoader $configLoader
	 * @param WikiPageConfigWriterFactory $configWriterFactory
	 * @param GrowthExperimentsMultiConfig $growthWikiConfig
	 */
	public function __construct(
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup,
		PageProps $pageProps,
		ILoadBalancer $loadBalancer,
		WikiPageConfigLoader $configLoader,
		WikiPageConfigWriterFactory $configWriterFactory,
		GrowthExperimentsMultiConfig $growthWikiConfig
	) {
		parent::__construct( 'EditGrowthConfig' );

		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
		$this->pageProps = $pageProps;
		$this->loadBalancer = $loadBalancer;
		$this->configLoader = $configLoader;
		$this->configWriterFactory = $configWriterFactory;
		$this->growthWikiConfig = $growthWikiConfig;
	}

	/**
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$this->getOutput()->enableOOUI();
		$config = $this->getConfig();
		$this->setConfigPage(
			'geconfig',
			$config->get( 'GEWikiConfigPageTitle' )
		);
		$this->setConfigPage(
			'newcomertasks',
			$config->get( 'GENewcomerTasksConfigTitle' )
		);

		$this->userCanWrite = $this->getAuthority()->isAllowed( self::REQUIRED_RIGHT_TO_WRITE );

		parent::execute( $par );
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
		return $this->userCanWrite;
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return $this->userCanWrite;
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
			return $this->msg( $this->errorMsgKey )->escaped();
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

		if ( $this->userCanWrite ) {
			$form->addPreText( $this->msg(
				'growthexperiments-edit-config-pretext',
				\Message::listParam( array_map( static function ( Title $title ) {
					return '[[' . $title->getPrefixedText() . ']]';
				}, array_values( $this->configPages ) ) )
			)->parseAsBlock() );
			$form->addPreText( $this->msg(
				'growthexperiments-edit-config-pretext-banner',
				$this->titleFactory->newFromText(
					Banner::MESSAGE_KEY,
					NS_MEDIAWIKI
				)->getPrefixedText()
			)->parseAsBlock() );
		} else {
			$form->addPreText( $this->msg( 'growthexperiments-edit-config-pretext-unprivileged' ) );
		}

		// Add last updated data
		foreach ( $this->configPages as $configType => $configTitle ) {
			$revision = $this->revisionLookup->getRevisionByTitle( $configTitle );
			if ( $revision !== null ) {
				$lastRevisionUser = $revision->getUser();
				$diffLink = $configTitle->getFullURL( [ 'oldid' => $revision->getId(), 'diff' => 'prev' ] );
				if ( $lastRevisionUser !== null ) {
					$form->addPreText( $this->msg(
						'growthexperiments-edit-config-last-edit',
						$lastRevisionUser,
						MWTimestamp::getInstance( $revision->getTimestamp() )
							->getRelativeTimestamp(),
						$configTitle->getPrefixedText(),
						$diffLink
					)->parseAsBlock() );
				} else {
					$form->addPreText( $this->msg(
						'growthexperiments-edit-config-last-edit-unknown-user',
						MWTimestamp::getInstance( $revision->getTimestamp() )
							->getRelativeTimestamp(),
						$configTitle->getPrefixedText(),
						$diffLink
					)->parseAsBlock() );
				}
			}
		}

		$form->addPreText( $this->getFeedbackHtml() );

		if ( !$this->userCanWrite ) {
			$form->suppressDefaultSubmit( true );
		}
	}

	private function getRawDescriptors(): array {
		// Whether the various pages configured as help links etc. must exist.
		$pagesMustExist = !$this->getConfig()->get( 'GEDeveloperSetup' );

		$descriptors = [
			// Growth experiments config (stored in MediaWiki:GrowthExperimentsConfig.json)
			'geconfig-GEHomepageSuggestedEditsIntroLinks-create' => [
				'type' => 'title',
				'exists' => $pagesMustExist,
				'interwiki' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-intro-links-create',
				'required' => true,
				'section' => 'homepage',
			],
			'geconfig-GEHomepageSuggestedEditsIntroLinks-image' => [
				'type' => 'title',
				'exists' => $pagesMustExist,
				'interwiki' => true,
				'label-message' => 'growthexperiments-edit-config-homepage-intro-links-image',
				'required' => true,
				'section' => 'homepage',
			],
			'geconfig-mentorship-description' => [
				'type' => 'info',
				'label-message' => 'growthexperiments-edit-config-mentorship-description',
				'section' => 'mentorship',
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

			// Description for suggested edits config
			'newcomertasks-section-description' => [
				'type' => 'info',
				'label-message' => 'growthexperiments-edit-config-newcomer-tasks-description',
				'section' => 'newcomertasks',
			],
		];

		$descriptors = array_merge( $descriptors, [
			'geconfig-GEInfoboxTemplates' => [
				'type' => 'titlesmultiselect',
				'exists' => $pagesMustExist,
				'placeholder' => $this->msg( 'nstab-template' )->text() . ':Infobox',
				'namespace' => NS_TEMPLATE,
				// TODO: This should be relative => true in an ideal world, see T285750 and
				// T285748 for blockers
				'relative' => false,
				'max' => GrowthConfigValidation::MAX_TEMPLATES_IN_COLLECTION,
				'label-message' => $this->msg(
					'growthexperiments-edit-config-newcomer-tasks-infobox-templates'
				),
				'help' => $this->msg( 'growthexperiments-edit-config-newcomer-tasks-infobox-templates-help' )->parse(),
				'required' => false,
				'section' => 'newcomertasks',
			]
		] );

		// Add fields for suggested edits config (stored in MediaWiki:NewcomerTasks.json)
		foreach ( NewcomerTasksValidator::SUGGESTED_EDITS_TASK_TYPES as $taskType => $taskTypeData ) {
			$isMachineSuggestionTaskType = in_array(
				$taskType,
				NewcomerTasksValidator::SUGGESTED_EDITS_MACHINE_SUGGESTIONS_TASK_TYPES
			);
			$descriptors["newcomertasks-${taskType}Info"] = [
				'type' => 'info',
				// TODO: It looks nicer to have each task type in its own section, but that's a bigger
				// reorganization.
				'default' => '<h3>' . new IconWidget( [ 'icon' => $taskTypeData['icon'] ] ) . '  ' .
					$this->msg( "growthexperiments-homepage-suggestededits-tasktype-name-$taskType" )->parse() .
					'</h3>',
				'raw' => true,
				'section' => 'newcomertasks',
			];
			$descriptors["newcomertasks-${taskType}Disabled"] = [
				'type' => 'check',
				'label-message' => 'growthexperiments-edit-config-newcomer-tasks-disabled',
				'section' => 'newcomertasks',
			];
			$descriptors["newcomertasks-${taskType}Templates"] = [
				'type' => 'titlesmultiselect',
				'disabled' => $isMachineSuggestionTaskType,
				'exists' => $pagesMustExist,
				'namespace' => NS_TEMPLATE,
				// TODO: This should be relative => true in an ideal world, see T285750 and
				// T285748 for blockers
				'relative' => false,
				'label-message' => $isMachineSuggestionTaskType ?
					"growthexperiments-edit-config-newcomer-tasks-machine-suggestions-no-templates" :
					"growthexperiments-edit-config-newcomer-tasks-$taskType-templates",
				'required' => false,
				'section' => 'newcomertasks'
			];
			$descriptors["newcomertasks-${taskType}ExcludedTemplates"] = [
				'type' => 'titlesmultiselect',
				'exists' => $pagesMustExist,
				'namespace' => NS_TEMPLATE,
				// TODO: This should be relative => true in an ideal world, see T285750 and
				// T285748 for blockers
				'relative' => false,
				'label-message' => $this->msg(
					"growthexperiments-edit-config-newcomer-tasks-excluded-templates"
				),
				'required' => false,
				'section' => 'newcomertasks'
			];
			$descriptors["newcomertasks-${taskType}ExcludedCategories"] = [
				'type' => 'titlesmultiselect',
				'exists' => $pagesMustExist,
				'namespace' => NS_CATEGORY,
				// TODO: This should be relative => true in an ideal world, see T285750 and
				// T285748 for blockers
				'relative' => false,
				'label-message' => $this->msg(
					"growthexperiments-edit-config-newcomer-tasks-excluded-categories"
				),
				'required' => false,
				'section' => 'newcomertasks'
			];
			$descriptors["newcomertasks-${taskType}Learnmore"] = [
				'type' => 'title',
				'interwiki' => true,
				'exists' => $pagesMustExist,
				'label-message' => "growthexperiments-edit-config-newcomer-tasks-$taskType-learnmore",
				'required' => false,
				'section' => 'newcomertasks'
			];
		}

		$descriptors = array_merge( $descriptors, [
			'geconfig-help-panel-description' => [
				'type' => 'info',
				'label-message' => 'growthexperiments-edit-config-help-panel-description',
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelExcludedNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-disabled-namespaces',
				'section' => 'help-panel',
			],
			'geconfig-GEHelpPanelReadingModeNamespaces' => [
				'type' => 'namespacesmultiselect',
				'exists' => true,
				'autocomplete' => false,
				'label-message' => 'growthexperiments-edit-config-help-panel-reading-namespaces',
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
			'geconfig-GEHelpPanelHelpDeskTitle' => [
				'type' => 'title',
				'exists' => $pagesMustExist,
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
			'geconfig-GEHelpPanelLinks-description' => [
				'type' => 'info',
				'label-message' => 'growthexperiments-edit-config-help-panel-links-description',
				'section' => 'help-panel-links',
			],
		] );

		foreach ( [ 'mos', 'editing', 'images', 'references', 'articlewizard' ] as $position => $type ) {
			$descriptors = array_merge( $descriptors, [
				"geconfig-GEHelpPanelLinks-$position-title" => [
					'type' => 'title',
					'label-message' => "growthexperiments-edit-config-help-panel-links-$type-title",
					'section' => 'help-panel-links',
					'required' => false,
					'exists' => $pagesMustExist,
					'interwiki' => true,
				],
				"geconfig-GEHelpPanelLinks-$position-label" => [
					'type' => 'text',
					'label-message' => "growthexperiments-edit-config-help-panel-links-$type-label",
					'section' => 'help-panel-links',
				],
			] );
		}

		$descriptors = array_merge( $descriptors, [
			'geconfig-GEHelpPanelViewMoreTitle' => [
				'type' => 'title',
				'exists' => $pagesMustExist,
				'label-message' => 'growthexperiments-edit-config-help-panel-view-more',
				'required' => false,
				'interwiki' => true,
				'section' => 'help-panel-links',
			],
		] );

		if ( !$this->userCanWrite ) {
			foreach ( $descriptors as $key => $descriptor ) {
				$descriptors[$key]['disabled'] = true;
			}
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
			GrowthExperimentsMultiConfig::READ_UNCACHED
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
			WikiPageConfigLoader::READ_UNCACHED
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
			$descriptors["newcomertasks-${taskType}Disabled"]['default']
				= !empty( $newcomerTasksConfig[$taskType]['disabled'] );
			$descriptors["newcomertasks-${taskType}Templates"]['default'] = implode(
				"\n",
				array_map( function ( $rawTitle ) {
					return $this->titleFactory
						->newFromTextThrow( $rawTitle, NS_TEMPLATE )
						->getPrefixedText();
				}, $newcomerTasksConfig[$taskType]['templates'] ?? [] )
			);
			$descriptors["newcomertasks-${taskType}ExcludedTemplates"]['default'] = implode(
				"\n",
				array_map( function ( $rawTitle ) {
					return $this->titleFactory
						->newFromTextThrow( $rawTitle, NS_TEMPLATE )
						->getPrefixedText();
				}, $newcomerTasksConfig[$taskType]['excludedTemplates'] ?? [] )
			);
			$descriptors["newcomertasks-${taskType}ExcludedCategories"]['default'] = implode(
				"\n",
				array_map( function ( $rawTitle ) {
					return $this->titleFactory
						->newFromTextThrow( $rawTitle, NS_CATEGORY )
						->getPrefixedText();
				}, $newcomerTasksConfig[$taskType]['excludedCategories'] ?? [] )
			);
			$descriptors["newcomertasks-${taskType}Learnmore"]['default'] =
				$newcomerTasksConfig[$taskType]['learnmore'] ?? '';
		}

		// Add default values for intro links
		$linkValues = $this->growthWikiConfig->getWithFlags(
			'GEHomepageSuggestedEditsIntroLinks',
			GrowthExperimentsMultiConfig::READ_UNCACHED
		);
		foreach ( self::SUGGESTED_EDITS_INTRO_LINKS as $link ) {
			$descriptors["geconfig-GEHomepageSuggestedEditsIntroLinks-$link"]['default'] =
				$linkValues[$link];
		}

		// Add default values for help panel links
		$helpPanelLinks = $this->growthWikiConfig->get( 'GEHelpPanelLinks' );
		foreach ( $helpPanelLinks as $i => $link ) {
			if (
				isset( $descriptors["geconfig-GEHelpPanelLinks-$i-title"] ) &&
				isset( $descriptors["geconfig-GEHelpPanelLinks-$i-label"] )
			) {
				$descriptors["geconfig-GEHelpPanelLinks-$i-title"]['default'] = $link['title'];
				$descriptors["geconfig-GEHelpPanelLinks-$i-label"]['default'] = $link['text'];
			}
		}

		// Add edit summary field, if user can write
		if ( $this->userCanWrite ) {
			$descriptors['summary'] = [
				'type' => 'text',
				'label-message' => 'growthexperiments-edit-config-edit-summary',
			];
		}
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

			$linkId = null;
			$title = $this->titleFactory->newFromText( $data["geconfig-GEHelpPanelLinks-$i-title"] );
			if ( $title !== null && $title->exists() && !$title->isExternal() ) {
				$props = $this->pageProps->getProperties( $title, 'wikibase_item' );
				$pageId = $title->getId();
				if ( array_key_exists( $pageId, $props ) ) {
					$linkId = $props[$pageId];
				}
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

			// Ignore fields with dashes except for newcomertasks, where task types
			// can have a dash, e.g. 'link-recommendation'
			if ( $prefix === 'newcomertasks' || strpos( $name, '-' ) === false ) {
				$dataToSave[$prefix][$name] = $data[$nameRaw] ?? 'false';

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
		foreach ( NewcomerTasksValidator::SUGGESTED_EDITS_TASK_TYPES as $taskType => $taskTypeData ) {
			$templates = array_map( static function ( Title $title ) {
				return $title->getText();
			}, $this->normalizeTitleList( $data["${taskType}Templates"] ?? null ) );
			if ( $templates === [] &&
				!in_array( $taskType, NewcomerTasksValidator::SUGGESTED_EDITS_MACHINE_SUGGESTIONS_TASK_TYPES )
			) {
				// Do not save template-based tasks with no templates
				continue;
			}
			$excludedTemplates = array_map( static function ( Title $title ) {
				return $title->getText();
			}, $this->normalizeTitleList( $data["${taskType}ExcludedTemplates"] ?? null ) );

			$excludedCategories = array_map( static function ( Title $title ) {
				return $title->getText();
			}, $this->normalizeTitleList( $data["${taskType}ExcludedCategories"] ?? null ) );

			$normalizedData[$taskType] = [
				'disabled' => (bool)$data["${taskType}Disabled"],
				'group' => $taskTypeData['difficulty'],
				'templates' => $templates,
				'excludedTemplates' => $excludedTemplates,
				'excludedCategories' => $excludedCategories,
				'type' => $taskTypeData['handler-id'],
			];

			// Add learnmore link if specified
			if ( isset( $data["${taskType}Learnmore"] ) ) {
				$normalizedData[$taskType]['learnmore'] = $data["${taskType}Learnmore"];
			}
		}

		return $normalizedData;
	}

	/**
	 * Helper method for normalizeSuggestedEditsConfig()
	 * @param string|null $list
	 * @return Title[] List of valid titles
	 */
	private function normalizeTitleList( ?string $list ) {
		if ( $list === null || $list === '' ) {
			return [];
		}
		return array_values( array_filter( array_map( function ( string $titleText ) {
			$title = $this->titleFactory->newFromText( $titleText );
			if ( $title === null ) {
				return null;
			}
			return $title;
		}, explode( "\n", $list ) ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		// DO NOT rely on userCanWrite here, in case its value is wrong for some weird reason
		if ( !$this->getAuthority()->isAllowed( self::REQUIRED_RIGHT_TO_WRITE ) ) {
			throw new PermissionsError( self::REQUIRED_RIGHT_TO_WRITE );
		}

		$dataToSave = $this->preprocessSubmittedData( $data );

		// Normalize complex variables
		$dataToSave['geconfig']['GEHomepageSuggestedEditsIntroLinks'] =
			$this->normalizeSuggestedEditsIntroLinks( $data );
		$dataToSave['geconfig']['GEHelpPanelLinks'] = $this->normalizeHelpPanelLinks( $data );
		$dataToSave['geconfig']['GEInfoboxTemplates'] = array_map( static function ( Title $title ) {
			return $title->getText();
		}, $this->normalizeTitleList( $data['geconfig-GEInfoboxTemplates'] ?? null ) );

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
		$out = $this->getOutput();

		// Add success message
		$out->addWikiMsg( 'growthexperiments-edit-config-config-changed' );
		$out->addWikiMsg( 'growthexperiments-edit-config-return-to-form' );

		// Ask for feedback
		$out->addHTML( $this->getFeedbackHtml() );
	}

	/**
	 * Add feedback CTA to the output
	 *
	 * @return string HTML to add to the output
	 */
	private function getFeedbackHtml(): string {
		$this->getOutput()->addModuleStyles( 'oojs-ui.styles.icons-interactions' );
		return Html::rawElement( 'div', [], implode( "\n", [
			Html::rawElement(
				'h3',
				[],
				$this->msg( 'growthexperiments-edit-config-feedback-headline' )
			),
			new ButtonWidget( [
				'icon' => 'feedback',
				'label' => $this->msg( 'growthexperiments-edit-config-feedback-cta' ),
				'href' => 'https://www.mediawiki.org/wiki/Talk:Growth',
				'flags' => [ 'primary', 'progressive' ]
			] )
		] ) );
	}
}
