<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

/**
 * Represents a single ORES topic (as opposed to OresBasedTopic which is a combination of ORES
 * topics). This is a special topic type that's not present in the topic list returned by
 * ConfigurationLoader and as such it cannot be cached or passed to the client side and should
 * be avoided in user-facing code. i18n-related methods should not be expected to provide anything
 * meaningful.
 */
class RawOresTopic extends OresBasedTopic {

	/**
	 * @param string $id
	 * @param string $oresTopic
	 */
	public function __construct( string $id, string $oresTopic ) {
		parent::__construct( $id, null, [ $oresTopic ] );
	}

}
