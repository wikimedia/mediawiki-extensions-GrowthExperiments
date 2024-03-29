<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AbstractSubmissionHandler;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * A fake submission handler for usage alongside NullTaskTypeHandler.
 */
class NullSubmissionHandler extends AbstractSubmissionHandler implements SubmissionHandler {

	/** @inheritDoc */
	public function validate(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, array $data
	): StatusValue {
		return StatusValue::newGood();
	}

	/** @inheritDoc */
	public function handle(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		return StatusValue::newGood();
	}
}
