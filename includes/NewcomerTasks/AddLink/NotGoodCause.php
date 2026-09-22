<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

/**
 * Reason why a page is not a good candidate for a link recommendation.
 *
 * The string values are the `outcome` label of the refreshLinks_total metric.
 * Do not change them. Dashboards use them.
 */
enum NotGoodCause: string {

	/** The pruning removes all recommended links of the page. */
	case ALL_RECOMMENDATIONS_PRUNED = 'all_recommendations_pruned';

	/** The database has a link recommendation for the current revision. */
	case ALREADY_STORED = 'already_stored';

	/** The database knows that the current revision has no link recommendation. */
	case KNOWN_UNAVAILABLE = 'known_unavailable';

	/** The page has less good links than the task type needs. */
	case GOOD_LINKS_COUNT_TOO_SMALL = 'good_links_count_too_small';

	/** The last edit to the page is too recent. */
	case MINIMUM_TIME_DID_NOT_PASS = 'minimum_time_since_last_edit_did_not_pass';

	/** The page is a disambiguation page. */
	case DISAMBIGUATION_PAGE = 'disambiguation_page';

	/** The page has a category that the task type excludes. */
	case EXCLUDED_CATEGORY = 'excluded_category';

	/** The page has a template that the task type excludes. */
	case EXCLUDED_TEMPLATE = 'excluded_template';

	/** The page has a prior link recommendation submission. */
	case HAS_PRIOR_SUBMISSION = 'has_prior_submission';

	/** No more specific cause applies. */
	case OTHER = 'other';
}
