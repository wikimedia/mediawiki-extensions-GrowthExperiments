<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Hooks\MentorHooks;
use MediaWiki\User\UserIdentity;

/**
 * Create autocomment-formatted summary for StructuredMentorWriter
 *
 * @see MentorHooks::onFormatAutocomments()
 * @see https://www.mediawiki.org/wiki/Autocomment
 */
class MentorshipSummaryCreator {

	private static function createSummary(
		string $messagePrefix,
		UserIdentity $performer,
		UserIdentity $mentor,
		string $reason
	): string {
		$messageKey = $messagePrefix;
		if ( !$performer->equals( $mentor ) ) {
			$messageKey .= '-admin';
		} else {
			$messageKey .= '-self';
		}
		if ( $reason === '' ) {
			$messageKey .= '-no-reason';
		} else {
			$messageKey .= '-with-reason';
		}

		return sprintf(
			'/* %s:%s|%s */',
			$messageKey,
			$mentor->getName(),
			$reason
		);
	}

	public static function createAddSummary(
		UserIdentity $performer,
		UserIdentity $mentor,
		string $reason = ''
	): string {
		return self::createSummary(
			'growthexperiments-manage-mentors-summary-add',
			$performer,
			$mentor,
			$reason
		);
	}

	public static function createChangeSummary(
		UserIdentity $performer,
		UserIdentity $mentor,
		string $reason = ''
	): string {
		return self::createSummary(
			'growthexperiments-manage-mentors-summary-change',
			$performer,
			$mentor,
			$reason
		);
	}

	public static function createRemoveSummary(
		UserIdentity $performer,
		UserIdentity $mentor,
		string $reason = ''
	): string {
		return self::createSummary(
			'growthexperiments-manage-mentors-summary-remove',
			$performer,
			$mentor,
			$reason
		);
	}
}
