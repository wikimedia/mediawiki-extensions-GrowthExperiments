<?php

namespace GrowthExperiments;

use MediaWiki\User\UserIdentity;

abstract class AbstractExperimentManager {

	abstract public function getVariant( UserIdentity $user ): string;

	/** Only used for variant forced override via params */
	abstract public function isValidVariant( string $variant ): bool;

	/** Only used for variant forced override via params */
	abstract public function setVariant( UserIdentity $user, string $variant ): void;

	/**
	 * @param UserIdentity $user
	 * @param string|string[] $variant
	 * @return bool
	 */
	public function isUserInVariant( UserIdentity $user, $variant ): bool {
		return in_array( $this->getVariant( $user ), (array)$variant );
	}
}
