<?php

namespace GrowthExperiments;

use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RequestContext;
use Skin;
use User;

class WelcomeSurveyHooks {

	/**
	 * Register WelcomeSurvey special page.
	 *
	 * @param array &$list
	 */
	public static function onSpecialPageInitList( &$list ) {
		if ( self::isWelcomeSurveyEnabled() ) {
			$list[ 'WelcomeSurvey' ] = SpecialWelcomeSurvey::class;
		}
	}

	/**
	 * Register preference to save the Welcome survey responses.
	 *
	 * @param User $user
	 * @param array &$preferences Preferences object
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		if ( self::isWelcomeSurveyEnabled() ) {
			$preferences['welcomesurvey-responses'] = [
				'type' => 'api',
			];
		}
	}

	/**
	 * Redirect to the Welcome survey after a new account is created.
	 *
	 * @param string &$welcome_creation_msg
	 * @param string &$injected_html
	 */
	public static function onBeforeWelcomeCreation( &$welcome_creation_msg, &$injected_html ) {
		if ( !self::isWelcomeSurveyEnabled() ) {
			return;
		}

		$context = RequestContext::getMain();
		$welcomeSurvey = new WelcomeSurvey( $context );
		$group  = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );
		$url = $welcomeSurvey->getRedirectUrl( $group );
		if ( $url ) {
			$context->getOutput()->redirect( $url );
		}
	}

	/**
	 *
	 * Add module for WelcomeSurveyPopup
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		if ( !self::isWelcomeSurveyEnabled() ) {
			return;
		}

		if ( $out->getTitle()->isSpecial( 'CreateAccount' ) ) {
			$out->addModules( 'ext.growthExperiments.detectjs' );
			return;
		}

		if ( $out->getRequest()->getBool( 'showwelcomesurvey' ) && $out->getUser()->isLoggedIn() ) {
			$welcomeSurvey = new WelcomeSurvey( $out->getContext() );
			$group = $welcomeSurvey->getGroup();
			$out->addJsConfigVars( [
				'wgWelcomeSurveyPrivacyPolicyUrl' => $out->getConfig()->get( 'WelcomeSurveyPrivacyPolicyUrl' ),
				'wgWelcomeSurveyQuestions' => $welcomeSurvey->getQuestions( $group, false ),
				'wgWelcomeSurveyExperimentalGroup' => $group,
			] );
			$out->addModules( 'ext.growthExperiments.welcomesurvey.popup' );
		}
	}

	private static function isWelcomeSurveyEnabled() {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'WelcomeSurveyEnabled' );
	}

}
