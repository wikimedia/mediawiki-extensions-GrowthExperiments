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

	/** @var string|null One of 'mobile' or 'desktop' */
	private ?string $platform;

	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
		'GEHomepageNewAccountVariantsByPlatform',
	];

	/**
	 * @param ServiceOptions $options
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param string|null $platform
	 */
	public function __construct(
		ServiceOptions $options,
		UserOptionsManager $userOptionsManager,
		UserOptionsLookup $userOptionsLookup,
		?string $platform = null
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userOptionsManager = $userOptionsManager;
		$this->platform = $platform;
	}

	/**
	 * Specify if the experiment manager is in a desktop/mobile platform context.
	 *
	 * @param string $platform One of "mobile" or "desktop"
	 */
	public function setPlatform( string $platform ): void {
		$this->platform = $platform;
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

	/**
	 * Get a random variant according to the distribution defined in $wgGEHomepageNewAccountVariantsByPlatform.
	 *
	 * @return string
	 */
	public function getRandomVariant(): string {
		$variantProbabilities = $this->options->get( 'GEHomepageNewAccountVariantsByPlatform' );
		$random = rand( 0, 99 );

		$variant = $this->options->get( 'GEHomepageDefaultVariant' );
		foreach ( $variantProbabilities as $candidateVariant => $percentageForVariant ) {
			if ( !$this->isValidVariant( $candidateVariant ) ) {
				continue;
			}
			if ( $random < $percentageForVariant[$this->platform] ) {
				$variant = $candidateVariant;
				break;
			}
			$random -= $percentageForVariant[$this->platform];
		}
		return $variant;
	}
}
