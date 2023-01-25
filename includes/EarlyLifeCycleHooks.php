<?php

namespace GrowthExperiments;

use ConfigException;
use MediaWiki\Cache\Hook\MessageCache__getHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use RequestContext;

/**
 * Hook handler class that contains hooks which are riskier than the average (called often,
 * or early in the request lifecycle, or both), and so we want to separate them out from
 * hook handlers with lots of context dependencies (e.g. HomepageHooks), so that there's
 * less chance of causing dependency loops.
 */
class EarlyLifeCycleHooks implements MessageCache__getHook {

	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( UserOptionsLookup $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Change the tooltip of the userpage link when it point to Special:Homepage
	 *
	 * @param string &$lcKey message key to check and possibly convert
	 * @throws ConfigException
	 */
	public function onMessageCache__get( &$lcKey ) {
		// Optimisation: There are 1000s of messages, limit cost for each one (T302623)
		if ( $lcKey === 'tooltip-pt-userpage' ) {
			$user = RequestContext::getMain()->getUser();
			if ( $user->isSafeToLoad()
				&& HomepageHooks::isHomepageEnabled( $user )
				&& $this->userHasPersonalToolsPrefEnabled( $user )
			) {
				$lcKey = 'tooltip-pt-homepage';
			}
		}
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	private function userHasPersonalToolsPrefEnabled( UserIdentity $user ): bool {
		return $user->isRegistered()
			&& $this->userOptionsLookup->getBoolOption( $user, HomepageHooks::HOMEPAGE_PREF_PT_LINK );
	}

}
