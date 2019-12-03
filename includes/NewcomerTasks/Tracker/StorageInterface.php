<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

interface StorageInterface {

	/**
	 * Set the page ID in a storage bin specific to the current user.
	 * @param int $pageId
	 * @return bool
	 */
	public function set( int $pageId ): bool;

	/**
	 * @return array []int
	 *   Array of page IDs that the user has visited via clicks in the Suggested Edits module.
	 */
	public function get(): array;
}
