<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserOptionsManager;

/**
 * Service for handling experiment / variant related functions for users.
 */
class ExperimentUserManager {

	/**
	 * @var ServiceOptions
	 */
	private $options;
	/**
	 * @var UserOptionsLookup
	 */
	private $userOptionsLookup;
	/**
	 * @var UserOptionsManager
	 */
	private $userOptionsManager;

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
		$this->options = $options;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @param UserIdentity $user
	 * @return string
	 */
	public function getVariant( UserIdentity $user ) {
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
	 * @param mixed $variant
	 */
	public function setVariant( UserIdentity $user, $variant ) {
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
	public function isUserInVariant( UserIdentity $user, $variant ) : bool {
		return in_array( $this->getVariant( $user ), (array)$variant );
	}
}
