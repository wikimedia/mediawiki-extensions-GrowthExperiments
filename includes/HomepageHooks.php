<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace GrowthExperiments;

use CirrusSearch\Search\CirrusIndexField;
use CirrusSearch\Wikimedia\WeightedTagsHooks;
use Config;
use ConfigException;
use DeferredUpdates;
use DomainException;
use EchoAttributeManager;
use EchoUserLocator;
use GrowthExperiments\Config\GrowthConfigLoaderStaticTrait;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\Homepage\SiteNoticeGenerator;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\Mentorship\EchoMentorChangePresentationModel;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\SuggestionsInfo;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use GrowthExperiments\Specials\SpecialClaimMentee;
use GrowthExperiments\Specials\SpecialHomepage;
use GrowthExperiments\Specials\SpecialImpact;
use GrowthExperiments\Specials\SpecialNewcomerTasksInfo;
use Html;
use IBufferingStatsdDataFactory;
use IContextSource;
use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\Menu\Entries\HomeMenuEntry;
use MediaWiki\Minerva\Menu\Entries\IProfileMenuEntry;
use MediaWiki\Minerva\Menu\Group;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use NamespaceInfo;
use OOUI\ButtonWidget;
use OutputPage;
use RecentChange;
use RequestContext;
use ResourceLoaderContext;
use Skin;
use SkinTemplate;
use SpecialContributions;
use SpecialPage;
use Status;
use StatusValue;
use stdClass;
use Title;
use TitleFactory;
use User;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\ILoadBalancer;

class HomepageHooks implements
	\MediaWiki\SpecialPage\Hook\SpecialPage_initListHook,
	\MediaWiki\Hook\BeforePageDisplayHook,
	\MediaWiki\Hook\SkinTemplateNavigation__UniversalHook,
	\MediaWiki\Hook\PersonalUrlsHook,
	\MediaWiki\Cache\Hook\MessageCache__getHook,
	\MediaWiki\Preferences\Hook\GetPreferencesHook,
	\MediaWiki\User\Hook\UserGetDefaultOptionsHook,
	\MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook,
	\MediaWiki\Auth\Hook\LocalUserCreatedHook,
	\MediaWiki\ChangeTags\Hook\ListDefinedTagsHook,
	\MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook,
	\MediaWiki\Hook\RecentChange_saveHook,
	\MediaWiki\Storage\Hook\PageSaveCompleteHook,
	\MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook,
	\MediaWiki\SpecialPage\Hook\SpecialPageAfterExecuteHook,
	\MediaWiki\User\Hook\ConfirmEmailCompleteHook,
	\MediaWiki\Hook\SiteNoticeAfterHook,
	\MediaWiki\Content\Hook\SearchDataForIndexHook
{
	use GrowthConfigLoaderStaticTrait;

	public const HOMEPAGE_PREF_ENABLE = 'growthexperiments-homepage-enable';
	public const HOMEPAGE_PREF_PT_LINK = 'growthexperiments-homepage-pt-link';
	/** @var string User options key for storing whether the user has seen the notice. */
	public const HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN = 'homepage_mobile_discovery_notice_seen';
	public const CONFIRMEMAIL_QUERY_PARAM = 'specialconfirmemail';

	public const GROWTH_FORCE_OPTIN = 1;
	public const GROWTH_FORCE_OPTOUT = 2;
	public const GROWTH_FORCE_NONE = 3;

	/** @var Config */
	private $config;
	/** @var Config */
	private $wikiConfig;
	/** @var ILoadBalancer */
	private $lb;
	/** @var UserOptionsLookup */
	private $userOptionsLookup;
	/** @var NamespaceInfo */
	private $namespaceInfo;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;
	/** @var ConfigurationLoader */
	private $configurationLoader;
	/** @var TrackerFactory */
	private $trackerFactory;
	/** @var ExperimentUserManager */
	private $experimentUserManager;
	/** @var HomepageModuleRegistry */
	private $moduleRegistry;
	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;
	/** @var TaskSuggesterFactory */
	private $taskSuggesterFactory;
	/** @var NewcomerTasksUserOptionsLookup */
	private $newcomerTasksUserOptionsLookup;
	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;
	/** @var LinkRecommendationHelper */
	private $linkRecommendationHelper;
	/** @var SuggestionsInfo */
	private $suggestionsInfo;

	/** @var bool Are we in a context where it is safe to access the primary DB? */
	private $canAccessPrimary;

	/**
	 * @param Config $config Uses PHP globals
	 * @param Config $wikiConfig Uses on-wiki config store, only for variables listed in
	 *  GrowthExperimentsMultiConfig::ALLOW_LIST.
	 * @param ILoadBalancer $lb
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param NamespaceInfo $namespaceInfo
	 * @param TitleFactory $titleFactory
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param ConfigurationLoader $configurationLoader
	 * @param TrackerFactory $trackerFactory
	 * @param ExperimentUserManager $experimentUserManager
	 * @param HomepageModuleRegistry $moduleRegistry
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup
	 * @param LinkRecommendationStore $linkRecommendationStore
	 * @param LinkRecommendationHelper $linkRecommendationHelper
	 * @param SuggestionsInfo $suggestionsInfo
	 */
	public function __construct(
		Config $config,
		Config $wikiConfig,
		ILoadBalancer $lb,
		UserOptionsLookup $userOptionsLookup,
		NamespaceInfo $namespaceInfo,
		TitleFactory $titleFactory,
		IBufferingStatsdDataFactory $statsdDataFactory,
		ConfigurationLoader $configurationLoader,
		TrackerFactory $trackerFactory,
		ExperimentUserManager $experimentUserManager,
		HomepageModuleRegistry $moduleRegistry,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		LinkRecommendationStore $linkRecommendationStore,
		LinkRecommendationHelper $linkRecommendationHelper,
		SuggestionsInfo $suggestionsInfo
	) {
		$this->config = $config;
		$this->wikiConfig = $wikiConfig;
		$this->lb = $lb;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->namespaceInfo = $namespaceInfo;
		$this->titleFactory = $titleFactory;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->configurationLoader = $configurationLoader;
		$this->trackerFactory = $trackerFactory;
		$this->experimentUserManager = $experimentUserManager;
		$this->moduleRegistry = $moduleRegistry;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->linkRecommendationStore = $linkRecommendationStore;
		$this->linkRecommendationHelper = $linkRecommendationHelper;
		$this->suggestionsInfo = $suggestionsInfo;

		// Ideally this would be injected but the way hook handlers are defined makes that hard.
		$this->canAccessPrimary = defined( 'MEDIAWIKI_JOB_RUNNER' )
			|| MediaWikiServices::getInstance()->getMainConfig()->get( 'CommandLineMode' )
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
			$mwServices = MediaWikiServices::getInstance();
			$pageViewInfoEnabled = \ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
			$list['Homepage'] = function () use ( $pageViewInfoEnabled, $mwServices ) {
				return new SpecialHomepage(
					$this->moduleRegistry,
					$this->trackerFactory,
					$this->statsdDataFactory,
					$this->experimentUserManager,
					$this->wikiConfig,
					$mwServices->getUserOptionsManager()
				);
			};
			if ( $pageViewInfoEnabled && $this->config->get( 'GEHomepageImpactModuleEnabled' ) ) {
				$list['Impact'] = function () use ( $mwServices ) {
					return new SpecialImpact(
						$this->lb->getLazyConnectionRef( DB_REPLICA ),
						$this->experimentUserManager,
						$this->titleFactory,
						GrowthExperimentsServices::wrap(
							$mwServices
						)->getGrowthWikiConfig(),
						$this->userOptionsLookup,
						$mwServices->get( 'PageViewService' )
					);
				};
			}
			$list[ 'ClaimMentee' ] = [
				'class' => SpecialClaimMentee::class,
				'services' => [
					'GrowthExperimentsMentorManager',
					'GrowthExperimentsMentorStore',
					'GrowthExperimentsMultiConfig'
				]
			];
			$list['NewcomerTasksInfo'] = function () use ( $mwServices ) {
				return new SpecialNewcomerTasksInfo(
					$this->suggestionsInfo,
					$mwServices->getMainWANObjectCache()
				);
			};
		}
	}

	/**
	 * @param UserIdentity|null $user
	 * @return bool
	 * @throws ConfigException
	 */
	public static function isHomepageEnabled( UserIdentity $user = null ) {
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
	 * Get the click ID from the URL if set (from clicking a suggested edit card).
	 *
	 * @param IContextSource $context
	 * @return string|null
	 */
	public static function getClickId( IContextSource $context ) {
		if ( SuggestedEdits::isEnabled( $context ) ) {
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
	public function onBeforePageDisplay( $out, $skin ) : void {
		$context = $out->getContext();
		if ( $context->getTitle()->inNamespaces( NS_MAIN, NS_TALK ) &&
			SuggestedEdits::isEnabled( $context ) ) {
			// Manage the suggested edit session.
			$out->addModules( 'ext.growthExperiments.SuggestedEditSession' );
		}

		if ( $context->getTitle()->inNamespaces( NS_MAIN, NS_TALK ) ) {
			$clickId = self::getClickId( $context );
			if ( $clickId ) {
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

				if (
					$context->getRequest()->getVal( 'getasktype' ) === LinkRecommendationTaskTypeHandler::TASK_TYPE_ID
				) {
					try {
						$linkRecommendations = $this->linkRecommendationHelper
							->getLinkRecommendation( $context->getTitle() );
						if ( $linkRecommendations ) {
							$linkRecommendations = $linkRecommendations->toArray();
						} else {
							$linkRecommendations = [ 'error' => 'None of the links in the recommendation are valid' ];
						}
					} catch ( ErrorException $e ) {
						$linkRecommendations = [ 'error' => $e->getErrorMessageInEnglish() ];
					}
					$out->addJsConfigVars( [
						'wgGESuggestedEditData' => $linkRecommendations
					] );
				}
			}
		}
		if ( !self::isHomepageEnabled( $skin->getUser() ) || !Util::isMobile( $skin ) ) {
			return;
		}
		$out->addModuleStyles( 'ext.growthExperiments.mobileMenu.icons' );
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
		if ( !self::isHomepageEnabled( $skin->getUser() ) ) {
			return;
		}
		if ( $skin->getTitle()->isSpecial( 'Homepage' ) ||
			 self::titleIsUserPageOrUserTalk( $skin->getTitle(), $skin->getUser() ) ) {
			$skinOptions->setMultiple( [
				SkinOptions::TALK_AT_TOP => true,
				SkinOptions::TABS_ON_SPECIALS => true,
			] );
		}
	}

	/**
	 * Make sure user pages have "User", "talk" and "homepage" tabs.
	 *
	 * @param SkinTemplate $skin
	 * @param array &$links
	 * @throws \MWException
	 * @throws ConfigException
	 */
	public function onSkinTemplateNavigation__Universal( $skin, &$links ) : void {
		$user = $skin->getUser();
		if ( !self::isHomepageEnabled( $user ) ) {
			return;
		}

		$title = $skin->getTitle();
		$homepageTitle = SpecialPage::getTitleFor( 'Homepage' );
		$userpage = $user->getUserPage();
		$usertalk = $user->getTalkPage();

		$isHomepage = $title->equals( $homepageTitle );
		$isUserSpace = $title->equals( $userpage ) || $title->isSubpageOf( $userpage );
		$isUserTalkSpace = $title->equals( $usertalk ) || $title->isSubpageOf( $usertalk );

		$isMobile = Util::isMobile( $skin );

		if ( $isHomepage || $isUserSpace || $isUserTalkSpace ) {
			unset( $links['namespaces']['special'] );
			unset( $links['namespaces']['user'] );
			unset( $links['namespaces']['user_talk'] );

			// T250554: If user currently views a subpage, direct him to the subpage talk page
			if ( !$isHomepage ) {
				$subjectpage = $this->namespaceInfo->getSubjectPage( $title );
				$talkpage = $this->namespaceInfo->getTalkPage( $title );

				if ( $subjectpage instanceof \TitleValue ) {
					$subjectpage = Title::newFromTitleValue( $subjectpage );
				}
				if ( $talkpage instanceof \TitleValue ) {
					$talkpage = Title::newFromTitleValue( $talkpage );
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

	private static function titleIsUserPageOrUserTalk( Title $title, User $user ) {
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
	 * @param array &$personal_urls
	 * @param Title &$title
	 * @param SkinTemplate $sk
	 * @throws \MWException
	 * @throws ConfigException
	 */
	public function onPersonalUrls( &$personal_urls, &$title, $sk ) : void {
		$user = $sk->getUser();
		if ( !self::isHomepageEnabled( $user ) || Util::isMobile( $sk ) ) {
			return;
		}

		if ( self::userHasPersonalToolsPrefEnabled( $user, $this->userOptionsLookup ) ) {
			$personal_urls['userpage']['href'] = self::getPersonalToolsHomepageLinkUrl(
				$title->getNamespace()
			);
			// Make the link blue
			unset( $personal_urls['userpage']['link-class'] );
			// Remove the "this page doesn't exist" part of the tooltip
			$personal_urls['userpage' ]['exists'] = true;
		}
	}

	/**
	 * Change the tooltip of the userpage link when it point to Special:Homepage
	 *
	 * @param string &$lcKey message key to check and possibly convert
	 * @throws ConfigException
	 */
	public function onMessageCache__get( &$lcKey ) {
		$user = RequestContext::getMain()->getUser();
		if (
			$lcKey === 'tooltip-pt-userpage' &&
			self::isHomepageEnabled( $user ) &&
			self::userHasPersonalToolsPrefEnabled( $user, $this->userOptionsLookup )
		) {
			$lcKey = 'tooltip-pt-homepage';
		}
	}

	/**
	 * Register preferences to control the homepage.
	 *
	 * @param User $user
	 * @param array &$preferences Preferences object
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

		$preferences[ self::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN ] = [
			'type' => 'api',
		];

		$preferences[ Tutorial::TUTORIAL_PREF ] = [
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

		$preferences[ SuggestedEdits::TASKTYPES_PREF ] = [
			'type' => 'api'
		];

		if ( $this->config->get( 'GEHomepageSuggestedEditsTopicsRequiresOptIn' ) ) {
			$preferences[ SuggestedEdits::TOPICS_ENABLED_PREF ] = [
				'type' => 'api'
			];
		}

		$preferences[ SuggestedEdits::GUIDANCE_BLUE_DOT_PREF ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::ADD_LINK_ONBOARDING_PREF ] = [
			'type' => 'api'
		];
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
				],
				'minerva' => [
					LinkRecommendationTaskTypeHandler::ID => true
				]
			] )
		];
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

		$geEnabled = $request->getInt( 'geEnabled', -1 );
		if ( $geEnabled !== null ) {
			$formDescriptor['geEnabled'] = [
				'type' => 'hidden',
				'name' => 'geEnabled',
				'default' => $geEnabled
			];
		}
	}

	/**
	 * Check if an user opted-in or opted-out from Growth features
	 *
	 * @return int One of GROWTH_FORCE_* constants
	 */
	public static function getGrowthFeaturesOptInOptOutOverride(): int {
		$enableGrowthFeatures = RequestContext::getMain()
			->getRequest()
			->getInt( 'geEnabled', -1 );
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
		if ( $autocreated || !self::isHomepageEnabled() ) {
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
		if (
			$growthOptInOptOutOverride === self::GROWTH_FORCE_OPTIN ||
			$geForceVariant !== null ||
			rand( 0, 99 ) < $enablePercentage
		) {
			$user->setOption( self::HOMEPAGE_PREF_ENABLE, 1 );
			$user->setOption( self::HOMEPAGE_PREF_PT_LINK, 1 );
			// Default option is that the user has seen the tours/notices (so we don't prompt
			// existing users to view them). We set the option to false on new user accounts
			// so they see them once (and then the option gets reset for them).
			$user->setOption( TourHooks::TOUR_COMPLETED_HELP_PANEL, 0 );
			$user->setOption( TourHooks::TOUR_COMPLETED_HOMEPAGE_MENTORSHIP, 0 );
			$user->setOption( TourHooks::TOUR_COMPLETED_HOMEPAGE_WELCOME, 0 );
			$user->setOption( TourHooks::TOUR_COMPLETED_HOMEPAGE_DISCOVERY, 0 );
			$user->setOption( self::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN, 0 );

			if (
				$this->config->get( 'GEHelpPanelNewAccountEnableWithHomepage' ) &&
				HelpPanel::isHelpPanelEnabled()
			) {
				$user->setOption( HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE, 1 );
			}

			// Variant assignment
			if ( $geForceVariant !== null
				 && $this->experimentUserManager->isValidVariant( $geForceVariant )
			) {
				$variant = $geForceVariant;
			} else {
				$variant = $this->experimentUserManager->getRandomVariant();
			}
			$this->experimentUserManager->setVariant( $user, $variant );

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
						$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $user ),
						$this->newcomerTasksUserOptionsLookup->getTopicFilter( $user )
					);
				} );
			}
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
			array_push( $tags,  ...$this->taskTypeHandlerRegistry->getChangeTags() );
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
			array_push( $tags,  ...$this->taskTypeHandlerRegistry->getChangeTags() );
		}
	}

	/**
	 * RecentChange_save hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RecentChange_save
	 *
	 * @param RecentChange $rc
	 */
	public function onRecentChange_save( $rc ) {
		$context = RequestContext::getMain();
		if ( SuggestedEdits::isEnabled( $context ) &&
			 SuggestedEdits::isActivated( $context, $this->userOptionsLookup )
		) {
			$pageId = $rc->getTitle()->getArticleID();
			$tracker = $this->trackerFactory->getTracker( $rc->getPerformerIdentity() );
			$taskType = $tracker->getTaskTypeForPage( $pageId );
			if ( $taskType ) {
				$taskTypeHandler = $this->taskTypeHandlerRegistry->getByTaskType( $taskType );
				$rc->addTags( $taskTypeHandler->getChangeTags() );
			}
		}
	}

	/**
	 * Invalidate configuration cache when needed.
	 * {@inheritDoc}
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		if ( $this->configurationLoader instanceof PageConfigurationLoader ) {
			$this->configurationLoader->onPageSaveComplete( $wikiPage, $user, $summary, $flags,
				$revisionRecord, $editResult );
		}
	}

	/**
	 * Helper method to update the "Profile" menu entry in menus
	 * @param Group $group
	 * @param string $menuEntryName The Profile menu entry - most probably 'auth' or 'profile'
	 * @throws \MWException
	 */
	private static function updateProfileMenuEntry( Group $group, $menuEntryName ) {
		$context = RequestContext::getMain();
		try {
			/** @var IProfileMenuEntry $profileMenuEntry */
			$profileMenuEntry = $group->getEntryByName( $menuEntryName );
			// @phan-suppress-next-line PhanUndeclaredMethod
			$profileMenuEntry->overrideProfileURL(
				self::getPersonalToolsHomepageLinkUrl(
					$context->getTitle()->getNamespace()
				), null, 'homepage' );
		}
		catch ( DomainException $exception ) {
			return;
		}
	}

	/**
	 * Helper method to update the "Home" menu entry in the Mobile Menu
	 * @param Group $group
	 */
	private static function updateHomeMenuEntry( Group $group ) {
		$context = RequestContext::getMain();
		try {
			/** @var HomeMenuEntry $homeMenuEntry */
			$homeMenuEntry = $group->getEntryByName( 'home' );
			// @phan-suppress-next-line PhanUndeclaredMethod
			$homeMenuEntry->overrideText( $context->msg( 'mainpage-nstab' )->text() )
				->overrideCssClass( \MinervaUI::iconClass( 'newspaper', 'before' ) );
		}
		catch ( DomainException $exception ) {
			return;
		}
	}

	/**
	 * @param string $tool
	 * @param Group $group
	 * @throws ConfigException
	 * @throws \MWException
	 */
	public static function onMobileMenu( $tool, Group $group ) {
		if ( in_array( $tool, [ 'personal', 'discovery', 'user' ] ) ) {
			$context = RequestContext::getMain();
			$user = $context->getUser();
			$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
			if ( self::isHomepageEnabled( $user ) &&
				 self::userHasPersonalToolsPrefEnabled( $user, $userOptionsLookup ) ) {
				switch ( $tool ) {
					case 'personal':
						self::updateProfileMenuEntry( $group, 'auth' );
						break;
					case 'user':
						self::updateProfileMenuEntry( $group, 'profile' );
						break;
					case 'discovery':
						self::updateHomeMenuEntry( $group );
						break;
				}
			}
		}
	}

	private static function getZeroContributionsHtml( SpecialPage $sp, $wrapperClasses = '' ) {
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
	 * @param int $userId
	 * @param User $user
	 * @param SpecialContributions $sp
	 */
	public function onSpecialContributionsBeforeMainOutput( $userId, $user, $sp ) {
		if (
			$user->equals( $sp->getUser() ) &&
			$user->getEditCount() === 0 &&
			self::isHomepageEnabled( $user )
		) {
			$out = $sp->getOutput();
			$out->enableOOUI();
			$out->addModuleStyles( 'ext.growthExperiments.Homepage.contribs.styles' );
			$out->addHTML( self::getZeroContributionsHtml( $sp ) );
		}
	}

	/**
	 * @param SpecialPage $sp
	 * @param string $subPage
	 */
	public function onSpecialPageAfterExecute( $sp, $subPage ) {
		// Can't use $sp instanceof \SpecialMobileContributions because that fails if
		// MobileFrontend is not installed
		if ( get_class( $sp ) !== 'SpecialMobileContributions' ) {
			return;
		}
		$user = User::newFromName( $subPage, false );
		if (
			$sp->getUser()->equals( $user ) &&
			$sp->getUser()->getEditCount() === 0 &&
			self::isHomepageEnabled( $sp->getUser() )
		) {
			$out = $sp->getOutput();
			$out->enableOOUI();
			$out->addModuleStyles( 'ext.growthExperiments.Homepage.contribs.styles' );
			$out->addHTML(
				Html::rawElement( 'div', [ 'class' => 'content-unstyled' ],
					self::getZeroContributionsHtml( $sp, 'warningbox' )
				)
			);
		}
	}

	/**
	 * @param User $user
	 * @throws ConfigException
	 * @throws \MWException
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
				$this->experimentUserManager
			);
			return $siteNoticeGenerator->setNotice(
				$skin->getRequest()->getVal( 'source' ),
				$siteNotice,
				$skin,
				$wgMinervaEnableSiteNotice
			);
		}
	}

	/**
	 * @inheritDoc
	 * Update link recommendation data in the search index. Used to deindex pages after they
	 * have been edited (and thus the recommendation does not apply anymore).
	 */
	public function onSearchDataForIndex( &$fields, $handler, $page, $output, $engine ) {
		if ( !$this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
			return;
		}
		$revision = $page->getRevisionRecord();
		$revId = $revision ? $revision->getId() : null;
		if ( !$revId || !$page->getTitle() ) {
			// should not happen
			return;
		} elseif ( !$this->canAccessPrimary ) {
			// A GET request; the hook might be called for diagnostic purposes, e.g. via
			// CirrusSearch\Api\QueryBuildDocument, but not for anything important.
			return;
		}

		// The hook is called after edits, but also on purges or edits to transcluded content,
		// so we mustn't delete recommendations that are still valid. Checking whether there is any
		// recommendation stored for the current revision should do the trick.
		//
		// Both revision IDs might be incorrect due to replication lag but usually it won't
		// matter. If $page is being edited, the cache has already been refreshed and $revId
		// is correct, so we are guaranteed to end up on the delete branch. If this is a purge
		// or other re-rendering-related update, and the page has been edited very recently,
		// and it already has a recommendation (so the real recommendation revision is larger
		// than what we see), we need to avoid erroneously deleting the recommendation - since
		// new recommendations are added to the search index asynchronously, it would result
		// in the DB and search index getting out of sync.
		$linkRecommendation = $this->linkRecommendationStore->getByLinkTarget( $page->getTitle() );
		if ( $linkRecommendation && $linkRecommendation->getRevisionId() < $revId ) {
			$linkRecommendation = $this->linkRecommendationStore->getByLinkTarget( $page->getTitle(),
				IDBAccessObject::READ_LATEST );
		}
		if ( $linkRecommendation && $linkRecommendation->getRevisionId() < $revId ) {
			$fields[WeightedTagsHooks::FIELD_NAME][] = LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX
				. '/' . CirrusIndexField::MULTILIST_DELETE_GROUPING;
			try {
				$this->linkRecommendationHelper->deleteLinkRecommendation(
					$page->getTitle()->toPageIdentity(), false );
			} catch ( DBReadOnlyError $e ) {
				// Leaving a dangling DB row behind doesn't cause any problems so just ignore this.
			}
		}
	}

	/**
	 * ResourceLoader callback used by our custom ResourceLoaderFileModuleWithLessVars class.
	 * @param ResourceLoaderContext $context
	 * @return array An array of LESS variables
	 */
	public static function lessCallback( ResourceLoaderContext $context ) {
		$isMobile = $context->getSkin() === 'minerva';
		return [
			// used in Homepage.SuggestedEdits.less
			'cardContainerWrapperHeight' => $isMobile ? '16em' : '20.5em',
			'cardImageHeight' => $isMobile ? '128px' : '188px',
			'cardWrapperWidth' => $isMobile ? '260px' : '332px',
			'cardWrapperWidthLegacy' => '260px',
			'cardWrapperPadding' => $isMobile ? '0' : '8px',
			'cardWrapperBorderRadius' => $isMobile ? '0' : '2px',
			'cardContentTextPadding' => $isMobile ? '0 16px' : '0 8px',
			'cardExtractHeight' => $isMobile ? '4.5em' : '3em',
			'cardPageviewsTopPadding' => $isMobile ? '10px' : '16px',
			'cardPageviewsIconMarginBottom' => $isMobile ? '0' : '4px',
		];
	}

	/**
	 * ResourceLoader JSON package callback for getting the task types defined on the wiki.
	 * @param ResourceLoaderContext $context
	 * @return array
	 *   - on success: [ task type id => task data, ... ]; see TaskType::toArray for data format.
	 *     Note that the messages in the task data are plaintext and it is the caller's
	 *     responsibility to escape them.
	 *   - on error: [ '_error' => error message in wikitext format ]
	 */
	public static function getTaskTypesJson( ResourceLoaderContext $context ) {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );

		// HACK For the duration of the link recommendation A/B test (T278123), either the 'links'
		// or 'link-recommendation' task type should be hidden for any given user. We can't access
		// user identity here though, so that will have to be done on the client side.

		// Hack - ResourceLoaderContext is not exposed to services initialization
		$configurationValidator = $growthServices->getNewcomerTasksConfigurationValidator();
		$configurationValidator->setMessageLocalizer( $context );

		$configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$taskTypes = $configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			$status = Status::wrap( $taskTypes );
			$status->setMessageLocalizer( $context );
			return [
				'_error' => $status->getWikiText(),
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
	 * @param ResourceLoaderContext $context
	 * @return string[]
	 */
	public static function getDefaultTaskTypesJson( ResourceLoaderContext $context ) {
		// HACK For the duration of the link recommendation A/B test (T278123), the default
		// task types should be [ 'link-recommendation' ] for users who have been opted into
		// link recommendations. We can't access user identity here though, so that will
		// have to be done on the client side.
		return SuggestedEdits::DEFAULT_TASK_TYPES;
	}

	/**
	 * ResourceLoader JSON package callback for getting the topics defined on the wiki.
	 * Some UI elements will be disabled if this returns an empty array.
	 * @param ResourceLoaderContext $context
	 * @return array
	 *   - on success: [ topic id => topic data, ... ]; see Topic::toArray for data format.
	 *     Note that the messages in the task data are plaintext and it is the caller's
	 *     responsibility to escape them.
	 *   - on error: [ '_error' => error message in wikitext format ]
	 */
	public static function getTopicsJson( ResourceLoaderContext $context ) {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );

		// Hack - ResourceLoaderContext is not exposed to services initialization
		$configurationValidator = $growthServices->getNewcomerTasksConfigurationValidator();
		$configurationValidator->setMessageLocalizer( $context );

		$configurationLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$topics = $configurationLoader->loadTopics();
		if ( $topics instanceof StatusValue ) {
			$status = Status::wrap( $topics );
			$status->setMessageLocalizer( $context );
			return [
				'_error' => $status->getWikiText(),
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
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @return array
	 */
	public static function getSuggestedEditsConfigJson(
		ResourceLoaderContext $context, Config $config
	) {
		return [
			'GERestbaseUrl' => Util::getRestbaseUrl( $config ),
			'GENewcomerTasksRemoteArticleOrigin' => $config->get( 'GENewcomerTasksRemoteArticleOrigin' ),
			'GEHomepageSuggestedEditsIntroLinks' => self::getGrowthWikiConfig()
				->get( 'GEHomepageSuggestedEditsIntroLinks' ),
			'GENewcomerTasksTopicFiltersPref' => SuggestedEdits::getTopicFiltersPref( $config ),
			'GELinkRecommendationsEnabled' => $config->get( 'GENewcomerTasksLinkRecommendationsEnabled' )
				&& $config->get( 'GELinkRecommendationsFrontendEnabled' ),
		];
	}

	/**
	 * @param UserIdentity $user
	 * @param UserOptionsLookup $userOptionsLookup
	 * @return bool
	 */
	private static function userHasPersonalToolsPrefEnabled(
		UserIdentity $user,
		UserOptionsLookup $userOptionsLookup
	) {
		return $userOptionsLookup->getBoolOption( $user, self::HOMEPAGE_PREF_PT_LINK );
	}

	/**
	 * Get URL to Special:Homepage with query parameters appended for EventLogging.
	 * @param int $namespace
	 * @return string
	 * @throws \MWException
	 */
	private static function getPersonalToolsHomepageLinkUrl( $namespace ) {
		return SpecialPage::getTitleFor( 'Homepage' )->getLinkURL(
			'source=personaltoolslink&namespace=' . $namespace
		);
	}

	/**
	 * Add GrowthExperiments events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notifications['mentor-changed'] = [
			'category' => 'system',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoMentorChangePresentationModel::class,
			EchoAttributeManager::ATTR_LOCATORS => [
				[
					EchoUserLocator::class . '::locateFromEventExtra',
					[ 'mentee' ]
				],
			],
		];
		$icons['growthexperiments-menteeclaimed'] = [
			'path' => [
				'ltr' => 'GrowthExperiments/images/mentor-ltr.svg',
				'rtl' => 'GrowthExperiments/images/mentor-rtl.svg'
			]
		];
	}

}
