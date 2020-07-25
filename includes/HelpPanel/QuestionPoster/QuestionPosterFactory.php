<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use IContextSource;
use UserNotLoggedIn;
use Wikimedia\Assert\Assert;

/**
 * Factory class for selecting the right question poster based on where the questions should go
 * and where they are sent from.
 */
class QuestionPosterFactory {

	/** The question is sent from the Mentorship module of the home page. */
	public const SOURCE_MENTORSHIP_MODULE = 'mentorship module';
	/** The question is sent from the help panel. */
	public const SOURCE_HELP_PANEL = 'help panel';

	/** The question is sent to the wiki's helpdesk. */
	public const TARGET_HELPDESK = 'helpdesk';
	/** The question is sent to the talk page of the asking user's mentor. */
	public const TARGET_MENTOR_TALK = 'mentor talk page';

	/**
	 * @param string $source One of the SOURCE_* constants.
	 * @param string $target One of the TARGET_* constants.
	 * @param IContextSource $context
	 * @param string $body Wikitext of the question.
	 * @param string $relevantTitle Title of the page the question is about, if any.
	 * @return QuestionPoster
	 * @throws UserNotLoggedIn
	 */
	public function getQuestionPoster(
		string $source,
		string $target,
		IContextSource $context,
		string $body,
		string $relevantTitle = ''
	): QuestionPoster {
		Assert::parameter(
			in_array( $source, [ self::SOURCE_MENTORSHIP_MODULE, self::SOURCE_HELP_PANEL ], true ),
			'$source', 'must be one of the QuestionPosterFactory::SOURCE_* constants' );
		Assert::parameter(
			in_array( $target, [ self::TARGET_HELPDESK, self::TARGET_MENTOR_TALK ], true ),
			'$target', 'must be one of the QuestionPosterFactory::TARGET_* constants' );

		if ( $target === self::TARGET_HELPDESK ) {
			return new HelpdeskQuestionPoster( $context, $body, $relevantTitle );
		} elseif ( $source === self::SOURCE_HELP_PANEL ) {
			return new HelppanelMentorQuestionPoster( $context, $body, $relevantTitle );
		} else {
			return new HomepageMentorQuestionPoster( $context, $body, $relevantTitle );
		}
	}

}
