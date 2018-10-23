<?php

namespace GrowthExperiments;

use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\MediaWikiServices;
use RequestContext;
use SpecialPage;
use User;

class Hooks {

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
			$preferences[ 'welcomesurvey-responses' ] = [
				'type' => 'api',
			];
		}
	}

	/**
	 * Redirect to the Welcome survey after a new account is created.
	 *
	 * @param string &$welcome_creation_msg
	 * @param string &$injected_html
	 * @throws \MWException
	 */
	public static function onBeforeWelcomeCreation( &$welcome_creation_msg, &$injected_html ) {
		if ( !self::isWelcomeSurveyEnabled() ) {
			return;
		}

		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$newUserSurvey = SpecialPage::getTitleFor( 'WelcomeSurvey' );
		$query = wfArrayToCgi( [
			'returnto' => $request->getVal( 'returnto' ),
			'returntoquery' => $request->getVal( 'returntoquery' ),
		] );
		$context->getOutput()->redirect( $newUserSurvey->getFullUrlForRedirect( $query ) );
		$injected_html = '<!-- redirect to WelcomeSurvey -->';
	}

	private static function isWelcomeSurveyEnabled() {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'WelcomeSurveyEnabled' );
	}

}
