<?php

namespace GrowthExperiments;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

/**
 * Service for handling experiment / variant related functions for users.
 */
class ExperimentUserManager {

	private LoggerInterface $logger;
	private ServiceOptions $options;
	private UserOptionsLookup $userOptionsLookup;
	private UserOptionsManager $userOptionsManager;
	private UserFactory $userFactory;

	public const CONSTRUCTOR_OPTIONS = [
		'GEHomepageDefaultVariant',
	];

	public function __construct(
		LoggerInterface $logger,
		ServiceOptions $options,
		UserOptionsManager $userOptionsManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->logger = $logger;
		$this->options = $options;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userOptionsManager = $userOptionsManager;
		$this->userFactory = $userFactory;
	}

	public function getVariant( UserIdentity $user ): string {
		if ( !$this->userFactory->newFromUserIdentity( $user )->isNamed() ) {
			$this->logger->debug( __METHOD__ . ' suspicious evaluation of unamed user', [
				'exception' => new \RuntimeException,
				'userName' => $user->getName(),
				'trace' => \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )
			] );
		}
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

	public function isValidVariant( string $variant ): bool {
		return in_array( $variant, VariantHooks::VARIANTS );
	}
}
