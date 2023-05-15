<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use StatusValue;

class TemplateBasedTaskSubmissionHandler extends AbstractSubmissionHandler implements SubmissionHandler {

	/** @inheritDoc */
	public function handle(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		return StatusValue::newGood();
	}

	/** @inheritDoc */
	public function validate(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, array $data
	): StatusValue {
		return StatusValue::newGood();
	}
}
