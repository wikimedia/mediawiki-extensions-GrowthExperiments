<?php

namespace GrowthExperiments;

use ConfigException;
use DomainException;
use Exception;
use Html;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\Specials\SpecialHomepage;
use GrowthExperiments\Specials\SpecialImpact;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\Menu\AuthMenuEntry;
use MediaWiki\Minerva\Menu\Group;
use MediaWiki\Minerva\Menu\HomeMenuEntry;
use MediaWiki\Minerva\SkinOptions;
use OutputPage;
use OOUI\IconWidget;
use RequestContext;
use Skin;
use SkinTemplate;
use SpecialPage;
use Title;
use User;

class HomepageHooks {

	const HOMEPAGE_PREF_ENABLE = 'growthexperiments-homepage-enable';
	const HOMEPAGE_PREF_PT_LINK = 'growthexperiments-homepage-pt-link';
	const CONFIRMEMAIL_QUERY_PARAM = 'specialconfirmemail';

	/**
	 * Register Homepage and Impact special pages.
	 *
	 * @param array &$list
	 * @throws ConfigException
	 */
	public static function onSpecialPageInitList( &$list ) {
		if ( self::isHomepageEnabled() ) {
			$list[ 'Homepage' ] = SpecialHomepage::class;
			if ( \ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' ) ) {
				$list['Impact'] = SpecialImpact::class;
			}
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
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @throws ConfigException
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
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
			/** @var SkinOptions $skinOptions */
			$skinOptions->setMultiple( [
				// TODO: OPTION_AMC needed for correct styles to apply.
				SkinOptions::OPTION_AMC => true,
				SkinOptions::OPTIONS_TALK_AT_TOP => true,
				SkinOptions::OPTION_TABS_ON_SPECIALS => true
			] );
		}
	}

	/**
	 * Make sure user pages have "User", "talk" and "homepage" tabs.
	 *
	 * @param SkinTemplate &$skin
	 * @param array &$links
	 * @throws \MWException
	 * @throws ConfigException
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate &$skin, array &$links ) {
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

			$homepageUrlQuery = $isHomepage ? '' : wfArrayToCgi( [
				'source' => $isUserSpace ? 'userpagetab' : 'usertalkpagetab',
				'namespace' => $title->getNamespace(),
			] );
			$links['namespaces']['homepage'] = $skin->tabAction(
				$homepageTitle, 'growthexperiments-homepage-tab', $isHomepage, $homepageUrlQuery
			);

			$links['namespaces']['user'] = $skin->tabAction(
				$userpage, 'nstab-user', $isUserSpace, '', !$isMobile
			);

			$links['namespaces']['user_talk'] = $skin->tabAction(
				$usertalk, 'talk', $isUserTalkSpace, '', !$isMobile
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
	public static function onPersonalUrls( &$personal_urls, &$title, $sk ) {
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

		$preferences[ Mentor::MENTOR_PREF ] = [
			'type' => 'api',
		];

		$preferences[ Tutorial::TUTORIAL_PREF ] = [
			'type' => 'api',
		];

		$preferences[ Mentorship::QUESTION_PREF ] = [
			'type' => 'api',
		];

		$preferences[ Help::QUESTION_PREF ] = [
			'type' => 'api',
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
			$user->saveSettings();
			try {
				$assignedMentor = Mentor::newFromMentee( $user, true );
				Mentor::saveMentor( $user, $assignedMentor->getMentorUser() );
			}
			catch ( Exception $exception ) {
				LoggerFactory::getInstance( 'GrowthExperiments' )
					->error( __METHOD__ . ' Failed to assign mentor for user.', [
						'user' => $user->getId()
					] );
			}
		}
	}

	/**
	 * ListDefinedTags and ChangeTagsListActive hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 *
	 * @param array &$tags The list of tags.
	 * @throws ConfigException
	 */
	public static function onListDefinedTags( &$tags ) {
		if ( self::isHomepageEnabled() ) {
			$tags[] = Help::HELP_MODULE_QUESTION_TAG;
			$tags[] = Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
		}
	}

	/**
	 * Handler for UserSaveOptions hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserSaveOptions
	 * @param User $user User whose options are being saved
	 * @param array &$options Options can be modified
	 * @return bool true in all cases
	 */
	public static function onUserSaveOptions( $user, &$options ) {
		$oldUser = User::newFromId( $user->getId() );
		$homepagePrefEnabled = $options[self::HOMEPAGE_PREF_ENABLE] ?? false;
		$homepageAlreadyEnabled = $oldUser->getOption( self::HOMEPAGE_PREF_ENABLE );
		$userHasMentor = $user->getIntOption( Mentor::MENTOR_PREF );
		if ( $homepagePrefEnabled && !$homepageAlreadyEnabled && !$userHasMentor ) {
			try {
				$mentor = Mentor::newFromMentee( $user, true );
				$options[Mentor::MENTOR_PREF] = $mentor->getMentorUser()->getId();
			}
			catch ( Exception $exception ) {
				LoggerFactory::getInstance( 'GrowthExperiments' )
					->error( 'Failed to assign mentor from Special:Preferences' );
			}
		}

		return true;
	}

	/**
	 * @param string $tool
	 * @param Group &$group
	 * @throws ConfigException
	 * @throws \MWException
	 */
	public static function onMobileMenu( $tool, &$group ) {
		if ( in_array( $tool, [ 'personal', 'discovery' ] ) ) {
			$context = RequestContext::getMain();
			$user = $context->getUser();
			if ( self::isHomepageEnabled( $user ) &&
				 self::userHasPersonalToolsPrefEnabled( $user ) ) {
				if ( $tool === 'personal' ) {
					try {
						/** @var AuthMenuEntry $authMenuEntry */
						$authMenuEntry = $group->getEntryByName( 'auth' );
						// @phan-suppress-next-line PhanUndeclaredMethod
						$authMenuEntry->overrideProfileURL(
							self::getPersonalToolsHomepageLinkUrl(
								$context->getTitle()->getNamespace()
							), null, 'homepage' );
					}
					catch ( DomainException $exception ) {
						return;
					}
				}
				if ( $tool === 'discovery' ) {
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
			}
		}
	}

	/**
	 * @param User $user
	 * @throws ConfigException
	 * @throws \MWException
	 */
	public static function onConfirmEmailComplete( User $user ) {
		if ( self::isHomepageEnabled( $user ) ) {
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
	 * @throws ConfigException
	 */
	public static function onSiteNoticeAfter( &$siteNotice, Skin $skin ) {
		global $wgMinervaEnableSiteNotice;
		$output = $skin->getOutput();
		if ( self::isHomepageEnabled( $skin->getUser() ) &&
			 $output->getTitle()->isSpecial( 'Homepage' ) &&
			 $skin->getRequest()->getVal( 'source' ) === self::CONFIRMEMAIL_QUERY_PARAM ) {
			$output->addModules( 'ext.growthExperiments.Homepage.ConfirmEmail' );
			$output->addModuleStyles( 'ext.growthExperiments.Homepage.ConfirmEmail.styles' );
			$baseCssClassName = 'mw-ge-homepage-confirmemail-nojs';
			$cssClasses = [
				$baseCssClassName,
				Util::isMobile( $skin ) ? $baseCssClassName . '-mobile' : $baseCssClassName . '-desktop'
			];
			$siteNotice = Html::rawElement( 'div', [ 'class' => $cssClasses ],
				new IconWidget( [ 'icon' => 'check', 'flags' => 'progressive' ] ) . ' ' .
				Html::element( 'span', [ 'class' => 'mw-ge-homepage-confirmemail-nojs-message' ],
					$output->msg( 'confirmemail_loggedin' )->text() )
			);
			$wgMinervaEnableSiteNotice = true;
		}
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

}
