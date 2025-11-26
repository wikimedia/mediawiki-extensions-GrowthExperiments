<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace GrowthExperiments\Mentorship;

use MediaWiki\Exception\ErrorPageError;

/**
 * Throw an error when user is not mentored.
 *
 * @newable
 * @see T386567
 * @ingroup Exception
 */
class UserNotMentoredException extends ErrorPageError {

	public function __construct() {
		parent::__construct( 'growthexperiments-exception-not-mentored',
			'growthexperiments-exception-no-mentored-text' );
	}
}
