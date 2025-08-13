<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * A ConfigurationLoader which returns a pre-configured error when loading task types and
 * warns any accesses to them.
 *
 * Useful for telling the wiki admin that the default configuration is not usable,
 * without breaking unrelated functionality (which an exception would).
 *
 * In use to tell apart callers that are not checking for Suggested Edits being enabled,
 * in which case no task type config should be requested, see T369312
 */
class ErrorForwardingConfigurationLoader extends StaticConfigurationLoader {
	private LoggerInterface $logger;

	public function __construct( StatusValue $statusValue, LoggerInterface $logger ) {
		parent::__construct( $statusValue );
		$this->logger = $logger;
	}

	/** @inheritDoc */
	public function getDisabledTaskTypes(): array {
		$this->logger->warning(
			'Unexpected call to ConfigurationLoader::getDisabledTaskTypes when feature is disabled. ' .
			'Called from: {class}::{function}.',
			[
				'class' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['class'] ?? 'unknown',
				'function' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? 'unknown',
				'exception' => new \RuntimeException,
			]
		);
		return [];
	}

	/**
	 * Override to log unexpected access to disabled Suggested Edits feature
	 */

	/** @inheritDoc */
	public function loadTaskTypes(): array|StatusValue {
		$this->logger->warning(
			'Unexpected call to ConfigurationLoader::loadTaskTypes when feature is disabled. ' .
			'Called from: {class}::{function}.',
			[
				'class' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['class'] ?? 'unknown',
				'function' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? 'unknown',
				'exception' => new \RuntimeException,
			]
		);
		return parent::loadTaskTypes();
	}

	/**
	 * Override to log unexpected access to disabled Suggested Edits feature
	 */
	public function getTaskTypes(): array {
		$this->logger->warning(
			'Unexpected call to ConfigurationLoader::getTaskTypes when feature is disabled. ' .
			'Called from: {class}::{function}.',
			[
				'class' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['class'] ?? 'unknown',
				'function' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? 'unknown',
				'exception' => new \RuntimeException,
			]
		);
		return [];
	}

}
