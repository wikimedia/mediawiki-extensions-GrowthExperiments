<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModules\SuggestedEditsComponents\CardWrapper;
use GrowthExperiments\HomepageModules\SuggestedEditsComponents\NavigationWidgetFactory;
use GrowthExperiments\HomepageModules\SuggestedEditsComponents\TaskExplanationWidget;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\PageViewInfo\PageViewService;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use OOUI\ButtonGroupWidget;
use OOUI\ButtonWidget;
use OOUI\Exception;
use OOUI\HtmlSnippet;
use OOUI\IconWidget;
use OOUI\Tag;
use RuntimeException;
use StatusValue;
use Wikimedia\Message\MessageParam;
use Wikimedia\Stats\StatsFactory;

/**
 * Homepage module that displays a list of recommended tasks.
 * This is JS-only functionality; most of the logic is in the
 * ext.growthExperiments.Homepage.SuggestedEdits module.
 */
class SuggestedEdits extends BaseModule {

	/**
	 * User preference to track that suggested edits should be shown to the user (instead of an
	 * onboarding dialog). Might be ignored in some situations.
	 */
	public const ACTIVATED_PREF = 'growthexperiments-homepage-suggestededits-activated';
	/** User preference to track that suggested edits were enabled this user automatically on signup. Not used. */
	public const PREACTIVATED_PREF = 'growthexperiments-homepage-suggestededits-preactivated';
	/** User preference used to remember the user's topic selection, when using morelike topics. */
	public const TOPICS_PREF = 'growthexperiments-homepage-se-topic-filters';
	/** User preference used to remember the user's topic selection, when using ORES topics. */
	public const TOPICS_ORES_PREF = 'growthexperiments-homepage-se-ores-topic-filters';
	/** User preference used to remember the user's topic mode selection, when using any type of topics. */
	public const TOPICS_MATCH_MODE_PREF = 'growthexperiments-homepage-se-topic-filters-mode';
	/** User preference used to remember the user's task type selection. */
	public const TASKTYPES_PREF = 'growthexperiments-homepage-se-filters';
	/** User preference for opting into guidance, when $wgGENewcomerTasksGuidanceRequiresOptIn is true. */
	public const GUIDANCE_ENABLED_PREF = 'growthexperiments-guidance-enabled';
	/**
	 * Default value for TASKTYPES_PREF.
	 *
	 * Depending on whether link recommendations are available for the wiki, either 'links' or 'link-recommendation'
	 * will be shown, see NewcomerTasksUserOptionsLookup::getTaskTypeFilter().
	 */
	public const DEFAULT_TASK_TYPES = [ 'copyedit', 'links', 'link-recommendation' ];

	/**
	 * Used to keep track of the state of user interactions with suggested edits per type per skin.
	 * See also HomepageHooks::onLocalUserCreated
	 */
	public const GUIDANCE_BLUE_DOT_PREF =
		'growthexperiments-homepage-suggestededits-guidance-blue-dot';

	/**
	 * Used to keep track of the whether the user has opted out of seeing Add a Link onboarding
	 */
	public const ADD_LINK_ONBOARDING_PREF = 'growthexperiments-addlink-onboarding';

	/**
	 * Used to keep track of the whether the user has opted out of seeing Add an Image onboarding
	 */
	public const ADD_IMAGE_ONBOARDING_PREF = 'growthexperiments-addimage-onboarding';

	/**
	 * Used to keep track of the whether the user has opted out of seeing onboarding for
	 * the caption step of Add Image
	 */
	public const ADD_IMAGE_CAPTION_ONBOARDING_PREF = 'growthexperiments-addimage-caption-onboarding';

	/**
	 * Used to keep track of the whether the user has opted out of seeing "Add a section image" onboarding
	 */
	public const ADD_SECTION_IMAGE_ONBOARDING_PREF = 'growthexperiments-addsectionimage-onboarding';

	/**
	 * Used to keep track of the whether the user has opted out of seeing onboarding for
	 * the caption step of Add Section Image
	 */
	public const ADD_SECTION_IMAGE_CAPTION_ONBOARDING_PREF = 'growthexperiments-addsectionimage-caption-onboarding';

	private ?PageViewService $pageViewService;

	private ConfigurationLoader $configurationLoader;

	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;

	private TaskSuggester $taskSuggester;

	private TitleFactory $titleFactory;

	private ProtectionFilter $protectionFilter;

	/** @var string[] cache key => HTML */
	private array $htmlCache = [];

	/** @var TaskSet|StatusValue */
	private $tasks;

	private ButtonGroupWidget $buttonGroupWidget;

	private UserOptionsLookup $userOptionsLookup;

	private ?NavigationWidgetFactory $navigationWidgetFactory = null;

	private LinkRecommendationFilter $linkRecommendationFilter;

	private ImageRecommendationFilter $imageRecommendationFilter;

	private CampaignConfig $campaignConfig;

	private StatsFactory $statsFactory;

	/**
	 * @param IContextSource $context
	 * @param Config $wikiConfig
	 * @param CampaignConfig $campaignConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param PageViewService|null $pageViewService
	 * @param ConfigurationLoader $configurationLoader
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param TaskSuggester $taskSuggester
	 * @param TitleFactory $titleFactory
	 * @param ProtectionFilter $protectionFilter
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param LinkRecommendationFilter $linkRecommendationFilter
	 * @param ImageRecommendationFilter $imageRecommendationFilter
	 * @param StatsFactory $statsFactory
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		CampaignConfig $campaignConfig,
		ExperimentUserManager $experimentUserManager,
		?PageViewService $pageViewService,
		ConfigurationLoader $configurationLoader,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		TaskSuggester $taskSuggester,
		TitleFactory $titleFactory,
		ProtectionFilter $protectionFilter,
		UserOptionsLookup $userOptionsLookup,
		LinkRecommendationFilter $linkRecommendationFilter,
		ImageRecommendationFilter $imageRecommendationFilter,
		StatsFactory $statsFactory
	) {
		parent::__construct( 'suggested-edits', $context, $wikiConfig, $experimentUserManager );
		$this->pageViewService = $pageViewService;
		$this->configurationLoader = $configurationLoader;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->taskSuggester = $taskSuggester;
		$this->titleFactory = $titleFactory;
		$this->protectionFilter = $protectionFilter;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->linkRecommendationFilter = $linkRecommendationFilter;
		$this->imageRecommendationFilter = $imageRecommendationFilter;
		$this->campaignConfig = $campaignConfig;
		$this->statsFactory = $statsFactory;
	}

	/** @inheritDoc */
	protected function getHeaderTextElement() {
		$context = $this->getContext();
		if ( $this->getMode() === self::RENDER_DESKTOP ) {
			return Html::element(
					'div',
					[ 'class' => self::BASE_CSS_CLASS . '-header-text' ],
					$this->getHeaderText() ) .
				new ButtonWidget( [
					'id' => 'mw-ge-homepage-suggestededits-info',
					'icon' => 'info-unpadded',
					'framed' => false,
					'title' => $context->msg( 'growthexperiments-homepage-suggestededits-more-info' )->text(),
					'label' => $context->msg( 'growthexperiments-homepage-suggestededits-more-info' )->text(),
					'invisibleLabel' => true,
					'infusable' => true,
				] );
		} else {
			return parent::getHeaderTextElement();
		}
	}

	/**
	 * Return the pagination text in the form "1 of 30" being 30 the total number of tasks shown
	 * @return string
	 */
	protected function getTasksPaginationText() {
		$tasks = $this->getTaskSet();

		return $this->getContext()->msg( 'growthexperiments-homepage-suggestededits-pager' )
			->numParams( 1, $tasks->getTotalCount() )
			->parse();
	}

	/**
	 * Check whether the suggested edits feature is (or could be) enabled for anyone
	 * on the wiki.
	 * @param Config $config
	 * @return bool
	 */
	public static function isEnabledForAnyone( Config $config ) {
		return $config->get( 'GEHomepageSuggestedEditsEnabled' );
	}

	/**
	 * Check whether the suggested edits feature is enabled according to the configuration.
	 * @param Config $config
	 * @return bool
	 */
	public static function isEnabled( Config $config ): bool {
		return self::isEnabledForAnyone( $config );
	}

	/** @inheritDoc */
	public function getCssClasses() {
		return array_merge( parent::getCssClasses(),
			$this->userOptionsLookup->getOption( $this->getContext()->getUser(), self::ACTIVATED_PREF ) ?
				[ 'activated' ] :
				[ 'unactivated' ]
		);
	}

	/**
	 * Check whether topic matching has been enabled for the context user.
	 * Note that even with topic matching disabled, all the relevant backend functionality
	 * should still work (but logging and UI will be different).
	 * @param IContextSource $context
	 * @param UserOptionsLookup $userOptionsLookup
	 * @return bool
	 */
	public static function isTopicMatchingEnabled(
		IContextSource $context,
		UserOptionsLookup $userOptionsLookup
	) {
		return self::isEnabled( $context->getConfig() ) &&
			$context->getConfig()->get( 'GEHomepageSuggestedEditsEnableTopics' );
	}

	/**
	 * Check whether topic match mode has been enabled for the context user.
	 * Note that even with topic match mode is disabled, all the relevant backend functionality
	 * should still work (but logging and UI will be different).
	 * @param IContextSource $context
	 * @param UserOptionsLookup $userOptionsLookup
	 * @return bool
	 */
	private function isTopicMatchModeEnabled(
		IContextSource $context,
		UserOptionsLookup $userOptionsLookup
	) {
		return self::isTopicMatchingEnabled( $context, $userOptionsLookup ) &&
			$this->campaignConfig->isUserInCampaign(
				$context->getUser(),
				'growth-glam-2022'
			);
	}

	/**
	 * Get the name of the preference to use for storing topic filters.
	 * @param Config $config
	 * @return string
	 */
	public static function getTopicFiltersPref( Config $config ) {
		$topicType = $config->get( 'GENewcomerTasksTopicType' );
		if ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_ORES ) {
			return self::TOPICS_ORES_PREF;
		}
		return self::TOPICS_PREF;
	}

	/**
	 * Check if guidance feature is enabled for suggested edits.
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isGuidanceEnabledForAnyone( IContextSource $context ): bool {
		return $context->getConfig()->get( 'GENewcomerTasksGuidanceEnabled' );
	}

	/**
	 * Check if guidance feature is enabled for suggested edits.
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isGuidanceEnabled( IContextSource $context ): bool {
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		return self::isGuidanceEnabledForAnyone( $context ) && (
			!$context->getConfig()->get( 'GENewcomerTasksGuidanceRequiresOptIn' ) ||
			$userOptionsLookup->getBoolOption( $context->getUser(), self::GUIDANCE_ENABLED_PREF ) );
	}

	/** @inheritDoc */
	public function getHtml() {
		// This method will be called both directly by the homepage and by getJsData() in
		// some cases, so use some lightweight caching.
		$key = $this->getMode() . ':' . $this->getContext()->getLanguage()->getCode();
		if ( !array_key_exists( $key, $this->htmlCache ) ) {
			$this->htmlCache[$key] = parent::getHtml();
		}
		return $this->htmlCache[$key];
	}

	/**
	 * @param string $mode one of the self::RENDER_* constants
	 * @param string $status one of ['ok', 'empty', 'error', 'total']
	 * @return void
	 */
	private function trackQueueStatus( string $mode, string $status ): void {
		if ( !in_array( $status, [ 'ok', 'empty', 'error', 'total' ] ) ) {
			// Should never happen
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				__METHOD__ . ' called with unexpected status: {status}',
				[
					'status' => $status,
					'exception' => new \RuntimeException
				]
			);

			return;
		}
		$wiki = WikiMap::getCurrentWikiId();
		$platform = ( $mode === self::RENDER_DESKTOP ? 'desktop' : 'mobile' );
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'suggested_edits_queue_total' )
			->setLabel( 'wiki', $wiki )
			->setLabel( 'platform', $platform )
			->setLabel( 'status', $status )
			->copyToStatsdAt( implode( '.', [
				$wiki,
				'growthExperiments.suggestedEdits',
				$platform,
				'queue',
				$status,
			] ) )
			->increment();
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		$data = parent::getJsData( $mode );
		$data['task-preview'] = [ 'noresults' => true ];

		// Preload task card and queue for users who have the module activated
		if ( $this->canRender() ) {
			$tasks = $this->getTaskSet();
			$this->trackQueueStatus( $mode, 'total' );
			if ( $tasks instanceof StatusValue ) {
				$data['task-preview'] = [
					'error' => Status::wrap( $tasks )->getMessage( false, false, 'en' )->parse(),
				];
				$this->trackQueueStatus( $mode, 'error' );
			} elseif ( $tasks->count() === 0 ) {
				$data['task-preview'] = [ 'noresults' => true ];
				$this->trackQueueStatus( $mode, 'empty' );
			} else {
				$formattedTasks = [];
				foreach ( $tasks as $task ) {
					$title = $this->titleFactory->newFromLinkTarget( $task->getTitle() );
					$taskData = [
						'tasktype' => $task->getTaskType()->getId(),
						'difficulty' => $task->getTaskType()->getDifficulty(),
						'qualityGateIds' => $task->getTaskType()->getQualityGateIds(),
						'qualityGateConfig' => $tasks->getQualityGateConfig(),
						'title' => $title->getPrefixedText(),
						// The front-end code for constructing SuggestedEditCardWidget checks
						// to see if pageId is set in order to construct a tracking URL.
						'pageId' => $title->getArticleID(),
						'token' => $task->getToken(),
					];
					if ( $task->getTaskType() instanceof ImageRecommendationBaseTaskType ) {
						// Prevent loading of thumbnail for image recommendation tasks.
						// TODO: Maybe there should be a property on the task type to check
						// rather than special casing image recommendation here
						$taskData['thumbnailSource'] = null;
					}
					$formattedTasks[] = $taskData;
				}
				$data['task-queue'] = $formattedTasks;
				$data['task-preview'] = current( $formattedTasks );
				$this->trackQueueStatus( $mode, 'ok' );
			}
		}

		// When the module is not activated yet, but can be, include module HTML in the
		// data, for dynamic loading on activation.
		if ( $this->canRender() &&
			!self::isActivated( $this->getContext()->getUser(), $this->userOptionsLookup ) &&
			$this->getMode() !== self::RENDER_MOBILE_DETAILS
		) {
			$data += [
				'html' => $this->getHtml(),
				'rlModules' => $this->getModules(),
			];
		}

		return $data;
	}

	/**
	 * Check whether suggested edits have been activated for the given user.
	 * Before activation, suggested edits are exposed via the StartEditing module;
	 * after activation (which happens by interacting with that module) via this one.
	 * @param UserIdentity $user
	 * @param UserOptionsLookup $userOptionsLookup
	 * @return bool
	 */
	public static function isActivated(
		UserIdentity $user,
		UserOptionsLookup $userOptionsLookup
	) {
		return $userOptionsLookup->getBoolOption( $user, self::ACTIVATED_PREF );
	}

	/** @inheritDoc */
	public function getState() {
		return self::isActivated( $this->getContext()->getUser(), $this->userOptionsLookup ) ?
			self::MODULE_STATE_ACTIVATED :
			self::MODULE_STATE_UNACTIVATED;
	}

	/**
	 * Get a suggested task set, with in-process caching.
	 * @return TaskSet|StatusValue
	 */
	private function getTaskSet() {
		if ( $this->tasks ) {
			return $this->tasks;
		}
		$user = $this->getContext()->getUser();
		$suggesterOptions = [ 'revalidateCache' => false ];
		if ( $this->getContext()->getRequest()->getCheck( 'resetTaskCache' ) ) {
			$suggesterOptions = [ 'resetCache' => true ];
			// TODO also reset cache in ImageRecommendationFilter
		}
		$taskTypes = $this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user );
		$topics = $this->newcomerTasksUserOptionsLookup->getTopics( $user );
		$topicsMatchMode = $this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user );
		$taskSetFilters = new TaskSetFilters( $taskTypes, $topics, $topicsMatchMode );
		$tasks = $this->taskSuggester->suggest( $user, $taskSetFilters, null, null,
			$suggesterOptions );
		if ( $tasks instanceof TaskSet ) {
			// If there are link recommendation tasks without corresponding DB entries, these will be removed
			// from the TaskSet.
			$tasks = $this->linkRecommendationFilter->filter( $tasks );
			$tasks = $this->imageRecommendationFilter->filter( $tasks );
			$tasks = $this->protectionFilter->filter( $tasks );
		}
		$this->tasks = $tasks;
		$this->resetTaskCache( $user, $taskSetFilters, $suggesterOptions );
		return $this->tasks;
	}

	/**
	 * Refresh the user's task cache in a deferred update.
	 *
	 * @param UserIdentity $user
	 * @param TaskSetFilters $taskSetFilters
	 * @param array $suggesterOptions
	 * @return void
	 */
	public function resetTaskCache( UserIdentity $user, TaskSetFilters $taskSetFilters, array $suggesterOptions ) {
		DeferredUpdates::addCallableUpdate( function () use ( $user, $taskSetFilters, $suggesterOptions ) {
			$suggesterOptions['resetCache'] = true;
			$this->taskSuggester->suggest( $user, $taskSetFilters, null, null, $suggesterOptions );
		} );
	}

	/** @inheritDoc */
	protected function canRender() {
		return self::isEnabled( $this->getContext()->getConfig() )
			&& !$this->configurationLoader->loadTaskTypes() instanceof StatusValue;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-suggested-edits-header' )
			->text();
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'lightbulb';
	}

	/** @inheritDoc */
	protected function getBody() {
		$isDesktop = $this->getMode() === self::RENDER_DESKTOP;
		$topicMatchMode = $this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $this->getUser() );
		return Html::rawElement(
			'div', [ 'class' => 'suggested-edits-module-wrapper' ],
			( new Tag( 'div' ) )
				->addClasses( [ 'suggested-edits-filters' ] )
				->appendContent( $isDesktop ? $this->getFiltersButtonGroupWidget() : '' ) .
			( new Tag( 'div' ) )
				->addClasses( [ 'suggested-edits-pager' ] )
				->appendContent( $this->getPager() ) .
			( new CardWrapper(
				$this->getContext(),
				self::isTopicMatchingEnabled( $this->getContext(), $this->userOptionsLookup ),
				$topicMatchMode === SearchStrategy::TOPIC_MATCH_MODE_AND,
				$this->getContext()->getLanguage()->getDir(),
				$this->getTaskSet(),
				$this->getNavigationWidgetFactory(),
				$isDesktop
			) )->render() .
			( new Tag( 'div' ) )->addClasses( [ 'suggested-edits-task-explanation' ] )
				->appendContent( ( new TaskExplanationWidget( [
					'taskSet' => $this->getTaskSet(),
					'localizer' => $this->getContext()
				] ) ) )
		);
	}

	/** @inheritDoc */
	protected function getFooter() {
		if ( $this->getMode() === self::RENDER_DESKTOP ) {
			$siteViewsCount = $this->getSiteViews();
			$siteViewsMessage = $siteViewsCount ?
				$this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer' )
					->params( $this->formatSiteViews( $siteViewsCount ) ) :
				$this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer-noviews' );
			return $siteViewsMessage->parse();
		}
		return ( new Tag( 'div' ) )->addClasses( [ 'suggested-edits-footer-navigation' ] )
			->appendContent( [
				$this->getNavigationWidgetFactory()->getPreviousNextButtonHtml( 'Previous' ),
				$this->getNavigationWidgetFactory()->getEditButton(),
				$this->getNavigationWidgetFactory()->getPreviousNextButtonHtml( 'Next' )
			] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		$tasks = $this->getTaskSet();
		// If the task cannot be loaded, fall back to the old summary style for now.
		$showTaskPreview = $tasks instanceof TaskSet && $tasks->count() > 0;

		if ( $showTaskPreview ) {
			$button = new ButtonWidget( [
				'label' => $this->getContext()->msg(
					'growthexperiments-homepage-suggestededits-mobilesummary-footer-button' )->text(),
				'classes' => [ 'suggested-edits-preview-cta-button' ],
				'flags' => [ 'primary', 'progressive' ],
				// Avoid nesting links, browsers will break markup
				'button' => new Tag( 'span' ),
			] );
			$centeredButton = Html::rawElement( 'div', [ 'class' => 'suggested-edits-preview-footer' ], $button );
			$subheader = Html::rawElement(
				'div',
				[ 'class' => 'suggested-edits-preview-pager' ],
				$this->getTasksPaginationText()
			);

			return Html::rawElement( 'div', [ 'class' => [ 'growthexperiments-task-preview-widget' ] ],
				$subheader . $this->getTaskCard() . $centeredButton );
		} else {
			$baseClass = 'growthexperiments-suggestededits-mobilesummary-notasks-widget';

			$previewTitle = $this->getContext()
				->msg( 'growthexperiments-homepage-suggestededits-mobilesummary-notasks-title' )
				->text();
			$subtitle = $this->getContext()
				->msg( 'growthexperiments-homepage-suggestededits-mobilesummary-notasks-subtitle' )
				->text();
			$footerText = $this->getContext()
				->msg( 'growthexperiments-homepage-suggestededits-mobilesummary-footer' )
				->text();
			$noTaskPreviewContent = Html::rawElement( 'div', [ 'class' => $baseClass . '__main' ],
				Html::rawElement( 'div', [ 'class' => $baseClass . '__icon' ] ) .
				Html::rawElement( 'div', [],
					Html::element( 'div', [ 'class' => $baseClass . '__title' ], $previewTitle ) .
					Html::element( 'div', [ 'class' => $baseClass . '__subtitle' ], $subtitle )
				)
			) . Html::element( 'div', [
				'class' => $baseClass . '__footer'
			], $footerText );
			return Html::rawElement( 'div', [
				'class' => [ $baseClass ]
			], $noTaskPreviewContent );
		}
	}

	/**
	 * Generate a button group widget with task and topic filters.
	 *
	 * This function should be kept in sync with
	 * SuggestedEditsFiltersWidget.prototype.updateButtonLabelAndIcon
	 */
	private function getFiltersButtonGroupWidget(): ButtonGroupWidget {
		$buttons = [];
		$user = $this->getContext()->getUser();
		if ( self::isTopicMatchingEnabled( $this->getContext(), $this->userOptionsLookup ) ) {
			// topicPreferences will be an empty array if the user had saved topics
			// in the past, or null if they have never saved topics
			$topicPreferences = $this->newcomerTasksUserOptionsLookup
				->getTopicFilterWithoutFallback( $user );
			$excludedTopics = $this->campaignConfig->getTopicsToExcludeForUser( $user );
			// Filter out campaign-specific topics that are no longer available
			if ( $topicPreferences && count( $excludedTopics ) ) {
				$topicPreferences = array_diff( $topicPreferences, $excludedTopics );
			}
			$topicData = $this->configurationLoader->getTopics();
			$topicLabel = '';
			$addPulsatingDot = false;
			$topicFilterMode = $this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user );
			$flags = [];
			if ( !$topicPreferences ) {
				if ( $topicPreferences === null ) {
					$flags = [ 'progressive' ];
					$addPulsatingDot = true;
				}
				$topicLabel =
					$this->getContext()
						->msg( 'growthexperiments-homepage-suggestededits-topic-filter-select-interests' )
						->text();
			} else {
				$topicMessages = [];
				foreach ( $topicPreferences as $topicPreference ) {
					$topic = $topicData[$topicPreference] ?? null;
					if ( $topic instanceof Topic ) {
						$topicMessages[] = $topic->getName( $this->getContext() );
					}
				}
				$topicMessages = array_filter( $topicMessages );
				if ( count( $topicMessages ) ) {
					if ( count( $topicMessages ) < 3 ) {
						$separator = $topicFilterMode === SearchStrategy::TOPIC_MATCH_MODE_OR ?
							$this->getContext()->msg( 'comma-separator' ) : ' + ';
						$topicLabel = implode( $separator, $topicMessages );
					} else {
						$topicLabel =
							$this->getContext()
								->msg( 'growthexperiments-homepage-suggestededits-topics-button-topic-count' )
								->numParams( count( $topicMessages ) )
								->text();
					}
				}
			}

			$topicFilterButtonWidget = new ButtonWidget( [
				'label' => $topicLabel,
				'flags' => $flags,
				'classes' => [ 'topic-matching', 'topic-filter-button' ],
				'indicator' => $this->getMode() === self::RENDER_DESKTOP ? null : 'down',
				'icon' => $topicFilterMode === SearchStrategy::TOPIC_MATCH_MODE_OR ? 'funnel' : 'funnel-add'
			] );
			if ( $addPulsatingDot ) {
				$topicFilterButtonWidget->appendContent(
					( new Tag( 'div' ) )->addClasses( [ 'mw-pulsating-dot' ] )
				);
			}
			$buttons[] = $topicFilterButtonWidget;
		}
		$difficultyFilterButtonWidget = new ButtonWidget( [
			'icon' => 'difficulty-outline',
			'classes' => self::isTopicMatchingEnabled( $this->getContext(), $this->userOptionsLookup ) ?
				[ 'topic-matching' ] : [ '' ],
			'label' => $this->getContext()->msg(
				'growthexperiments-homepage-suggestededits-difficulty-filters-title'
			)->text(),
			'indicator' => $this->getMode() === self::RENDER_DESKTOP ? null : 'down'
		] );

		$levels = [];
		$taskTypeData = $this->configurationLoader->getTaskTypes();
		foreach ( $this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ) as $taskTypeId ) {
			/** @var TaskType $taskType */
			$taskType = $taskTypeData[$taskTypeId] ?? null;
			if ( $taskType ) {
				// Sometimes the default task types don't exist on a wiki (T268012)
				$levels[ $taskType->getDifficulty() ] = true;
			}
		}
		$taskTypeMessages = [];
		$messageKey = $this->getMode() === self::RENDER_DESKTOP ?
			'growthexperiments-homepage-suggestededits-difficulty-filter-label' :
			'growthexperiments-homepage-suggestededits-difficulty-filter-label-mobile';

		foreach ( [ 'easy', 'medium', 'hard' ] as $level ) {
			if ( !isset( $levels[$level] ) ) {
				continue;
			}
			// The following messages are used here:
			// * growthexperiments-homepage-suggestededits-difficulty-filter-label-easy
			// * growthexperiments-homepage-suggestededits-difficulty-filter-label-medium
			// * growthexperiments-homepage-suggestededits-difficulty-filter-label-hard
			$label = $this->getContext()->msg(
				'growthexperiments-homepage-suggestededits-difficulty-filter-label-' . $level
			);
			$message = $this->getContext()->msg( $messageKey )
				->params( $label )
				->text();
			$difficultyFilterButtonWidget->setLabel( $message );
			// Icons: difficulty-easy, difficulty-medium, difficulty-hard
			$difficultyFilterButtonWidget->setIcon( 'difficulty-' . $level );
			$taskTypeMessages[] = $label;
		}
		if ( count( $taskTypeMessages ) > 1 ) {
			$difficultyFilterButtonWidget->setIcon( 'difficulty-outline' );
			$messageKey = $this->getMode() === self::RENDER_DESKTOP ?
				'growthexperiments-homepage-suggestededits-difficulty-filter-label' :
				'growthexperiments-homepage-suggestededits-difficulty-filter-label-mobile';
			$message = $this->getContext()->msg( $messageKey )
				->params( implode( $this->getContext()->msg( 'comma-separator' ),
					$taskTypeMessages ) )
				->text();
			$difficultyFilterButtonWidget->setLabel( $message );
		}

		$buttons[] = $difficultyFilterButtonWidget;
		$this->buttonGroupWidget = new ButtonGroupWidget( [
			'class' => 'suggested-edits-filters',
			'items' => $buttons,
			'infusable' => true,
		] );
		return $this->buttonGroupWidget;
	}

	/**
	 * Generate HTML identical to that of mw.libs.ge.SmallTaskCard
	 * @return string
	 */
	private function getTaskCard() {
		$tasks = $this->getTaskSet();
		if ( !$tasks instanceof TaskSet ) {
			throw new RuntimeException( 'Expected to have tasks.' );
		}
		$task = $tasks[0];
		$taskTypeId = $task->getTaskType()->getId();
		$title = $this->titleFactory->newFromLinkTarget( $task->getTitle() );

		$imageClasses = array_merge(
			[ 'mw-ge-small-task-card-image' ],
			$task->getTaskType()->getSmallTaskCardImageCssClasses()
		);
		$image = Html::element( 'div', [ 'class' =>
			implode( " ", $imageClasses ) ] );
		$title = Html::element( 'span',
			[ 'class' => 'mw-ge-small-task-card-title' ],
			$title->getPrefixedText() );
		$description = Html::element( 'div',
			[ 'class' => 'mw-ge-small-task-card-description skeleton' ] );
		$taskIcon = new IconWidget( [ 'icon' => 'difficulty-' . $task->getTaskType()->getDifficulty() ] );
		$iconData = $task->getTaskType()->getIconData();
		$taskTypeIcon = array_key_exists( 'icon', $iconData )
			? new IconWidget( [ 'icon' => $iconData['icon'] ] )
			: '';
		$taskType = Html::rawElement( 'span',
			[ 'class' => 'mw-ge-small-task-card-tasktype '
				 // The following classes are used here:
				 // * mw-ge-small-task-card-tasktype-difficulty-easy
				 // * mw-ge-small-task-card-tasktype-difficulty-medium
				 // * mw-ge-small-task-card-tasktype-difficulty-hard
				. 'mw-ge-small-task-card-tasktype-difficulty-'
				. $task->getTaskType()->getDifficulty() ],
			$taskTypeIcon . $taskIcon . Html::element( 'span',
				[ 'class' => 'mw-ge-small-task-card-tasktype-taskname' ],
				$task->getTaskType()->getName( $this->getContext() )
			) );

		$glue = Html::element( 'div',
			[ 'class' => 'mw-ge-small-task-card-glue' ] );
		$cardMetadataContainer = Html::rawElement( 'div',
			[ 'class' => 'mw-ge-small-task-card-metadata-container' ],
			// Unlike SmallTaskCard, this version does not have pageviews.
			$taskType );
		$cardTextContainer = Html::rawElement( 'div',
			[ 'class' => 'mw-ge-small-task-card-text-container' ],
			$title . $description . $glue . $cardMetadataContainer );
		return Html::rawElement( 'div',
			// only called for mobile views
			[ 'class' => 'mw-ge-small-task-card mw-ge-small-task-card-mobile '
				. "mw-ge-small-task-card mw-ge-tasktype-$taskTypeId"
			],
		$image . $cardTextContainer );
	}

	/** @inheritDoc */
	protected function getSubheader() {
		// Ugly hack to get the filters positioned outside of the module wrapper on mobile.
		$mobileDetails = [ self::RENDER_MOBILE_DETAILS, self::RENDER_MOBILE_DETAILS_OVERLAY ];
		if ( !in_array( $this->getMode(), $mobileDetails, true ) ) {
			return '';
		}
		return Html::rawElement( 'div', [ 'class' => 'suggested-edits-filters' ] );
	}

	/** @inheritDoc */
	protected function getSubheaderTag() {
		return 'div';
	}

	/** @inheritDoc */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[
				'mediawiki.pulsatingdot',
				'oojs-ui.styles.icons-editing-core'
			]
		);
	}

	/** @inheritDoc */
	protected function getModules() {
		return array_merge(
			parent::getModules(),
			[ 'ext.growthExperiments.Homepage.SuggestedEdits' ]
		);
	}

	/**
	 * Returns daily unique site views, averaged over the last 30 days.
	 * @return int|null
	 */
	protected function getSiteViews() {
		if ( !$this->pageViewService ||
			 !$this->pageViewService->supports( PageViewService::METRIC_UNIQUE, PageViewService::SCOPE_SITE )
		) {
			return null;
		}
		// When PageViewService is a WikimediaPageViewService, the pageviews for the last two days
		// or so will be missing due to AQS processing lag. Get some more days and discard the
		// newest ones.
		$status = $this->pageViewService->getSiteData( 32, PageViewService::METRIC_UNIQUE );
		if ( !$status->isOK() ) {
			return null;
		}
		$data = $status->getValue();
		ksort( $data );
		return (int)( array_sum( array_slice( $data, 0, 30 ) ) / 30 );
	}

	/**
	 * Format site views count in a human-readable way.
	 * @param int $siteViewsCount
	 * @return string|MessageParam A Message::params() parameter
	 */
	protected function formatSiteViews( int $siteViewsCount ) {
		// We only get here when $siteViewsCount is not 0 so log is safe.
		$siteViewsCount = (int)round( $siteViewsCount, (int)-floor( log10( $siteViewsCount ) ) );
		$language = $this->getContext()->getLanguage();
		if ( $this->getContext()->msg( 'growthexperiments-homepage-suggestededits-footer-suffix' )
			->isDisabled()
		) {
			// This language does not use suffixes, just output the rounded number
			return Message::numParam( $siteViewsCount );
		}
		// Abuse Language::formatComputingNumbers into displaying large numbers in a human-readable way
		return $language->formatComputingNumbers( $siteViewsCount, 1000,
			'growthexperiments-homepage-suggestededits-footer-$1suffix' );
	}

	/** @inheritDoc */
	protected function getActionData() {
		$user = $this->getContext()->getUser();
		$taskSet = $this->getTaskSet();
		$taskTypes = $topics = null;
		if ( $taskSet instanceof TaskSet ) {
			$taskTypes = $taskSet->getFilters()->getTaskTypeFilters();
			$topics = $taskSet->getFilters()->getTopicFilters();
			$topicsMatchMode = $taskSet->getFilters()->getTopicFiltersMode();
		}

		$isMobile = Util::isMobile( $this->getContext()->getOutput()->getSkin() );

		// these will be updated on the client side as needed
		$data = [
			'taskTypes' => $taskTypes ?? $this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
			'taskCount' => ( $taskSet instanceof TaskSet ) ? $taskSet->getTotalCount() : 0,
		];
		if ( self::isTopicMatchingEnabled( $this->getContext(), $this->userOptionsLookup ) ) {
			$data['topics'] = $topics ?? $this->newcomerTasksUserOptionsLookup->getTopics( $user );
			if ( $this->isTopicMatchModeEnabled( $this->getContext(), $this->userOptionsLookup ) ) {
				$data['topicsMatchMode'] = $topicsMatchMode ??
					$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user );
			}

		}
		return array_merge( parent::getActionData(), $data );
	}

	/**
	 * @inheritDoc
	 */
	protected function getJsConfigVars() {
		return [
			'GEHomepageSuggestedEditsEnableTopics' => self::isTopicMatchingEnabled(
				$this->getContext(),
				$this->userOptionsLookup
			)
		];
	}

	/**
	 * Get the pager text (1 of X) to show on server side render.
	 *
	 * This code roughly corresponds to SuggestedEditPagerWidget.prototype.setMessage
	 *
	 * @return string
	 * @throws Exception
	 */
	private function getPager() {
		$taskSet = $this->getTaskSet();
		if ( !$taskSet instanceof TaskSet || !$taskSet->count() ) {
			return '';
		}
		return new HtmlSnippet( $this->getContext()->msg( 'growthexperiments-homepage-suggestededits-pager' )
				->numParams( [ 1, $taskSet->getTotalCount() ] )
			->parse() );
	}

	/**
	 * Get the query params for the redirect URL for the specified task type ID
	 *
	 * @param string|null $taskTypeId
	 * @return array
	 */
	public function getRedirectParams( ?string $taskTypeId = null ): array {
		$taskType = $this->configurationLoader->getTaskTypes()[ $taskTypeId ] ?? null;
		if ( !$taskType ) {
			return [];
		}

		$redirectParams = [];
		if ( $taskType->shouldOpenInEditMode() ) {
			$redirectParams[ 'veaction' ] = 'edit';
		}
		if ( (bool)$taskType->getDefaultEditSection() ) {
			$redirectParams[ 'section' ] = $taskType->getDefaultEditSection();
		}
		return $redirectParams;
	}

	private function getNavigationWidgetFactory(): NavigationWidgetFactory {
		if ( !$this->navigationWidgetFactory ) {
			$this->navigationWidgetFactory = new NavigationWidgetFactory(
				$this->getContext(),
				$this->getTaskSet()
			);
		}
		return $this->navigationWidgetFactory;
	}
}
