<?php

declare( strict_types = 1 );

namespace GrowthExperiments\PersonalDashboard;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;

class HomeOnboardingActionLookup {

	/** The user saw Account Setup on Home, or the welcome back dialog. */
	public const INTRO_SEEN_PREF = 'growthexperiments-home-intro-seen';
	/** The user dismissed the "no interests" notice. */
	public const INTERESTS_NOTICE_INTERACTED_PREF = 'growthexperiments-home-interests-notice-interacted';

	/** Show the "no interests" notice. */
	public const ACTION_INTERESTS_NOTICE = 'show-no-interests-notice';
	/** Show the "no interests" notice, and the welcome back dialog on top of it. */
	public const ACTION_WELCOME_BACK = 'show-welcome-back';

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
	) {
	}

	/**
	 * @param UserIdentity $user
	 * @return string|null One of the ACTION_* constants, or null when Home shows no onboarding
	 */
	public function getAction( UserIdentity $user ): ?string {
		if ( $this->newcomerTasksUserOptionsLookup->getValidatedInterests( $user ) !== [] ) {
			return null;
		}
		if ( $this->userOptionsLookup->getBoolOption( $user, self::INTERESTS_NOTICE_INTERACTED_PREF ) ) {
			return null;
		}
		if (
			!$this->userOptionsLookup->getBoolOption( $user, HomepageHooks::HOMEPAGE_PREF_ENABLE ) ||
			$this->userOptionsLookup->getBoolOption( $user, self::INTRO_SEEN_PREF )
		) {
			return self::ACTION_INTERESTS_NOTICE;
		}
		return self::ACTION_WELCOME_BACK;
	}
}
