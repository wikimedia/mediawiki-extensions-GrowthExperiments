<?php

namespace GrowthExperiments\NewcomerTasks;

use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use StatusValue;

class TemplateBasedTaskSubmissionHandler extends AbstractSubmissionHandler implements SubmissionHandler {

	/** @inheritDoc */
	public function handle(
		ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		return StatusValue::newGood();
	}

	/** @inheritDoc */
	public function validate(
		ProperPageIdentity $page, UserIdentity $user, ?int $baseRevId, array $data
	): StatusValue {
		return StatusValue::newGood();
	}
}
