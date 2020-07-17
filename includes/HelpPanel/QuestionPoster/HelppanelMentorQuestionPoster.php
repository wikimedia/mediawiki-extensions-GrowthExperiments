<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HomepageModules\Mentorship;

/**
 * QuestionPoster variant for asking questions from a mentor, via the help panel.
 * The edit tag and wording are slightly different from asking mentor questions via the homepage.
 */
class HelppanelMentorQuestionPoster extends MentorQuestionPoster {

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return Mentorship::MENTORSHIP_HELPPANEL_QUESTION_TAG;
	}

}
