<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HomepageModules\Mentorship;

/**
 * QuestionPoster variant for asking questions from a mentor, via the homepage.
 * The edit tag and wording are slightly different from asking mentor questions via the help panel.
 */
class HomepageMentorQuestionPoster extends MentorQuestionPoster {

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
	}

}
