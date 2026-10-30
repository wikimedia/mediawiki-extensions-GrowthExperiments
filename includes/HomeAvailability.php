<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

/**
 * How a wiki gives Home to its users.
 *
 * @see FeatureManager::getHomeAvailability()
 */
enum HomeAvailability {
	case Unavailable;
	case Wikiwide;
}
