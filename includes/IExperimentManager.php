<?php
namespace GrowthExperiments;

use MediaWiki\User\UserIdentity;

/**
 * An implementation of IExperimentManager should be always capable of
 * returning a variant even if there are no experiments on-going. This is to satisfy
 * the expectation of existing callers while GrowthExperiments is fully migrated
 * to use TestKitchen for experiment management, T375198.
 */
interface IExperimentManager {
	/**
	 * Return the group assigned to a user if there's a current experiment
	 * in course. If not, provides a fallback group name. GE only supports one experiment
	 * at a time.
	 * @param UserIdentity $user
	 * @return string
	 */
	public function getVariant( UserIdentity $user ): string;

	/**
	 * Whether the user is assigned to a given variant in the current
	 * experiment. GE only supports one experiment at a time.
	 * @param UserIdentity $user
	 * @param mixed $variant
	 * @return bool
	 */
	public function isUserInVariant( UserIdentity $user, $variant ): bool;
}
