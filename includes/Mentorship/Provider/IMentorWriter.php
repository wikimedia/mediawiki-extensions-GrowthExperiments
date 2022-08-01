<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Mentorship\Mentor;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use StatusValue;

interface IMentorWriter {

	/**
	 * Add a mentor to the mentor list
	 *
	 * Properties of the future mentor will be taken from
	 * the Mentor object passed.
	 *
	 * @param Mentor $mentor
	 * @param UserIdentity $performer User who performed the action
	 * @param string $summary
	 * @param bool $bypassWarnings Should warnings/non-fatals stop the operation? Defaults to
	 * false.
	 * @return StatusValue
	 */
	public function addMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue;

	/**
	 * Remove a mentor from the database
	 *
	 * This will also reassign all the mentor's mentees to someone else. To stop
	 * accepting new mentees, use setAutoAssigned().
	 *
	 * @param Mentor $mentor
	 * @param UserIdentity $performer User who performed the action
	 * @param string $summary
	 * @param bool $bypassWarnings Should warnings/non-fatals stop the operation? Defaults to
	 * true.
	 * @return StatusValue
	 */
	public function removeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue;

	/**
	 * Change a mentor in the mentor list
	 *
	 * This will change all options of a given mentor
	 * to those contained in the Mentor object passed.
	 *
	 * Use Mentor::set* methods to change the options.
	 *
	 * @param Mentor $mentor
	 * @param UserIdentity $performer User who performed the action
	 * @param string $summary
	 * @param bool $bypassWarnings Should warnings/non-fatals stop the operation? Defaults to
	 * true.
	 * @return StatusValue
	 * @throws InvalidArgumentException when changeMentor was called, but the mentor is not in
	 * the list.
	 */
	public function changeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue;
}
