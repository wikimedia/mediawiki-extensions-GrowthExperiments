<?php

namespace GrowthExperiments;

use Config;
use ConfigException;
use DomainException;
use EchoAttributeManager;
use EchoUserLocator;
use Exception;
use GrowthExperiments\Homepage\SiteNoticeGenerator;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Tracker\Tracker;
use GrowthExperiments\Specials\SpecialClaimMentee;
use GrowthExperiments\Specials\SpecialHomepage;
use GrowthExperiments\Specials\SpecialImpact;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\Menu\Entries\HomeMenuEntry;
use MediaWiki\Minerva\Menu\Entries\IProfileMenuEntry;
use MediaWiki\Minerva\Menu\Group;
use MediaWiki\Minerva\SkinOptions;
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
use Throwable;
use Title;
use User;

class HomepageHooks {

	const HOMEPAGE_PREF_ENABLE = 'growthexperiments-homepage-enable';
	const HOMEPAGE_PREF_PT_LINK = 'growthexperiments-homepage-pt-link';
	/** @var string User options key for storing whether the user has seen the notice. */
	const HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN = 'homepage_mobile_discovery_notice_seen';
	const CONFIRMEMAIL_QUERY_PARAM = 'specialconfirmemail';

	/**
	 * Register Homepage, Impact and ClaimMentee special pages.
	 *
	 * @param array &$list
	 * @throws ConfigException
	 */
	public static function onSpecialPageInitList( &$list ) {
		if ( self::isHomepageEnabled() ) {
			$pageViewInfoEnabled = \ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
			$mwServices = MediaWikiServices::getInstance();
			$list['Homepage'] = function () use ( $pageViewInfoEnabled, $mwServices ) {
				$pageViewsService = $pageViewInfoEnabled ? $mwServices->get( 'PageViewService' ) : null;
				return new SpecialHomepage(
					$mwServices->get( 'GrowthExperimentsEditInfoService' ),
					$mwServices->getDBLoadBalancer()->getLazyConnectionRef( DB_REPLICA ),
					$mwServices->get( 'GrowthExperimentsConfigurationLoader' ),
					$mwServices->get( 'GrowthExperimentsNewcomerTaskTrackerFactory' ),
					$pageViewsService
				);
			};
			if ( $pageViewInfoEnabled ) {
				$list['Impact'] = function () use ( $pageViewInfoEnabled, $mwServices ) {
					return new SpecialImpact(
						$mwServices->getDBLoadBalancer()->getLazyConnectionRef( DB_REPLICA ),
						$mwServices->get( 'PageViewService' )
					);
				};
			}
			$list[ 'ClaimMentee' ] = function () use ( $mwServices ) {
				return new SpecialClaimMentee( $mwServices->getMainConfig() );
			};
		}
	}

	/**
	 * @param User|null $user
	 * @return bool
	 * @throws ConfigException
	 */
	public static function isHomepageEnabled( User $user = null ) {
		return (
			MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHomepageEnabled' ) &&
			( $user === null || $user->getBoolOption( self::HOMEPAGE_PREF_ENABLE ) )
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
			$clickId = $context->getRequest()->getVal( 'geclickid' );
			if ( $clickId && SpecialHomepage::verifyPageviewToken( $clickId, $context ) ) {
				return $clickId;
			}
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
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$context = $out->getContext();
		if ( SuggestedEdits::isEnabled( $context ) ) {
			// Manage the suggesed edit session.
			$out->addModules( 'ext.growthExperiments.SuggestedEditSession' );
		}

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
		$isHomepage = $skin->getTitle()->isSpecial( 'Homepage' );
		if ( $isHomepage ||
			 self::titleIsUserPageOrUserTalk( $skin->getTitle(), $skin->getUser() ) ) {
			/** @var SkinOptions $skinOptions */
			$skinOptions->setMultiple( [
				SkinOptions::TALK_AT_TOP => true,
				SkinOptions::TABS_ON_SPECIALS => true,
				// Another hack. When overflow submenu is true, the various tabs normally shown
				// on editable page will be hidden, which is what we want on Special:Homepage.
				// On User:Foo or User_talk:Foo, however, we want this set to false.
				SkinOptions::TOOLBAR_SUBMENU => $isHomepage
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
	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skin, array &$links ) {
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
				$subjectpage = MediaWikiServices::getInstance()->getNamespaceInfo()->getSubjectPage( $title );
				$talkpage = MediaWikiServices::getInstance()->getNamespaceInfo()->getTalkPage( $title );

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
				$subjectpage, 'nstab-user', $isUserSpace, '', !$isMobile
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
	 * @param Title $title
	 * @param SkinTemplate $sk
	 * @throws \MWException
	 * @throws ConfigException
	 */
	public static function onPersonalUrls( &$personal_urls, Title $title, $sk ) {
		$user = $sk->getUser();
		if ( !self::isHomepageEnabled( $user ) || Util::isMobile( $sk ) ) {
			return;
		}

		if ( self::userHasPersonalToolsPrefEnabled( $user ) ) {
			$personal_urls['userpage']['href'] = self::getPersonalToolsHomepageLinkUrl(
				$title->getNamespace()
			);
			// Make the link blue
			unset( $personal_urls['userpage']['class'] );
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
	public static function onMessageCacheGet( &$lcKey ) {
		$user = RequestContext::getMain()->getUser();
		if (
			$lcKey === 'tooltip-pt-userpage' &&
			self::isHomepageEnabled( $user ) &&
			self::userHasPersonalToolsPrefEnabled( $user )
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
	public static function onGetPreferences( $user, &$preferences ) {
		if ( !self::isHomepageEnabled() ) {
			return;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();

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

		$preferences[ Mentor::MENTOR_PREF ] = [
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

		$preferences[ SuggestedEdits::getTopicFiltersPref( $config ) ] = [
			'type' => 'api'
		];

		$preferences[ SuggestedEdits::TASKTYPES_PREF ] = [
			'type' => 'api'
		];

		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( $config->get( 'GEHomepageSuggestedEditsRequiresOptIn' ) ) {
			$preferences[ SuggestedEdits::ENABLED_PREF ] = [
				'type' => 'api'
			];
		}

		if ( $config->get( 'GEHomepageSuggestedEditsTopicsRequiresOptIn' ) ) {
			$preferences[ SuggestedEdits::TOPICS_ENABLED_PREF ] = [
				'type' => 'api'
			];
		}

		$preferences[ SuggestedEdits::GUIDANCE_BLUE_DOT_PREF ] = [
			'type' => 'api'
		];
	}

	/**
	 * Register defaults for homepage-related preferences.
	 *
	 * @param array &$wgDefaultUserOptions Reference to default options array
	 */
	public static function onUserGetDefaultOptions( &$wgDefaultUserOptions ) {
		$wgDefaultUserOptions += [
			// Set discovery notice seen flag to true; it will be changed for new users in the
			// LocalUserCreated hook.
			self::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN => true,
		];
	}

	/**
	 * Enable the homepage for a percentage of new local accounts.
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @throws ConfigException
	 */
	public static function onLocalUserCreated( User $user, $autocreated ) {
		if ( !self::isHomepageEnabled() ) {
			return;
		}

		// Enable the homepage for a percentage of non-autocreated users.
		$config = RequestContext::getMain()->getConfig();
		$enablePercentage = $config->get( 'GEHomepageNewAccountEnablePercentage' );
		if ( $user->isLoggedIn() && !$autocreated && rand( 0, 99 ) < $enablePercentage ) {
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
			try {
				$user->setOption(
					Mentor::MENTOR_PREF,
					Mentor::newFromMentee( $user, true )
						->getMentorUser()
						->getId()
				);
			} catch ( Exception $exception ) {
				Util::logError( $exception, [
					'user' => $user->getId(),
					'impact' => 'Failed to assign mentor for user',
					'origin' => __METHOD__,
				] );
			} catch ( Throwable $throwable ) {
				Util::logError( $throwable, [
					'user' => $user->getId(),
					'impact' => 'Failed to assign mentor for user',
					'origin' => __METHOD__,
				] );
			}

			if (
				$config->get( 'GEHelpPanelNewAccountEnableWithHomepage' ) &&
				HelpPanel::isHelpPanelEnabled()
			) {
				$user->setOption( HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE, 1 );
			}

			if ( rand( 0, 99 ) < $config->get( 'GEHomepageSuggestedEditsNewAccountInitiatedPercentage' ) ) {
				$user->setOption( SuggestedEdits::ACTIVATED_PREF, 1 );
				// Record that the user has suggested edits pre-activated. This preference isn't
				// used for anything in software, it's only used in data analysis to distinguish
				// users who manually activated suggested edits from those for whom it was pre-activated.
				$user->setOption( SuggestedEdits::PREACTIVATED_PREF, 1 );
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
	public static function onListDefinedTags( &$tags ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( self::isHomepageEnabled() ) {
			$tags[] = Help::HELP_MODULE_QUESTION_TAG;
			$tags[] = Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
		}
		if ( SuggestedEdits::isEnabledForAnyone( $config ) ) {
			$tags[] = SuggestedEdits::SUGGESTED_EDIT_TAG;
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
	public static function onChangeTagsListActive( &$tags ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( self::isHomepageEnabled() ) {
			// Help::HELP_MODULE_QUESTION_TAG is no longer active (T232548)
			$tags[] = Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
		}
		if ( SuggestedEdits::isEnabledForAnyone( $config ) ) {
			$tags[] = SuggestedEdits::SUGGESTED_EDIT_TAG;
		}
	}

	/**
	 * RecentChange_save hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RecentChange_save
	 *
	 * @param RecentChange &$rc
	 */
	public static function onRecentChangeSave( RecentChange &$rc ) {
		$context = RequestContext::getMain();
		if ( SuggestedEdits::isEnabled( $context ) &&
			 SuggestedEdits::isActivated( $context )
		) {
			/** @var Tracker $tracker */
			$tracker = MediaWikiServices::getInstance()->get(
				'GrowthExperimentsNewcomerTaskTrackerFactory'
			)->getTracker( $rc->getPerformer() );
			if ( $tracker && in_array( $rc->getTitle()->getArticleID(), $tracker->getTrackedPageIds() ) ) {
				$rc->addTags( SuggestedEdits::SUGGESTED_EDIT_TAG );
			}
		}
	}

	/**
	 * Handler for UserSaveOptions hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserSaveOptions
	 * @param User $user User whose options are being saved
	 * @param array &$options Options can be modified
	 * @param array $originalOptions Original options being replaced
	 * @return bool true in all cases
	 */
	public static function onUserSaveOptions( $user, &$options, $originalOptions ) {
		$homepagePrefEnabled = $options[self::HOMEPAGE_PREF_ENABLE] ?? false;
		$homepageAlreadyEnabled = $originalOptions[self::HOMEPAGE_PREF_ENABLE] ?? false;
		$userHasMentor = MediaWikiServices::getInstance()
			->getUserOptionsLookup()
			->getIntOption( $user, Mentor::MENTOR_PREF );
		if ( $homepagePrefEnabled && !$homepageAlreadyEnabled && !$userHasMentor ) {
			try {
				$mentor = Mentor::newFromMentee( $user, true );
				$options[Mentor::MENTOR_PREF] = $mentor->getMentorUser()->getId();
			} catch ( Exception $exception ) {
				Util::logError( $exception, [
					'user' => $user->getId(),
					'impact' => 'Failed to assign mentor from Special:Preferences',
					'origin' => __METHOD__,
				] );
			} catch ( Throwable $throwable ) {
				Util::logError( $throwable, [
					'user' => $user->getId(),
					'impact' => 'Failed to assign mentor from Special:Preferences',
					'origin' => __METHOD__,
				] );
			}
		}

		return true;
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
			if ( self::isHomepageEnabled( $user ) &&
				 self::userHasPersonalToolsPrefEnabled( $user ) ) {
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
			->getFullUrl( [ 'source' => 'specialcontributions' ] );
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
	public static function onSpecialContributionsBeforeMainOutput(
		$userId, User $user, SpecialContributions $sp
	) {
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
	public static function onSpecialPageAfterExecute( SpecialPage $sp, $subPage ) {
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
	 * @throws ConfigException
	 * @throws \MWException
	 */
	public static function onConfirmEmailComplete() {
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
	public static function onSiteNoticeAfter( &$siteNotice, Skin $skin ) {
		global $wgMinervaEnableSiteNotice;
		if ( self::isHomepageEnabled( $skin->getUser() ) ) {
			return SiteNoticeGenerator::setNotice(
				$skin->getRequest()->getVal( 'source' ),
				$siteNotice,
				$skin,
				$wgMinervaEnableSiteNotice
			);
		}
	}

	/**
	 * ResourceLoader callback used by our custom ResourceLoaderFileModuleWithLessVars class.
	 * @param ResourceLoaderContext $context
	 * @return array An array of LESS variables
	 */
	public static function lessCallback( ResourceLoaderContext $context ) {
		return [
			// used in Homepage.SuggestedEdits.less, keep the comment there in sync
			'cardContainerWrapperHeight' => ( $context->getSkin() === 'minerva' ) ? '16em' : '18em',
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
		/** @var ConfigurationLoader $configurationLoader */
		$configurationLoader = MediaWikiServices::getInstance()
			->get( 'GrowthExperimentsConfigurationLoader' );
		$configurationLoader->setMessageLocalizer( $context );
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
				$taskTypesData[$taskType->getId()] = $taskType->toArray( $context );
			}
			return $taskTypesData;
		}
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
		/** @var ConfigurationLoader $configurationLoader */
		$configurationLoader = MediaWikiServices::getInstance()
			->get( 'GrowthExperimentsConfigurationLoader' );
		$configurationLoader->setMessageLocalizer( $context );
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
				$topicsData[$topic->getId()] = $topic->toArray( $context );
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
			'GEHomepageSuggestedEditsIntroLinks' => $config->get( 'GEHomepageSuggestedEditsIntroLinks' ),
			'GENewcomerTasksTopicFiltersPref' => SuggestedEdits::getTopicFiltersPref( $config ),
		];
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	private static function userHasPersonalToolsPrefEnabled( User $user ) {
		return $user->getBoolOption( self::HOMEPAGE_PREF_PT_LINK );
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
