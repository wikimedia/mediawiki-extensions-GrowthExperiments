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
class ExperimentUserManager extends AbstractExperimentManager {

	private LoggerInterface $logger;
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
		parent::__construct( $options );
		$this->logger = $logger;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userOptionsManager = $userOptionsManager;
		$this->userFactory = $userFactory;
	}

	public function getVariant( UserIdentity $user ): string {
		if ( !$this->userFactory->newFromUserIdentity( $user )->isNamed() ) {
			$this->logger->debug( __METHOD__ . ' suspicious evaluation of unamed user', [
				'exception' => new \RuntimeException,
				'userName' => $user->getName(),
				'trace' => \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ),
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
}
