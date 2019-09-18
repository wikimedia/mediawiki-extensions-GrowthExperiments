<?php

namespace GrowthExperiments;

use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\MediaWikiServices;
use RequestContext;
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

	private static function isWelcomeSurveyEnabled() {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'WelcomeSurveyEnabled' );
	}

}
