<?php

namespace GrowthExperiments\NewcomerTasks;

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
interface RecommendationSubmissionHandler {

	/**
	 * Validate a recommendation submission. If validation fails, the submission won't
	 * be turned into an edit.
	 *
	 * @param ProperPageIdentity $page The article the recommendation was about.
	 * @param UserIdentity $user The user who acted on the recommendation.
	 * @param int $baseRevId Revision that the recommendation was for.
	 * @param array $data Tasktype-specific data. Typically, this is the data returned via
	 *   the visualeditoredit API's plugin mechanism.
	 * @return array|null Null on success, a message descriptor array on failure.
	 */
	public function validate( ProperPageIdentity $page, UserIdentity $user, int $baseRevId,
		array $data ): ?array;

	/**
	 * Handle a recommendation submission. This is called after validation was successful
	 * and the recommendation has been turned into an edit and saved, but within the same
	 * transaction round.
	 *
	 * @param ProperPageIdentity $page The article the recommendation was about.
	 * @param UserIdentity $user The user who acted on the recommendation.
	 * @param int $baseRevId Revision that the recommendation was for.
	 * @param int|null $editRevId New revision created (when the recommendation was accepted
	 *   or partially accepted).
	 * @param array $data Tasktype-specific data. Typically, this is the data returned via
	 *   the visualeditoredit API's plugin mechanism.
	 * @return StatusValue A success status. On success, it holds an array with the following fields:
	 *   - logId (int, optional): ID of the log entry that was created.
	 */
	public function handle( ProperPageIdentity $page, UserIdentity $user, int $baseRevId, ?int $editRevId,
		array $data ): StatusValue;

}
