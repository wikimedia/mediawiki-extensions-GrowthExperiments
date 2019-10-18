<?php

namespace GrowthExperiments;

use StatusValue;

/**
 * A service for getting edit-related site information.
 */
abstract class EditInfoService {

	/**
	 * Get the number of mainspace edits per day.
	 * A typical implementation would return the number of edits on the previous day.
	 * @return int|StatusValue The number of edits, or an error status.
	 */
	abstract public function getEditsPerDay();

}
