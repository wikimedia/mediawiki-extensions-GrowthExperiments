<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;

/**
 * Service for handling experiment / variant related functions for users.
 */
class ExperimentUserManager {

	private ServiceOptions $options;
	private UserOptionsLookup $userOptionsLookup;
	private UserOptionsManager $userOptionsManager;

	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
	];

	/**
	 * @param ServiceOptions $options
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		ServiceOptions $options,
		UserOptionsManager $userOptionsManager,
		UserOptionsLookup $userOptionsLookup
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @param UserIdentity $user
	 * @return string
	 */
	public function getVariant( UserIdentity $user ): string {
		$variant = $this->userOptionsLookup->getOption(
			$user,
			VariantHooks::USER_PREFERENCE
		);
		if ( !in_array( $variant, VariantHooks::VARIANTS ) ) {
			$variant = $this->options->get( 'GEHomepageDefaultVariant' );
		}
		return $variant;
	}

	/**
	 * Set (but does not save) the variant for a user.
	 *
	 * @param UserIdentity $user
	 * @param string $variant
	 */
	public function setVariant( UserIdentity $user, string $variant ): void {
		$this->userOptionsManager->setOption(
			$user,
			VariantHooks::USER_PREFERENCE,
			$variant
		);
	}

	/**
	 * @param UserIdentity $user
	 * @param string|string[] $variant
	 * @return bool
	 */
	public function isUserInVariant( UserIdentity $user, $variant ): bool {
		return in_array( $this->getVariant( $user ), (array)$variant );
	}

	/**
	 * @param string $variant
	 * @return bool
	 */
	public function isValidVariant( string $variant ): bool {
		return in_array( $variant, VariantHooks::VARIANTS );
	}
}
