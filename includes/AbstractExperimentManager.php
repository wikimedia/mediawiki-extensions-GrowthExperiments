<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;

abstract class AbstractExperimentManager {

	protected ServiceOptions $options;
	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
	];

	public function __construct(
		ServiceOptions $options,
	) {
		$options->assertRequiredOptions( static::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	abstract public function getVariant( UserIdentity $user ): string;

	/**
	 * @param UserIdentity $user
	 * @param string|string[] $variant
	 * @return bool
	 */
	public function isUserInVariant( UserIdentity $user, $variant ): bool {
		return in_array( $this->getVariant( $user ), (array)$variant );
	}
}
