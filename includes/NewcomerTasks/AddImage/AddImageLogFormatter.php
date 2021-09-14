<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use LogFormatter;

class AddImageLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageKey() {
		$accepted = $this->entry->getParameters()['accepted'];
		return $accepted
			? 'logentry-growthexperiments-addimage-accepted'
			: 'logentry-growthexperiments-addimage-rejected';
	}

}
