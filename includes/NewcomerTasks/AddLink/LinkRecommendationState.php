<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

/**
 * State of the stored link recommendation for a given revision.
 */
enum LinkRecommendationState {
	/** A link recommendation is stored for the revision. */
	case AVAILABLE;
	/** The revision is known to have no link recommendation available. */
	case NOT_AVAILABLE;
	/** Nothing is stored about the revision. */
	case UNKNOWN;
}
