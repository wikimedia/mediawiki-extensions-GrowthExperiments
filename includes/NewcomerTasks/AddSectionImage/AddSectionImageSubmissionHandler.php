<?php

namespace GrowthExperiments\NewcomerTasks\AddSectionImage;

use GrowthExperiments\NewcomerTasks\AbstractSubmissionHandler;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use StatusValue;

class AddSectionImageSubmissionHandler extends AbstractSubmissionHandler implements SubmissionHandler {

	public function validate(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, array $data
	): StatusValue {
		// TODO: Implement validate() method.
		return new StatusValue();
	}

	public function handle(
		TaskType $taskType, ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		// TODO: Implement handle() method.
		return new StatusValue();
	}
}
