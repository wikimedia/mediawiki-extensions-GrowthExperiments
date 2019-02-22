<?php

namespace GrowthExperiments;

use GrowthExperiments\Specials\SpecialHomepage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\MenuBuilder;
use MinervaUI;
use RequestContext;
use SkinTemplate;
use SpecialPage;
use Title;
use User;

class HomepageHooks {

	const HOMEPAGE_PREF_ENABLE = 'growthexperiments-homepage-enable';
	const HOMEPAGE_PREF_PT_LINK = 'growthexperiments-homepage-pt-link';

	/**
	 * Register Homepage special page.
	 *
	 * @param array &$list
	 * @throws \ConfigException
	 */
	public static function onSpecialPageInitList( &$list ) {
		if ( self::isHomepageEnabled() ) {
			$list[ 'Homepage' ] = SpecialHomepage::class;
		}
	}

	/**
	 * @param User|null $user
	 * @return bool
	 * @throws \ConfigException
	 */
	private static function isHomepageEnabled( User $user = null ) {
		return (
			MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHomepageEnabled' ) &&
			( $user === null || $user->getBoolOption( self::HOMEPAGE_PREF_ENABLE ) )
		);
	}

	/**
	 * Make sure user pages have "User", "talk" and "homepage" tabs.
	 *
	 * @param SkinTemplate &$skin
	 * @param array &$links
	 * @throws \MWException
	 * @throws \ConfigException
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate &$skin, array &$links ) {
		$user = $skin->getUser();
		if ( !self::isHomepageEnabled( $user ) ) {
			return;
		}

		$title = $skin->getTitle();
		$userpage = $user->getUserPage();
		$usertalk = $user->getTalkPage();

		if ( $title->isSpecial( 'Homepage' ) ) {
			unset( $links[ 'namespaces' ][ 'special' ] );
			$links[ 'namespaces' ][ 'homepage' ] = $skin->tabAction(
				$title, 'growthexperiments-homepage-tab', true
			);
			$links[ 'namespaces' ][ 'user' ] = $skin->tabAction(
				$userpage, 'nstab-user', false, '', true
			);
			$links[ 'namespaces' ][ 'talk' ] = $skin->tabAction(
				$usertalk, 'talk', false, '', true
			);
			return;
		}

		if ( $title->equals( $userpage ) ||
			$title->isSubpageOf( $userpage ) ||
			$title->equals( $usertalk ) ||
			$title->isSubpageOf( $usertalk )
		) {
			$links[ 'namespaces' ] = array_merge(
				[ 'homepage' => $skin->tabAction(
					SpecialPage::getTitleFor( 'Homepage' ),
					'growthexperiments-homepage-tab',
					false
				) ],
				$links[ 'namespaces' ]
			);
		}
	}

	/**
	 * Conditionally make the userpage link go to the homepage.
	 *
	 * @param array &$personal_urls
	 * @param Title &$title
	 * @param SkinTemplate $sk
	 * @throws \MWException
	 * @throws \ConfigException
	 */
	public static function onPersonalUrls( &$personal_urls, &$title, $sk ) {
		$user = $sk->getUser();
		if ( !self::isHomepageEnabled( $user ) ) {
			return;
		}

		if ( $user->getBoolOption( self::HOMEPAGE_PREF_PT_LINK ) ) {
			$homepage = SpecialPage::getTitleFor( 'Homepage' );
			$personal_urls[ 'userpage' ][ 'href' ] = $homepage->getLinkURL();
		}
	}

	/**
	 * Conditionally add a link to the homepage in the mobile menu
	 *
	 * @param string $section
	 * @param MenuBuilder &$menu
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public static function onMobileMenu( $section, MenuBuilder &$menu ) {
		$user = RequestContext::getMain()->getUser();
		if ( !self::isHomepageEnabled( $user ) ) {
			return;
		}

		if ( $section === 'personal' ) {
			$homepage = SpecialPage::getTitleFor( 'Homepage' );
			$menu->insertAfter( 'auth', 'homepage' )
				->addComponent(
					wfMessage( 'growthexperiments-homepage-tab' )->text(),
					$homepage->getLinkURL(),
					MinervaUI::iconClass( 'profile-gray', 'before', 'truncated-text primary-action' ),
					[ 'data-event-name' => 'homepage' ]
				);
		}
	}

	/**
	 * Register preferences to control the homepage.
	 *
	 * @param User $user
	 * @param array &$preferences Preferences object
	 * @throws \ConfigException
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
	}

	/**
	 * Enable the homepage for a percentage of new local accounts.
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @throws \ConfigException
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
		}
	}

}
