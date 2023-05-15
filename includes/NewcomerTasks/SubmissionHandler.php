<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * Submission handlers process user submissions of recommendations (such as a user
 * accepting or rejecting a recommendation).
 *
 * Since the structured task UI heavily relies on integration with the editor UI,
 * edits happen via the normal APIs used for editing (typically, visualeditoredit),
 * so turning the submission into an edit is not the handler's responsibility.
 */
interface SubmissionHandler {

	/**
	 * Validate a recommendation submission. If validation fails, the submission won't
	 * be turned into an edit.
	 *
	 * @param TaskType $taskType The type of the recommendation.
	 * @param ProperPageIdentity $page The article the recommendation was about.
	 * @param UserIdentity $user The user who acted on the recommendation.
	 * @param int|null $baseRevId Revision that the recommendation was for, or null if no
	 *   revision ID is set in the 'oldid' parameter from VisualEditor.
	 * @param array $data Tasktype-specific data. Typically, this is the data returned via
	 *   the visualeditoredit API's plugin mechanism.
	 * @return StatusValue Success status. A good status is required to pass. When there are
	 *   errors, the OK flag determines whether those should be logged as production errors.
	 *   The StatusValue should always contain a single error.
	 */
	public function validate(
		TaskType $taskType,
		ProperPageIdentity $page,
		UserIdentity $user,
		?int $baseRevId,
		array $data
	): StatusValue;

	/**
	 * Handle a recommendation submission. This is called after validation was successful
	 * and the recommendation has been turned into an edit and saved, but within the same
	 * transaction round.
	 *
	 * @param TaskType $taskType The type of the recommendation.
	 * @param ProperPageIdentity $page The article the recommendation was about.
	 * @param UserIdentity $user The user who acted on the recommendation.
	 * @param int|null $baseRevId Revision that the recommendation was for, or null if no
	 *   revision ID is set in the 'oldid' parameter from VisualEditor.
	 * @param int|null $editRevId New revision created (when the recommendation was accepted
	 *   or partially accepted).
	 * @param array $data Tasktype-specific data. Typically, this is the data returned via
	 *   the visualeditoredit API's plugin mechanism.
	 * @return StatusValue A success status. When it contains errors, its OK flag determines
	 *   whether those should be logged as production errors. When it does not contain errors,
	 *   it holds an array with the following fields:
	 *   - logId (int, optional): ID of the log entry that was created.
	 */
	public function handle(
		TaskType $taskType,
		ProperPageIdentity $page,
		UserIdentity $user,
		?int $baseRevId,
		?int $editRevId,
		array $data
	): StatusValue;

}
