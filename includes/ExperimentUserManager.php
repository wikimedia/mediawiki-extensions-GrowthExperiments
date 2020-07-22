<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserOptionsManager;
use User;

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
	 * @param User $user
	 * @return string
	 */
	public function getVariant( User $user ) {
		$variant = $this->userOptionsLookup->getOption(
			$user,
			HomepageHooks::HOMEPAGE_PREF_VARIANT
		);
		if ( !in_array( $variant, HomepageHooks::VARIANTS ) ) {
			$variant = $this->options->get( 'GEHomepageDefaultVariant' );
		}
		return $variant;
	}
}
