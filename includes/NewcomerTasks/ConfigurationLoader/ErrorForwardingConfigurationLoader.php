<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use MessageLocalizer;
use StatusValue;

/**
 * A ConfigurationLoader which returns a pre-configured error.
 *
 * Useful for telling the wiki admin that the default configuration is not usable,
 * without breaking unrelated functionality (which an exception would).
 */
class ErrorForwardingConfigurationLoader implements ConfigurationLoader {

	/** @var StatusValue The error to forward. */
	private $statusValue;

	/**
	 * @param StatusValue $statusValue The error to forward.
	 */
	public function __construct( StatusValue $statusValue ) {
		$this->statusValue = $statusValue;
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		return $this->statusValue;
	}

	/** @inheritDoc */
	public function loadTemplateBlacklist() {
		return [];
	}

	/** @inheritDoc */
	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ): void {
	}

}
