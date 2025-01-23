<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace GrowthExperiments;

use CirrusSearch\Search\Rescore\BoostFunctionBuilder;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use GrowthExperiments\Config\GrowthConfigLoaderStaticTrait;
use GrowthExperiments\Homepage\SiteNoticeGenerator;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\LevelingUp\NotificationGetStartedJob;
use GrowthExperiments\LevelingUp\NotificationKeepGoingJob;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksInfo;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Recommendation;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\UnderlinkedFunctionScoreBuilder;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\StructuredTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\Specials\SpecialClaimMentee;
use GrowthExperiments\Specials\SpecialHomepage;
use GrowthExperiments\Specials\SpecialImpact;
use GrowthExperiments\Specials\SpecialNewcomerTasksInfo;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Hook\FormatAutocommentsHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Hook\SiteNoticeAfterHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Contribute\Card\ContributeCard;
use MediaWiki\Specials\Contribute\Card\ContributeCardActionLink;
use MediaWiki\Specials\Contribute\Hook\ContributeCardsHook;
use MediaWiki\Status\Status;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\Hook\ConfirmEmailCompleteHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\WikiMap\WikiMap;
use MessageLocalizer;
use OOUI\ButtonWidget;
use Skin;
use SkinTemplate;
use StatusValue;
use stdClass;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Stats\StatsFactory;
use WikiPage;

/**
 * Hook implementations that related directly or indirectly to Special:Homepage.
 *
 * Most suggested edits related hooks are defined here.
 */
class HomepageHooks implements
	SpecialPage_initListHook,
	BeforePageDisplayHook,
	SkinTemplateNavigation__UniversalHook,
	SidebarBeforeOutputHook,
	GetPreferencesHook,
	UserGetDefaultOptionsHook,
	ResourceLoaderExcludeUserOptionsHook,
	AuthChangeFormFieldsHook,
	LocalUserCreatedHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	SpecialContributionsBeforeMainOutputHook,
	ConfirmEmailCompleteHook,
	SiteNoticeAfterHook,
	FormatAutocommentsHook,
	PageSaveCompleteHook,
	RecentChange_saveHook,
	ContributeCardsHook
{
	use GrowthConfigLoaderStaticTrait;

	public const HOMEPAGE_PREF_ENABLE = 'growthexperiments-homepage-enable';
	public const HOMEPAGE_PREF_PT_LINK = 'growthexperiments-homepage-pt-link';
	/** @var string User options key for storing whether the user has seen the notice. */
	public const HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN = 'homepage_mobile_discovery_notice_seen';
	public const CONFIRMEMAIL_QUERY_PARAM = 'specialconfirmemail';
	private const VE_PREF_DISABLE_BETA = 'visualeditor-betatempdisable';
	private const VE_PREF_EDITOR = 'visualeditor-editor';

	public const GROWTH_FORCE_OPTIN = 1;
	public const GROWTH_FORCE_OPTOUT = 2;
	public const GROWTH_FORCE_NONE = 3;

	/** @var string Query string used on Special:CreateAccount to force enable/disable Growth features */
	public const REGISTRATION_GROWTHEXPERIMENTS_ENABLED = 'geEnabled';

	private Config $config;
	private ILoadBalancer $lb;
	private UserOptionsManager $userOptionsManager;
	private UserOptionsLookup $userOptionsLookup;
	private UserIdentityUtils $userIdentityUtils;
	private NamespaceInfo $namespaceInfo;
	private TitleFactory $titleFactory;
	private ConfigurationLoader $configurationLoader;
	private CampaignConfig $campaignConfig;
	private ExperimentUserManager $experimentUserManager;
	private TaskTypeHandlerRegistry $taskTypeHandlerRegistry;
	private TaskSuggesterFactory $taskSuggesterFactory;
	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;
	private LinkRecommendationStore $linkRecommendationStore;
	private LinkRecommendationHelper $linkRecommendationHelper;
	private NewcomerTasksInfo $suggestionsInfo;
	private JobQueueGroup $jobQueueGroup;
	private SpecialPageFactory $specialPageFactory;
	private NewcomerTasksChangeTagsManager $newcomerTasksChangeTagsManager;
	private ?MessageLocalizer $messageLocalizer;
	private ?OutputPage $outputPage;
	private ?UserIdentity $userIdentity;
	private UserImpactLookup $userImpactLookup;
	private UserImpactStore $userImpactStore;

	/** @var bool Are we in a context where it is safe to access the primary DB? */
	private $canAccessPrimary;
	private StatsFactory $statsFactory;

	/**
	 * @param Config $config Uses PHP globals
	 * @param ILoadBalancer $lb
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserIdentityUtils $userIdentityUtils
	 * @param NamespaceInfo $namespaceInfo
	 * @param TitleFactory $titleFactory
	 * @param StatsFactory $statsFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param ConfigurationLoader $configurationLoader
	 * @param CampaignConfig $campaignConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkRecommendationStore $linkRecommendationStore
	 * @param LinkRecommendationHelper $linkRecommendationHelper
	 * @param SpecialPageFactory $specialPageFactory
	 * @param NewcomerTasksChangeTagsManager $newcomerTasksChangeTagsManager
	 * @param NewcomerTasksInfo $suggestionsInfo
	 * @param UserImpactLookup $userImpactLookup
	 * @param UserImpactStore $userImpactStore
	 */
	public function __construct(
		Config $config,
		ILoadBalancer $lb,
		UserOptionsManager $userOptionsManager,
		UserOptionsLookup $userOptionsLookup,
		UserIdentityUtils $userIdentityUtils,
		NamespaceInfo $namespaceInfo,
		TitleFactory $titleFactory,
		StatsFactory $statsFactory,
		JobQueueGroup $jobQueueGroup,
		ConfigurationLoader $configurationLoader,
		CampaignConfig $campaignConfig,
		ExperimentUserManager $experimentUserManager,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LinkRecommendationStore $linkRecommendationStore,
		LinkRecommendationHelper $linkRecommendationHelper,
		SpecialPageFactory $specialPageFactory,
		NewcomerTasksChangeTagsManager $newcomerTasksChangeTagsManager,
		NewcomerTasksInfo $suggestionsInfo,
		UserImpactLookup $userImpactLookup,
		UserImpactStore $userImpactStore
	) {
		$this->config = $config;
		$this->lb = $lb;
		$this->userOptionsManager = $userOptionsManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->namespaceInfo = $namespaceInfo;
		$this->titleFactory = $titleFactory;
		$this->statsFactory = $statsFactory;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->configurationLoader = $configurationLoader;
		$this->campaignConfig = $campaignConfig;
		$this->experimentUserManager = $experimentUserManager;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->linkRecommendationHelper = $linkRecommendationHelper;
		$this->specialPageFactory = $specialPageFactory;
		$this->newcomerTasksChangeTagsManager = $newcomerTasksChangeTagsManager;
		$this->suggestionsInfo = $suggestionsInfo;
		$this->userImpactLookup = $userImpactLookup;
		$this->userImpactStore = $userImpactStore;

		// Ideally this would be injected but the way hook handlers are defined makes that hard.
		$this->canAccessPrimary = defined( 'MEDIAWIKI_JOB_RUNNER' )
			|| MW_ENTRY_POINT === 'cli'
			|| RequestContext::getMain()->getRequest()->wasPosted();
	}

	/**
	 * Register Homepage, Impact, ClaimMentee and NewcomerTasksInfo special pages.
	 *
	 * @param array &$list
	 * @throws ConfigException
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( self::isHomepageEnabled() ) {
			$pageViewInfoEnabled = ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
			$list['Homepage'] = [
				'class' => SpecialHomepage::class,
				'services' => [
					'GrowthExperimentsHomepageModuleRegistry',
					'StatsFactory',
					'GrowthExperimentsExperimentUserManager',
					'GrowthExperimentsMentorManager',
					'GrowthExperimentsCommunityConfig',
					'UserOptionsManager',
					'TitleFactory',
				]
			];
			if ( $pageViewInfoEnabled ) {
				$list['Impact'] = [
					'class' => SpecialImpact::class,
					'services' => [
						'UserFactory',
						'UserNameUtils',
						'UserNamePrefixSearch',
						'GrowthExperimentsHomepageModuleRegistry',
					]
				];
			}
			$list[ 'ClaimMentee' ] = [
				'class' => SpecialClaimMentee::class,
				'services' => [
					'GrowthExperimentsMentorProvider',
					'GrowthExperimentsChangeMentorFactory',
					'GrowthExperimentsCommunityConfig'
				]
			];

			$list[ 'NewcomerTasksInfo' ] = [
				'class' => SpecialNewcomerTasksInfo::class,
				'services' => [
					'GrowthExperimentsSuggestionsInfo'
				]
			];
		}
	}

	/**
	 * @param UserIdentity|null $user
	 * @return bool
	 * @throws ConfigException
	 */
	public static function isHomepageEnabled( ?UserIdentity $user = null ): bool {
		// keep the dependencies minimal, this is used from other hooks as well
		$services = MediaWikiServices::getInstance();
		return (
			$services->getMainConfig()->get( 'GEHomepageEnabled' ) &&
			(
				$user === null ||
				$services->getUserOptionsLookup()->getBoolOption(
					$user,
					self::HOMEPAGE_PREF_ENABLE
				)
			)
		);
	}

	/**
	 * Similar to ::isHomepageEnabled, but using dependency-injected services.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	private function isHomepageEnabledGloballyAndForUser( UserIdentity $user ): bool {
		return $this->config->get( 'GEHomepageEnabled' ) &&
			$this->userOptionsLookup->getBoolOption( $user, self::HOMEPAGE_PREF_ENABLE );
	}

	/**
	 * Get the click ID from the URL if set (from clicking a suggested edit card).
	 *
	 * @param IContextSource $context
	 * @return string|null
	 */
	public static function getClickId( IContextSource $context ) {
		if ( SuggestedEdits::isEnabled( $context->getConfig() ) ) {
			return $context->getRequest()->getVal( 'geclickid' ) ?: null;
		}
		return null;
	}

	/**
	 * @param IContextSource $context
	 * @param bool &$shouldOversample
	 */
	public static function onWikimediaEventsShouldSchemaEditAttemptStepOversample(
		IContextSource $context, &$shouldOversample
	) {
		if ( self::getClickId( $context ) ) {
			// Force WikimediaEvents to log EditAttemptStep on every request
			$shouldOversample = true;
		}
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @throws ConfigException
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$context = $out->getContext();
		$isSuggestedEditsEnabled = SuggestedEdits::isEnabled( $context->getConfig() );
		if (
			Util::isMobile( $skin ) &&
			// Optimisation: isHomepageEnabled() is non-trivial, check it last
			self::isHomepageEnabled( $skin->getUser() )
		) {
			$out->addModuleStyles( 'ext.growthExperiments.mobileMenu.icons' );
		}
		if ( $context->getTitle()->inNamespaces( NS_MAIN, NS_TALK ) &&
			$isSuggestedEditsEnabled
		) {
			// Manage the suggested edit session.
			$out->addModules( 'ext.growthExperiments.SuggestedEditSession' );
		}

		if ( $isSuggestedEditsEnabled ) {
			$isLevelingUpEnabledForUser = LevelingUpManager::isEnabledForUser(
				$context->getUser(),
				$this->config,
				$this->experimentUserManager
			);
			$out->addJsConfigVars( [
				// Always output these config vars since they are used by ext.growthExperiments.DataStore
				// which can be included in any module
				'GEHomepageSuggestedEditsEnableTopics' => SuggestedEdits::isTopicMatchingEnabled(
					$context,
					$this->userOptionsLookup
				),
				'wgGETopicsMatchModeEnabled' => $this->config->get( 'GETopicsMatchModeEnabled' ),
				'wgGEStructuredTaskRejectionReasonTextInputEnabled' =>
					$this->config->get( 'GEStructuredTaskRejectionReasonTextInputEnabled' ),
				// Always output, it's used throughout the suggested editing session.
				'wgGELevelingUpEnabledForUser' => $isLevelingUpEnabledForUser,
			] );
		}

		$clickId = self::getClickId( $context );
		if ( $context->getTitle()->inNamespaces( NS_MAIN, NS_TALK ) && $clickId ) {
			// The user just clicked on a suggested edit task card; we need to initialize the
			// suggested edit session.

			// Override the edit session ID.
			// The suggested edit session is tracked on the client side, because it is
			// specific to the browser tab, but some of the EditAttemptStep events it
			// needs to be associated with happen early on page load so setting this
			// on the JS side might be too late. So, we use JS to propagate the clickId
			// to all edit links, and then use this code to set the JS variable for the
			// pageview that's initiated by clicking on the edit link. This might be overkill.
			$out->addJsConfigVars( [
				'wgWMESchemaEditAttemptStepSessionId' => $clickId,
			] );

			$recommendationProvider = $taskType = null;
			$taskTypeId = $context->getRequest()->getVal( 'getasktype' );
			if ( !$taskTypeId ) {
				Util::logText( 'Click ID present but task type ID missing' );
			} else {
				$taskType = $this->configurationLoader->getTaskTypes()[$taskTypeId] ?? null;
				if ( !$taskType ) {
					Util::logText( "No such task type: {taskTypeId}", [
						'taskTypeId' => $taskTypeId,
					] );
				} else {
					$taskTypeHandler = $this->taskTypeHandlerRegistry->getByTaskType( $taskType );
					if ( $taskTypeHandler instanceof StructuredTaskTypeHandler ) {
						$recommendationProvider = $taskTypeHandler->getRecommendationProvider();
					}
				}
			}

			if ( $taskType ) {
				$levelingUpTryNewTaskOptOuts = $this->userOptionsLookup->getOption(
					$context->getUser(),
					LevelingUpManager::TASK_TYPE_PROMPT_OPT_OUTS_PREF,
					json_encode( [] )
				);
				$levelingUpTryNewTaskOptOuts = json_decode( $levelingUpTryNewTaskOptOuts ) ?? [];
				$out->addJsConfigVars( [
					'wgGESuggestedEditTaskType' => $taskType->getId(),
					'wgGELevelingUpTryNewTaskOptOuts' => $levelingUpTryNewTaskOptOuts,
				] );

				if ( $recommendationProvider ) {
					$recommendation = $recommendationProvider->get( $context->getTitle(), $taskType );
					if ( $recommendation instanceof Recommendation ) {
						$serializedRecommendation = $recommendation->toArray();
					} else {
						Util::logStatus( $recommendation );
						$serializedRecommendation = [
							'error' => Status::wrap( $recommendation )->getWikiText( false, false, 'en' ),
						];
					}

					$taskSet = $this->taskSuggesterFactory->create()->suggest(
						$context->getUser(),
						new TaskSetFilters(
							$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $context->getUser() ),
							$this->newcomerTasksUserOptionsLookup->getTopics( $context->getUser() ),
							$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $context->getUser() )
						),
						1
					);
					$qualityGateConfig = $taskSet instanceof TaskSet ? $taskSet->getQualityGateConfig() : [];
					// If the user's gone over the dailyLimit for a task, return an error.
					if ( $qualityGateConfig[$taskType->getId()]['dailyLimit'] ?? false ) {
						$serializedRecommendation = [ 'error' => 'Daily limit exceeded for ' . $taskType->getId() ];
					}
					$out->addJsConfigVars( [ 'wgGESuggestedEditQualityGateConfig' => $qualityGateConfig ] );
					$out->addJsConfigVars( [
						'wgGESuggestedEditData' => $serializedRecommendation,
					] );
				}

				$this->maybeOverridePreferredEditorWithVE( $taskType, $skin->getUser() );
			}
		}

		// Config vars used to modify the suggested edits topics based on campaign
		// (see ext.growthExperiments.Homepage.SuggestedEdits/Topics.js)
		if ( ( !$skin->getTitle() || $skin->getTitle()->isSpecial( 'Homepage' ) ) &&
			SuggestedEdits::isEnabled( $context->getConfig() ) ) {
			$out->addJsConfigVars( [
				'wgGETopicsToExclude' => $this->campaignConfig->getTopicsToExcludeForUser(
					$context->getUser()
				),
				'wgGETopicsMatchModeEnabled' => $this->config->get( 'GETopicsMatchModeEnabled' )
			] );
		}
	}

	/**
	 * @param SkinTemplate $skin
	 * @param SkinOptions $skinOptions
	 * @throws ConfigException
	 */
	public static function onSkinMinervaOptionsInit(
		SkinTemplate $skin,
		SkinOptions $skinOptions
	) {
		$title = $skin->getTitle();
		if ( $title && (
			$title->isSpecial( 'Homepage' ) ||
			self::titleIsUserPageOrUserTalk( $title, $skin->getUser() )
		) ) {
			if ( self::isHomepageEnabled( $skin->getUser() ) ) {
				$skinOptions->setMultiple( [
					SkinOptions::TALK_AT_TOP => true,
					SkinOptions::TABS_ON_SPECIALS => true,
				] );
			}
		}
	}

	/**
	 * Make sure user pages have "User", "talk" and "homepage" tabs.
	 *
	 * @param SkinTemplate $skin
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $skin, &$links ): void {
		$user = $skin->getUser();
		$this->personalUrlsBuilder( $skin, $links, $user );
		if ( !self::isHomepageEnabled( $user ) ) {
			return;
		}

		$isMobile = Util::isMobile( $skin );
		if ( $isMobile && $this->userHasPersonalToolsPrefEnabled( $user ) ) {
			$this->updateProfileMenuEntry( $links );
		}

		$title = $skin->getTitle();
		$homepageTitle = SpecialPage::getTitleFor( 'Homepage' );
		$userpage = $user->getUserPage();
		$usertalk = $user->getTalkPage();

		$isHomepage = $title->equals( $homepageTitle );
		$isUserSpace = $title->equals( $userpage ) || $title->isSubpageOf( $userpage );
		$isUserTalkSpace = $title->equals( $usertalk ) || $title->isSubpageOf( $usertalk );

		if ( $isHomepage || $isUserSpace || $isUserTalkSpace ) {
			unset( $links['namespaces']['special'] );
			unset( $links['namespaces']['user'] );
			unset( $links['namespaces']['user_talk'] );

			// T250554: If user currently views a subpage, direct him to the subpage talk page
			if ( !$isHomepage ) {
				$subjectpage = $this->namespaceInfo->getSubjectPage( $title );
				$talkpage = $this->namespaceInfo->getTalkPage( $title );

				if ( $subjectpage instanceof TitleValue ) {
					$subjectpage = Title::newFromLinkTarget( $subjectpage );
				}
				if ( $talkpage instanceof TitleValue ) {
					$talkpage = Title::newFromLinkTarget( $talkpage );
				}
			} else {
				$subjectpage = $userpage;
				$talkpage = $usertalk;
			}

			$homepageUrlQuery = $isHomepage ? '' : wfArrayToCgi( [
				'source' => $isUserSpace ? 'userpagetab' : 'usertalkpagetab',
				'namespace' => $title->getNamespace(),
			] );
			$links['namespaces']['homepage'] = $skin->tabAction(
				$homepageTitle, 'growthexperiments-homepage-tab', $isHomepage, $homepageUrlQuery
			);

			$links['namespaces']['user'] = $skin->tabAction(
				$subjectpage, wfMessage( 'nstab-user', $user->getName() ), $isUserSpace, '', !$isMobile
			);

			$links['namespaces']['user_talk'] = $skin->tabAction(
				$talkpage, 'talk', $isUserTalkSpace, '', !$isMobile
			);
			// Enable talk overlay on talk page tab
			$links['namespaces']['user_talk']['context'] = 'talk';
			if ( $isMobile ) {
				$skin->getOutput()->addModules( 'skins.minerva.talk' );
			}
		}
	}

	private static function titleIsUserPageOrUserTalk( Title $title, User $user ): bool {
		$userpage = $user->getUserPage();
		$usertalk = $user->getTalkPage();
		return $title->equals( $userpage ) ||
			$title->isSubpageOf( $userpage ) ||
			$title->equals( $usertalk ) ||
			$title->isSubpageOf( $usertalk );
	}

	/**
	 * Conditionally make the userpage link go to the homepage.
	 *
	 * @param SkinTemplate $skin
	 * @param array &$links
	 * @param User $user
	 * @throws ConfigException
	 */
	public function personalUrlsBuilder( $skin, &$links, $user ): void {
		if ( Util::isMobile( $skin ) || !self::isHomepageEnabled( $user ) ) {
			return;
		}

		if ( $this->userHasPersonalToolsPrefEnabled( $user ) ) {
			$links['user-menu']['userpage']['href'] = $this->getPersonalToolsHomepageLinkUrl(
				$skin->getTitle()->getNamespace()
			);
			$links['user-page']['userpage']['href'] = $this->getPersonalToolsHomepageLinkUrl(
				$skin->getTitle()->getNamespace()
			);
			// Make the link blue
			unset( $links['user-menu']['userpage']['link-class'] );
			// Remove the "this page doesn't exist" part of the tooltip
			$links['user-menu']['userpage' ]['exists'] = true;
		}
	}

	/**
	 * Register preferences to control the homepage.
	 *
	 * @param User $user
	 * @param array &$preferences
	 * @throws ConfigException
	 */
	public function onGetPreferences( $user, &$preferences ) {
		if ( !self::isHomepageEnabled() ) {
			return;
		}

		$preferences[ self::HOMEPAGE_PREF_ENABLE ] = [
			'type' => 'toggle',
			'section' => 'personal/homepage',
			'label-message' => self::HOMEPAGE_PREF_ENABLE,
		];

		$preferences[ self::HOMEPAGE_PREF_PT_LINK ] = [
			'type' => 'toggle',
			'section' => 'personal/homepage',
			'label-message' => self::HOMEPAGE_PREF_PT_LINK,
			'hide-if' => [ '!==', self::HOMEPAGE_PREF_ENABLE, '1' ],
		];

		if ( HelpPanel::isHelpPanelEnabled() ) {
			$preferences[ HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE ] = [
				'type' => 'toggle',
				'section' => 'personal/homepage',
				'label-message' => HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE
			];
		}

		$preferences[ self::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN ] = [
			'type' => 'api',
		];

		$preferences[ Mentorship::QUESTION_PREF ] = [
			'type' => 'api',
		];

		$preferences[ SuggestedEdits::ACTIVATED_PREF ] = [
			'type' => 'api',
		];

		$preferences[ SuggestedEdits::PREACTIVATED_PREF ] = [
			'type' => 'api',
		];

		$preferences[ SuggestedEdits::getTopicFiltersPref( $this->config ) ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::TOPICS_MATCH_MODE_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::TASKTYPES_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::GUIDANCE_BLUE_DOT_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::ADD_LINK_ONBOARDING_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::ADD_IMAGE_ONBOARDING_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::ADD_IMAGE_CAPTION_ONBOARDING_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::ADD_SECTION_IMAGE_CAPTION_ONBOARDING_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::ADD_SECTION_IMAGE_ONBOARDING_PREF ] = [
			'type' => 'api'
		];

		if ( LevelingUpManager::isEnabledForAnyone( $this->config ) ) {
			$preferences[LevelingUpManager::TASK_TYPE_PROMPT_OPT_OUTS_PREF] = [
				'type' => 'api'
			];
		}
	}

	/**
	 * Register defaults for homepage-related preferences.
	 *
	 * @param array &$defaultOptions
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			// Set discovery notice seen flag to true; it will be changed for new users in the
			// LocalUserCreated hook.
			self::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN => true,
			// Disable blue dot on Edit tab for link-recommendation tasks.
			SuggestedEdits::GUIDANCE_BLUE_DOT_PREF => json_encode( [
				'vector' => [
					LinkRecommendationTaskTypeHandler::ID => true,
					ImageRecommendationTaskTypeHandler::ID => true,
				],
				'minerva' => [
					LinkRecommendationTaskTypeHandler::ID => true,
					ImageRecommendationTaskTypeHandler::ID => true,
				]
			] ),
			SuggestedEdits::TOPICS_MATCH_MODE_PREF => SearchStrategy::TOPIC_MATCH_MODE_OR,
			self::HOMEPAGE_PREF_ENABLE => false,
			self::HOMEPAGE_PREF_PT_LINK => false,
		];
	}

	/** @inheritDoc */
	public function onResourceLoaderExcludeUserOptions(
		array &$keysToExclude,
		RL\Context $context
	): void {
		$keysToExclude = array_merge( $keysToExclude, [
			self::HOMEPAGE_PREF_ENABLE,
			self::HOMEPAGE_PREF_PT_LINK,
			self::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN,
			Mentorship::QUESTION_PREF,
			SuggestedEdits::PREACTIVATED_PREF,
		] );
	}

	/**
	 * Pass through the debug flag used by LocalUserCreated.
	 * @inheritDoc
	 */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$request = RequestContext::getMain()->getRequest();

		$geForceVariant = $request->getVal( 'geForceVariant' );
		if ( $geForceVariant !== null ) {
			$formDescriptor['geForceVariant'] = [
				'type' => 'hidden',
				'name' => 'geForceVariant',
				'default' => $geForceVariant,
			];
		}

		$formDescriptor[self::REGISTRATION_GROWTHEXPERIMENTS_ENABLED] = [
			'type' => 'hidden',
			'name' => self::REGISTRATION_GROWTHEXPERIMENTS_ENABLED,
			'default' => $request->getInt( self::REGISTRATION_GROWTHEXPERIMENTS_ENABLED, -1 )
		];
	}

	/**
	 * Check if a user opted-in or opted-out from Growth features
	 *
	 * @return int One of GROWTH_FORCE_* constants
	 */
	public static function getGrowthFeaturesOptInOptOutOverride(): int {
		$enableGrowthFeatures = RequestContext::getMain()
			->getRequest()
			->getInt( self::REGISTRATION_GROWTHEXPERIMENTS_ENABLED, -1 );
		if ( $enableGrowthFeatures === 1 ) {
			return self::GROWTH_FORCE_OPTIN;
		} elseif ( $enableGrowthFeatures === 0 ) {
			return self::GROWTH_FORCE_OPTOUT;
		} else {
			return self::GROWTH_FORCE_NONE;
		}
	}

	/**
	 * Enable the homepage for a percentage of new local accounts.
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @throws ConfigException
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $autocreated || !self::isHomepageEnabled() || $user->isTemp() ) {
			return;
		}

		$geForceVariant = RequestContext::getMain()->getRequest()
			->getVal( 'geForceVariant' );
		$growthOptInOptOutOverride = self::getGrowthFeaturesOptInOptOutOverride();

		if ( $growthOptInOptOutOverride === self::GROWTH_FORCE_OPTOUT ) {
			// Growth features cannot be enabled, short-circuit
			return;
		}

		// Enable the homepage for a percentage of non-autocreated users.
		$enablePercentage = $this->config->get( 'GEHomepageNewAccountEnablePercentage' );
		$wiki = WikiMap::getCurrentWikiId();
		if (
			$growthOptInOptOutOverride === self::GROWTH_FORCE_OPTIN ||
			$geForceVariant !== null ||
			rand( 0, 99 ) < $enablePercentage
		) {
			$this->statsFactory
				->withComponent( 'GrowthExperiments' )
				->getCounter( 'users_opted_into_growth_features_total' )
				->setLabel( 'wiki', $wiki )
				->copyToStatsdAt( $wiki . '.GrowthExperiments.UsersOptedIntoGrowthFeatures' )
				->increment();
			$this->userOptionsManager->setOption( $user, self::HOMEPAGE_PREF_ENABLE, 1 );
			$this->userOptionsManager->setOption( $user, self::HOMEPAGE_PREF_PT_LINK, 1 );
			// Default option is that the user has seen the tours/notices (so we don't prompt
			// existing users to view them). We set the option to false on new user accounts
			// so they see them once (and then the option gets reset for them).
			$this->userOptionsManager->setOption( $user, TourHooks::TOUR_COMPLETED_HELP_PANEL, 0 );
			$this->userOptionsManager->setOption( $user, TourHooks::TOUR_COMPLETED_HOMEPAGE_MENTORSHIP, 0 );
			$this->userOptionsManager->setOption( $user, TourHooks::TOUR_COMPLETED_HOMEPAGE_WELCOME, 0 );
			$this->userOptionsManager->setOption( $user, TourHooks::TOUR_COMPLETED_HOMEPAGE_DISCOVERY, 0 );
			$this->userOptionsManager->setOption( $user, self::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN, 0 );

			if (
				$this->config->get( 'GEHelpPanelNewAccountEnableWithHomepage' ) &&
				HelpPanel::isHelpPanelEnabled()
			) {
				$this->userOptionsManager->setOption( $user, HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE, 1 );
			}

			// Mentorship
			$mentorshipEnablePercentage = $this->config->get( 'GEMentorshipNewAccountEnablePercentage' );
			if ( rand( 0, 99 ) >= $mentorshipEnablePercentage ) {
				// the default value is enabled, to avoid removing mentorship from someone who used
				// to have it. Only setOption if the result is "do not enable".
				$this->userOptionsManager->setOption(
					$user,
					MentorPageMentorManager::MENTORSHIP_ENABLED_PREF,
					MentorManager::MENTORSHIP_DISABLED
				);
			}

			// Variant assignment for forced variants and variant metric logging. Wrapped in a deferred update because
			// CentralAuth generates the central user in a onLocalUserCreated hook, hence the order of execution is
			// not guaranteed. This is necessary so the getOption call to the USER_PREFERENCE has a chance to retrieve
			// a valid central user id. See also hook in ExperimentsHooks.php determining which a variant to assign
			DeferredUpdates::addCallableUpdate( function () use ( $user, $geForceVariant, $wiki ) {
				// Get the variant assigned by ExperimentUserDefaultsManager
				$variant = $this->userOptionsLookup->getOption( $user, VariantHooks::USER_PREFERENCE );
				// Maybe override variant with query parameter
				if ( $geForceVariant !== null
					&& $this->experimentUserManager->isValidVariant( $geForceVariant )
					&& $geForceVariant !== $variant
				) {
					$variant = $geForceVariant;
					$this->experimentUserManager->setVariant( $user, $variant );
					$this->userOptionsManager->saveOptions( $user );
				}
				$this->statsFactory
					->withComponent( 'GrowthExperiments' )
					->getCounter( 'user_variant_total' )
					->setLabel( 'wiki', $wiki )
					->setLabel( 'variant', $variant )
					->copyToStatsdAt( $wiki . '.GrowthExperiments.UserVariant.' . $variant )
					->increment();
			} );

			// Place an empty user impact object in the database table cache, to avoid
			// making an extra HTTP request on first visit to Special:Homepage.
			DeferredUpdates::addCallableUpdate( function () use ( $user ) {
				$userImpact = $this->userImpactLookup->getExpensiveUserImpact(
					$user,
					IDBAccessObject::READ_LATEST
				);
				if ( $userImpact ) {
					$this->userImpactStore->setUserImpact( $userImpact );
				}
			} );

			if ( SuggestedEdits::isEnabledForAnyone( $this->config ) ) {
				// Populate the cache of tasks with default task/topic selections
				// so that when the user lands on Special:Homepage, the request to retrieve tasks
				// will pull from the cached TaskSet instead of doing time consuming search queries.
				// With nuances in how mobile/desktop users are onboarded, this may not be always
				// necessary but does no harm to run for all newly created users.
				DeferredUpdates::addCallableUpdate( function () use ( $user ) {
					$taskSuggester = $this->taskSuggesterFactory->create();
					$taskSuggester->suggest(
						$user,
						new TaskSetFilters(
							$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
							$this->newcomerTasksUserOptionsLookup->getTopics( $user ),
							$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $user )
						)
					);
				} );

				$jobQueue = $this->jobQueueGroup->get( NotificationKeepGoingJob::JOB_NAME );
				if ( LevelingUpManager::isEnabledForAnyone( $this->config ) &&
					$this->experimentUserManager->isUserInVariant( $user, VariantHooks::VARIANT_CONTROL ) &&
					$jobQueue->delayedJobsEnabled() ) {
					$this->jobQueueGroup->lazyPush(
						new JobSpecification( NotificationKeepGoingJob::JOB_NAME, [
							'userId' => $user->getId(),
							// Process the job X seconds after account creation (default: 48 hours)
							'jobReleaseTimestamp' => (int)wfTimestamp() +
								$this->config->get( 'GELevelingUpKeepGoingNotificationSendAfterSeconds' )
						] )
					);
					$this->jobQueueGroup->lazyPush(
						new JobSpecification( NotificationGetStartedJob::JOB_NAME, [
							'userId' => $user->getId(),
							// Process the job X seconds after account creation (configured in extension.json)
							'jobReleaseTimestamp' => (int)wfTimestamp() +
								$this->config->get( 'GELevelingUpGetStartedNotificationSendAfterSeconds' )
						] )
					);
				}
			}
		} else {
			$this->statsFactory
				->withComponent( 'GrowthExperiments' )
				->getCounter( 'users_not_opted_into_growth_features_total' )
				->setLabel( 'wiki', $wiki )
				->copyToStatsdAt( $wiki . '.GrowthExperiments.UsersNotOptedIntoGrowthFeatures' )
				->increment();
		}
	}

	/**
	 * ListDefinedTags hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 *
	 * @param array &$tags The list of tags.
	 * @throws ConfigException
	 */
	public function onListDefinedTags( &$tags ) {
		if ( self::isHomepageEnabled() ) {
			$tags[] = Help::HELP_MODULE_QUESTION_TAG;
			$tags[] = Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
		}
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$tags[] = Mentorship::MENTORSHIP_HELPPANEL_QUESTION_TAG;
		}
		if ( SuggestedEdits::isEnabledForAnyone( $this->config ) ) {
			array_push( $tags, ...$this->taskTypeHandlerRegistry->getChangeTags() );
		}
	}

	/**
	 * ChangeTagsListActive hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 *
	 * @param array &$tags The list of tags.
	 * @throws ConfigException
	 */
	public function onChangeTagsListActive( &$tags ) {
		if ( self::isHomepageEnabled() ) {
			// Help::HELP_MODULE_QUESTION_TAG is no longer active (T232548)
			$tags[] = Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
		}
		if ( HelpPanel::isHelpPanelEnabled() ) {
			$tags[] = Mentorship::MENTORSHIP_HELPPANEL_QUESTION_TAG;
		}
		if ( SuggestedEdits::isEnabledForAnyone( $this->config ) ) {
			array_push( $tags, ...$this->taskTypeHandlerRegistry->getChangeTags() );
		}
	}

	/**
	 * Helper method to update the "Profile" menu entry in menus
	 * @param array &$links
	 */
	private function updateProfileMenuEntry( array &$links ) {
		$userItem = $links['user-menu']['userpage'] ?? [];
		if ( $userItem ) {
			$context = RequestContext::getMain();
			$userItem['href'] = $this->getPersonalToolsHomepageLinkUrl(
				$context->getTitle()->getNamespace()
			);
			unset( $links['user-menu']['userpage'] );
			unset( $links['user-page']['userpage'] );
			$links['user-menu'] = [
				'homepage' => $userItem,
			] + $links['user-menu'];
			$links['user-page'] = [
					'homepage' => $userItem,
					// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset the user-page is always set
			] + $links['user-page'];
		}
	}

	/**
	 * Helper method to update the "Home" menu entry in the Mobile Menu.
	 *
	 * We want "Home" to read "Main Page", because Special:Homepage is intended to be "Home"
	 * for users with Growth features.
	 *
	 * @param array &$sidebar
	 */
	private function updateHomeMenuEntry( array &$sidebar ) {
		foreach ( $sidebar['navigation'] ?? [] as $key => $item ) {
			$id = $item['id'] ?? null;
			if ( $id === 'n-mainpage-description' ) {
				// MinervaNeue's BuilderUtil::getDiscoveryTools will override 'text'
				// with the message key set in 'msg'.
				$item['msg'] = 'mainpage-nstab';
				$item['icon'] = 'newspaper';
				$sidebar['navigation'][$key] = $item;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		if ( !Util::isMobile( $skin ) ) {
			return;
		}
		$user = $skin->getUser();
		if ( !self::isHomepageEnabled( $user ) ) {
			return;
		}
		if ( $this->userHasPersonalToolsPrefEnabled( $user ) ) {
			$this->updateHomeMenuEntry( $sidebar );
		}
	}

	private static function getZeroContributionsHtml( SpecialPage $sp, string $wrapperClasses = '' ): string {
		$linkUrl = SpecialPage::getTitleFor( 'Homepage' )
			->getFullURL( [ 'source' => 'specialcontributions' ] );
		return Html::rawElement( 'div', [ 'class' => 'mw-ge-contributions-zero ' . $wrapperClasses ],
			Html::element( 'p', [ 'class' => 'mw-ge-contributions-zero-title' ],
				$sp->msg( 'growthexperiments-homepage-contributions-zero-title' )
					->params( $sp->getUser()->getName() )->text()
			) .
			Html::element( 'p', [ 'class' => 'mw-ge-contributions-zero-subtitle' ],
				$sp->msg( 'growthexperiments-homepage-contributions-zero-subtitle' )
					->params( $sp->getUser()->getName() )->text()
			) .
			new ButtonWidget( [
				'label' => $sp->msg( 'growthexperiments-homepage-contributions-zero-button' )
					->params( $sp->getUser()->getName() )->text(),
				'href' => $linkUrl,
				'flags' => [ 'primary', 'progressive' ]
			] )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onSpecialContributionsBeforeMainOutput( $userId, $user, $sp ) {
		if (
			$user->equals( $sp->getUser() ) &&
			$user->getEditCount() === 0 &&
			self::isHomepageEnabled( $user )
		) {
			$out = $sp->getOutput();
			$out->enableOOUI();
			$out->addModuleStyles( 'ext.growthExperiments.Account.styles' );
			$out->addHTML( self::getZeroContributionsHtml( $sp ) );
		}
	}

	/**
	 * @param User $user
	 */
	public function onConfirmEmailComplete( $user ) {
		// context user is used for cases when someone else than $user confirms the email,
		// and that user doesn't have homepage enabled
		if ( self::isHomepageEnabled( RequestContext::getMain()->getUser() ) ) {
			RequestContext::getMain()->getOutput()
				->redirect( SpecialPage::getTitleFor( 'Homepage' )
					->getFullUrlForRedirect( [
						'source' => self::CONFIRMEMAIL_QUERY_PARAM,
						'namespace' => NS_SPECIAL
					] )
			);
		}
	}

	/**
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @return bool|void
	 * @throws ConfigException
	 */
	public function onSiteNoticeAfter( &$siteNotice, $skin ) {
		global $wgMinervaEnableSiteNotice;
		if ( self::isHomepageEnabled( $skin->getUser() ) ) {
			$siteNoticeGenerator = new SiteNoticeGenerator(
				$this->experimentUserManager,
				$this->userOptionsLookup,
				$this->jobQueueGroup
			);
			return $siteNoticeGenerator->setNotice(
				$skin->getRequest()->getVal( 'source' ),
				$siteNotice,
				$skin,
				$wgMinervaEnableSiteNotice
			);
		}
	}

	private function clearLinkRecommendationRecordForPage( WikiPage $wikiPage ): void {
		try {
			$this->linkRecommendationHelper->deleteLinkRecommendation(
				$wikiPage->getTitle()->toPageIdentity(), true, true );
		} catch ( DBReadOnlyError $e ) {
			// Leaving a dangling DB row behind doesn't cause any problems so just ignore this.
		}
	}

	/**
	 * ResourceLoader callback used by our custom ResourceLoaderFileModuleWithLessVars class.
	 * @param RL\Context $context
	 * @return array An array of LESS variables
	 */
	public static function lessCallback( RL\Context $context ) {
		$isMobile = $context->getSkin() === 'minerva';
		return [
			// used in Homepage.SuggestedEdits.less
			'cardContainerWrapperHeight' => $isMobile ? '16em' : '20.5em',
			'cardImageHeight' => $isMobile ? '128px' : '188px',
			'cardWrapperWidth' => $isMobile ? '260px' : '332px',
			'cardWrapperWidthLegacy' => '260px',
			'cardWrapperPadding' => $isMobile ? '0' : '8px',
			'cardWrapperBorderRadius' => $isMobile ? '0' : '2px',
			'cardContentTextPadding' => $isMobile ? '0 16px 8px 16px' : '0 8px',
			'cardExtractHeight' => $isMobile ? '4.5em' : '3em',
			'cardPageviewsTopPadding' => $isMobile ? '10px' : '16px',
			'cardPageviewsIconMarginBottom' => $isMobile ? '0' : '4px',
		];
	}

	/**
	 * ResourceLoader JSON package callback for getting the task types defined on the wiki.
	 * @param RL\Context $context
	 * @return array
	 *   - on success: [ task type id => task data, ... ]; see TaskType::toArray for data format.
	 *     Note that the messages in the task data are plaintext and it is the caller's
	 *     responsibility to escape them.
	 *   - on error: [ '_error' => error message in wikitext format ]
	 */
	public static function getTaskTypesJson( RL\Context $context ) {
		// Based on user variant settings, some task types might need to be hidden for the user,
		// but we can't access user identity here, so we return all tasks. User-specific filtering
		// will be done on the client side in TaskTypeAbFilter.
		$configurationLoader = self::getConfigurationLoaderForResourceLoader( $context );
		$taskTypes = $configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			$errorMessages = array_map(
				static fn ( $spec ) => $context->msg( $spec )->parse(),
				$taskTypes->getMessages()
			);
			return [
				'_error' => $errorMessages[0],
			];
		} else {
			$taskTypesData = [];
			foreach ( $taskTypes as $taskType ) {
				$taskTypesData[$taskType->getId()] = $taskType->getViewData( $context );
			}
			return $taskTypesData;
		}
	}

	/**
	 * ResourceLoader JSON package callback for getting the default task types when the user
	 * does not have SuggestedEdits::TASKTYPES_PREF set.
	 * @param RL\Context $context
	 * @return string[]
	 */
	public static function getDefaultTaskTypesJson( RL\Context $context ) {
		// Like with getTaskTypesJson, we ignore user-specific filtering here.
		return SuggestedEdits::DEFAULT_TASK_TYPES;
	}

	/**
	 * ResourceLoader JSON package callback for getting the topics defined on the wiki.
	 * Some UI elements will be disabled if this returns an empty array.
	 * @param RL\Context $context
	 * @return array
	 *   - on success: [ topic id => topic data, ... ]; see Topic::toArray for data format.
	 *     Note that the messages in the task data are plaintext and it is the caller's
	 *     responsibility to escape them.
	 *   - on error: [ '_error' => error message in wikitext format ]
	 */
	public static function getTopicsJson( RL\Context $context ) {
		$configurationLoader = self::getConfigurationLoaderForResourceLoader( $context );
		$topics = $configurationLoader->loadTopics();
		if ( $topics instanceof StatusValue ) {
			$errorMessages = array_map(
				static fn ( $spec ) => $context->msg( $spec )->parse(),
				$topics->getMessages()
			);
			return [
				'_error' => $errorMessages[0],
			];
		} else {
			$topicsData = [];
			foreach ( $topics as $topic ) {
				$topicsData[$topic->getId()] = $topic->getViewData( $context );
			}
			return $topicsData;
		}
	}

	/**
	 * ResourceLoader JSON package callback for getting the AQS domain to use.
	 * @return stdClass
	 */
	public static function getAQSConfigJson() {
		return MediaWikiServices::getInstance()->getService( '_GrowthExperimentsAQSConfig' );
	}

	/**
	 * ResourceLoader JSON package callback for getting config variables that are shared between
	 * SuggestedEdits and StartEditingDialog
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function getSuggestedEditsConfigJson(
		RL\Context $context, Config $config
	) {
		// Note: GELinkRecommendationsEnabled / GEImageRecommendationsEnabled reflect PHP configuration.
		// Checking whether these task types have been disabled in community configuration is the
		// frontend code's responsibility (handled in TaskTypeAbFilter).
		return [
			'GESearchTaskSuggesterDefaultLimit' => SearchTaskSuggester::DEFAULT_LIMIT,
			'GERestbaseUrl' => Util::getRestbaseUrl( $config ),
			'GENewcomerTasksRemoteArticleOrigin' => $config->get( 'GENewcomerTasksRemoteArticleOrigin' ),
			'GEHomepageSuggestedEditsIntroLinks' => self::getGrowthWikiConfig()
				->get( 'GEHomepageSuggestedEditsIntroLinks' ),
			'GENewcomerTasksTopicFiltersPref' => SuggestedEdits::getTopicFiltersPref( $config ),
			'GELinkRecommendationsEnabled' => $config->get( 'GENewcomerTasksLinkRecommendationsEnabled' )
				&& $config->get( 'GELinkRecommendationsFrontendEnabled' ),
			'GEImageRecommendationsEnabled' => $config->get( 'GENewcomerTasksImageRecommendationsEnabled' ),
			'GENewcomerTasksSectionImageRecommendationsEnabled' =>
				$config->get( 'GENewcomerTasksSectionImageRecommendationsEnabled' ),
		];
	}

	/**
	 * Helper method for ResourceLoader callbacks.
	 *
	 * @param RL\Context $context
	 * @return ConfigurationLoader
	 */
	private static function getConfigurationLoaderForResourceLoader(
		RL\Context $context
	): ConfigurationLoader {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		// Hack - RL\Context is not exposed to services initialization
		$configurationValidator = $growthServices->getNewcomerTasksConfigurationValidator();
		$configurationValidator->setMessageLocalizer( $context );
		return $growthServices->getNewcomerTasksConfigurationLoader();
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	private function userHasPersonalToolsPrefEnabled( User $user ): bool {
		return $user->isNamed() &&
			$this->userOptionsLookup->getBoolOption( $user, self::HOMEPAGE_PREF_PT_LINK );
	}

	/**
	 * Check whether the user has disabled VisualEditor while it's in beta
	 *
	 * @param UserIdentity $user
	 * @param UserOptionsLookup $userOptionsLookup
	 * @return bool
	 */
	private static function userHasDisabledVe(
		UserIdentity $user,
		UserOptionsLookup $userOptionsLookup
	): bool {
		return $userOptionsLookup->getBoolOption( $user, self::VE_PREF_DISABLE_BETA );
	}

	/**
	 * Check whether the user prefers source editor
	 *
	 * @param UserIdentity $user
	 * @param UserOptionsLookup $userOptionsLookup
	 * @return bool
	 */
	private static function userPrefersSourceEditor(
		UserIdentity $user,
		UserOptionsLookup $userOptionsLookup
	): bool {
		return $userOptionsLookup->getOption( $user, self::VE_PREF_EDITOR ) === 'prefer-wt';
	}

	/**
	 * Get URL to Special:Homepage with query parameters appended for EventLogging.
	 * @param int $namespace
	 * @return string
	 */
	private function getPersonalToolsHomepageLinkUrl( int $namespace ): string {
		return $this->titleFactory->newFromLinkTarget(
			new TitleValue( NS_SPECIAL, $this->specialPageFactory->getLocalNameFor( 'Homepage' ) )
		)->getLinkURL(
			'source=personaltoolslink&namespace=' . $namespace
		);
	}

	/**
	 * @inheritDoc
	 * This gets called when the autocomment is rendered.
	 *
	 * For add link and add image tasks, localize the edit summary in the viewer's language.
	 */
	public function onFormatAutocomments( &$comment, $pre, $auto, $post, $title,
										  $local, $wikiId
	) {
		$allowedMessageKeys = [
			'growthexperiments-addlink-summary-summary',
			'growthexperiments-addimage-summary-summary',
			'growthexperiments-addsectionimage-summary-summary',
		];
		$messageParts = explode( ':', $auto, 2 );
		$messageKey = $messageParts[ 0 ];
		if ( in_array( $messageKey, $allowedMessageKeys ) ) {
			$messageParamsStr = $messageParts[ 1 ] ?? '';
			$comment = wfMessage( $messageKey )
				->numParams( ...explode( '|', $messageParamsStr ) )
				->parse();
		}
	}

	/**
	 * Update the user's editor preference based on the given task type and whether the user prefers
	 * the source editor. The preference is not saved so the override doesn't persist beyond
	 * the suggested edit session.
	 *
	 * @param TaskType $taskType
	 * @param UserIdentity $user
	 */
	private function maybeOverridePreferredEditorWithVE(
		TaskType $taskType, UserIdentity $user
	): void {
		if ( $taskType->shouldOpenInEditMode() ) {
			return;
		}

		if ( self::userPrefersSourceEditor( $user, $this->userOptionsManager ) ) {
			return;
		}

		if ( self::userHasDisabledVe( $user, $this->userOptionsManager ) ) {
			return;
		}

		$this->userOptionsManager->setOption(
			$user,
			self::VE_PREF_EDITOR, 'visualeditor'
		);
	}

	/**
	 * @param SearchConfig $config
	 * @param array &$extraFeatures Array holding KeywordFeature objects
	 */
	public static function onCirrusSearchAddQueryFeatures( SearchConfig $config, array &$extraFeatures ) {
		$mwServices = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $mwServices );
		$configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$taskTypes = $configurationLoader->getTaskTypes();
		$infoboxTemplates = $configurationLoader->loadInfoboxTemplates();
		// FIXME remove once GrowthExperiments drops support for CC1.0
		if ( $infoboxTemplates instanceof StatusValue ) {
			$infoboxTemplates = $growthServices->getGrowthWikiConfig()->get( 'GEInfoboxTemplates' );
		}
		$templateCollectionFeature = new TemplateCollectionFeature(
			'infobox', $infoboxTemplates, $mwServices->getTitleFactory()
		);
		foreach ( $taskTypes as $taskType ) {
			if ( $taskType instanceof TemplateBasedTaskType ) {
				$templateCollectionFeature->addCollection( $taskType->getId(), $taskType->getTemplates() );
			}
		}
		$extraFeatures[] = $templateCollectionFeature;
	}

	/** @inheritDoc */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ): void {
		if (
			$this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) &&
			$wikiPage->getNamespace() === NS_MAIN
		) {
			$this->clearLinkRecommendationRecordForPage( $wikiPage );
		}

		if ( $editResult->isRevert() ) {
			$this->trackRevertedNewcomerTaskEdit( $editResult );
		}
	}

	/** @inheritDoc */
	public function onRecentChange_save( $recentChange ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$user = $recentChange->getPerformerIdentity();
		if ( !$this->userIdentityUtils->isNamed( $user ) ) {
			return;
		}
		$plugins = $request->getVal( 'plugins', '' );
		if ( !$plugins ) {
			return;
		}
		$pluginData = json_decode( $request->getVal( 'data-' . $plugins, '' ), true );

		if ( !$pluginData || !isset( $pluginData['taskType'] ) ) {
			return;
		}
		if ( !SuggestedEdits::isEnabledForAnyone( $context->getConfig() ) ) {
			return;
		}
		if ( SuggestedEdits::isActivated( $context->getUser(), $this->userOptionsLookup ) ) {
			$taskTypeId = $pluginData['taskType'];
			$tags = $this->newcomerTasksChangeTagsManager->getTags(
				$taskTypeId,
				$recentChange->getPerformerIdentity()
			);
			if ( $tags->isGood() ) {
				$recentChange->addTags( $tags->getValue() );
			}
		}
	}

	/**
	 * @param array $function Function definition. The 'type' field holds the function name.
	 * @param SearchContext $context
	 * @param BoostFunctionBuilder|null &$builder Score builder output variable.
	 * @return bool|void
	 * @see https://www.mediawiki.org/wiki/Extension:CirrusSearch/Hooks/CirrusSearchScoreBuilder
	 */
	public function onCirrusSearchScoreBuilder(
		array $function,
		SearchContext $context,
		?BoostFunctionBuilder &$builder
	) {
		if ( $function['type'] === UnderlinkedFunctionScoreBuilder::TYPE ) {
			$taskTypes = $this->configurationLoader->getTaskTypes();
			$linkRecommendationTaskType = $taskTypes[LinkRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
			if ( $linkRecommendationTaskType instanceof LinkRecommendationTaskType ) {
				$builder = new UnderlinkedFunctionScoreBuilder(
					$linkRecommendationTaskType->getUnderlinkedWeight(),
					$linkRecommendationTaskType->getUnderlinkedMinLength()
				);
				return false;
			}
			// Not doing anything will result in a Cirrus error about a non-existent function type,
			// which seems like a reasonable way to handle the case of using underlinked weighting
			// on a wiki with no link recommendation task type.
		}
	}

	/** @inheritDoc */
	public function onContributeCards( array &$cards ): void {
		$userIdentity = $this->userIdentity ?? RequestContext::getMain()->getUser();
		if ( !$this->isHomepageEnabledGloballyAndForUser( $userIdentity ) ) {
			return;
		}
		$messageLocalizer = $this->messageLocalizer ?? RequestContext::getMain();
		$homepageTitle = $this->titleFactory->newFromLinkTarget(
			new TitleValue( NS_SPECIAL, $this->specialPageFactory->getLocalNameFor( 'Homepage' ) )
		);
		$homepageTitle->setFragment( '#/homepage/suggested-edits' );
		$cards[] = ( new ContributeCard(
			$messageLocalizer->msg( 'growthexperiments-homepage-special-contribute-title' )->text(),
			$messageLocalizer->msg( 'growthexperiments-homepage-special-contribute-description' )->text(),
			'lightbulb',
			new ContributeCardActionLink(
				$homepageTitle->getLinkURL( wfArrayToCgi( [
					'source' => 'specialcontribute',
					'namespace' => NS_SPECIAL,
					// on mobile, avoids the flash of Special:Homepage before routing to the
					// Suggested Edits overlay. On desktop, has no effect.
					'overlay' => 1
				] ) ),
				$messageLocalizer->msg( 'growthexperiments-homepage-special-contribute-cta' )->text(),
			)
		) )->toArray();
		$out = $this->outputPage ?? RequestContext::getMain()->getOutput();
		$out->addModuleStyles( 'oojs-ui.styles.icons-interactions' );
	}

	/**
	 * Allow setting a MessageLocalizer for the class. For testing purposes.
	 *
	 * @param MessageLocalizer $messageLocalizer
	 * @return void
	 */
	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ): void {
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Allow setting an OutputPage for the class. For testing purposes.
	 *
	 * @param OutputPage $outputPage
	 * @return void
	 */
	public function setOutputPage( OutputPage $outputPage ): void {
		$this->outputPage = $outputPage;
	}

	/**
	 * Allow setting the active UserIdentity for the class. For testing purposes.
	 *
	 * @param UserIdentity $userIdentity
	 * @return void
	 */
	public function setUserIdentity( UserIdentity $userIdentity ): void {
		$this->userIdentity = $userIdentity;
	}

	private function trackRevertedNewcomerTaskEdit( EditResult $editResult ): void {
		$revId = $editResult->getNewestRevertedRevisionId();
		if ( !$revId ) {
			return;
		}
		$tags = MediaWikiServices::getInstance()->getChangeTagsStore()->getTags(
			$this->lb->getConnection( DB_REPLICA ),
			null,
			$revId
		);
		$growthTasksChangeTags = array_merge(
			TemplateBasedTaskTypeHandler::NEWCOMER_TASK_TEMPLATE_BASED_ALL_CHANGE_TAGS,
			[
				LinkRecommendationTaskTypeHandler::CHANGE_TAG,
				ImageRecommendationTaskTypeHandler::CHANGE_TAG,
				SectionImageRecommendationTaskTypeHandler::CHANGE_TAG,
			]
		);
		foreach ( $tags as $tag ) {
			// We can use more precise tags, skip this generic one applied to all suggested edits.
			if ( $tag === TaskTypeHandler::NEWCOMER_TASK_TAG ||
				// ...but make sure the tag is one we care about tracking.
				!in_array( $tag, $growthTasksChangeTags ) ) {
				continue;
			}
			// HACK: craft the task type ID from the change tag. We should probably add a method to
			// TaskTypeHandlerRegistry to get a TaskType from a change tag.
			$taskType = str_replace( 'newcomer task ', '', $tag );
			if ( $tag === LinkRecommendationTaskTypeHandler::CHANGE_TAG ) {
				$taskType = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;
			} elseif ( $tag === ImageRecommendationTaskTypeHandler::CHANGE_TAG ) {
				$taskType = ImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
			} elseif ( $tag === SectionImageRecommendationTaskTypeHandler::CHANGE_TAG ) {
				$taskType = SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
			}
			$wiki = WikiMap::getCurrentWikiId();
			$this->statsFactory
				->withComponent( 'GrowthExperiments' )
				->getCounter( 'newcomertask_reverted_total' )
				->setLabel( 'taskType', $taskType )
				->setLabel( 'wiki', $wiki )
				->copyToStatsdAt( sprintf( "$wiki.GrowthExperiments.NewcomerTask.Reverted.%s", $taskType ) )
				->increment();
		}
	}
}
