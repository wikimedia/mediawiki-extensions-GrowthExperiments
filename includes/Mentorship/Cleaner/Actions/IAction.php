<?php

namespace GrowthExperiments\Mentorship\Cleaner\Actions;

use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserIdentity;
use StatusValue;

interface IAction {

	/**
	 * Is the action enabled?
	 */
	public function isEnabled(): bool;

	/**
	 * Check if the action should be performed for a given mentor
	 *
	 * Do NOT call if isEnabled() returns false.
	 */
	public function check( UserIdentity $user ): bool;

	/**
	 * Perform the action for the provided mentor
	 *
	 * Do NOT call if isEnabled() returns false.
	 *
	 * @note Does NOT check if the action should be performed. Caller is responsible for calling
	 * check() to verify that.
	 */
	public function perform( UserIdentity $user, MessageLocalizer $messageLocalizer ): StatusValue;
}
