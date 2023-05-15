<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use StatusValue;

/**
 * A ConfigurationLoader which returns a pre-configured error.
 *
 * Useful for telling the wiki admin that the default configuration is not usable,
 * without breaking unrelated functionality (which an exception would).
 */
class ErrorForwardingConfigurationLoader extends StaticConfigurationLoader {

	/**
	 * @param StatusValue $statusValue The error to forward.
	 */
	public function __construct( StatusValue $statusValue ) {
		parent::__construct( $statusValue, [] );
	}

	/** @inheritDoc */
	public function getDisabledTaskTypes(): array {
		return [];
	}

}
