<?php

namespace GrowthExperiments;

use MediaWiki\Auth\AuthManager;
use Sanitizer;
use User;

class Util {

	/**
	 * Helper method to check if a user can set their email.
	 *
	 * Called from the Help Panel and the Welcome Survey when a user has no email, or has
	 * an email that has not yet been confirmed.
	 *
	 * To check if a user with no email can set a particular email, pass in only the second
	 * argument; to check if a user with an unconfirmed email can set a particular email set the
	 * third argument to false.
	 *
	 * @param User $user
	 * @param null $newEmail
	 * @param bool $checkConfirmedEmail
	 * @return bool
	 */
	public static function canSetEmail( User $user, $newEmail = null, $checkConfirmedEmail = true ) {
		return ( $checkConfirmedEmail ?
				!$user->getEmail() || !$user->isEmailConfirmed() :
				!$user->getEmail() ) &&
			$user->isAllowed( 'viewmyprivateinfo' ) &&
			$user->isAllowed( 'editmyprivateinfo' ) &&
			AuthManager::singleton()->allowsPropertyChange( 'emailaddress' ) &&
			( $newEmail ? Sanitizer::validateEmail( $newEmail ) : true );
	}
}
