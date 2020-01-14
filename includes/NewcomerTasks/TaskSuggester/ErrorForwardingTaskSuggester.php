<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * An TaskSuggester which returns a pre-configured error.
 *
 * Used for the delayed reporting of errors that happen early on in the lifecycle where we
 * probably don't have a straightforward way to notify the user. This is not uncommon since
 * some of the configuration might be managed on-wiki; and we don't want to use internal
 * reporting or exceptions for user errors.
 */
class ErrorForwardingTaskSuggester implements TaskSuggester {

	/** @var StatusValue */
	private $status;

	/**
	 * @param StatusValue $status The error to return.
	 */
	public function __construct( StatusValue $status ) {
		$this->status = $status;
	}

	/** @inheritDoc */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = null,
		array $topicFilter = null,
		$limit = null,
		$offset = null,
		$debug = false
	) {
		return $this->status;
	}

}
