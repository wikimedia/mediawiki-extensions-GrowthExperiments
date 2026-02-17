<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;

class StaticExperimentManager implements IExperimentManager {
	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
	];

	public function __construct( private readonly ServiceOptions $options ) {
	}

	/** @inheritDoc */
	public function getVariant( ?UserIdentity $user ): string {
		return $this->options->get( 'GEHomepageDefaultVariant' );
	}

	/** @inheritDoc */
	public function isUserInVariant( UserIdentity $user, $variant ): bool {
		return $this->getVariant( $user ) === $variant;
	}

	public function getValidVariants(): array {
		return [
			// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
			$this->getVariant( null ),
		];
	}
}
