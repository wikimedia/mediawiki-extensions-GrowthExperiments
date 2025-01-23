<?php

namespace GrowthExperiments;

use MediaWiki\Cache\Hook\MessageCacheFetchOverridesHook;
use MediaWiki\Config\ConfigException;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;

/**
 * Hook handler class that contains hooks which are riskier than the average (called often,
 * or early in the request lifecycle, or both), and so we want to separate them out from
 * hook handlers with lots of context dependencies (e.g. HomepageHooks), so that there's
 * less chance of causing dependency loops.
 */
class EarlyLifeCycleHooks implements MessageCacheFetchOverridesHook {

	private UserOptionsLookup $userOptionsLookup;

	public function __construct( UserOptionsLookup $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Change the tooltip of the userpage link when it point to Special:Homepage
	 *
	 * @param callable[] &$keys message keys to convert
	 * @throws ConfigException
	 */
	public function onMessageCacheFetchOverrides( array &$keys ): void {
		$keys['tooltip-pt-userpage'] = function ( string $key ): string {
			$user = RequestContext::getMain()->getUser();

			if ( $user->isSafeToLoad()
				&& HomepageHooks::isHomepageEnabled( $user )
				&& $this->userHasPersonalToolsPrefEnabled( $user )
			) {
				return 'tooltip-pt-homepage';
			}

			return $key;
		};
	}

	private function userHasPersonalToolsPrefEnabled( User $user ): bool {
		return $user->isNamed()
			&& $this->userOptionsLookup->getBoolOption( $user, HomepageHooks::HOMEPAGE_PREF_PT_LINK );
	}

}
