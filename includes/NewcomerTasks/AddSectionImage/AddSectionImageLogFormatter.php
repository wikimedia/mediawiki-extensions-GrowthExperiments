<?php

namespace GrowthExperiments\NewcomerTasks\AddSectionImage;

use LogFormatter;

class AddSectionImageLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageKey() {
		$accepted = $this->entry->getParameters()['accepted'];
		return $accepted
			? 'logentry-growthexperiments-addsectionimage-accepted'
			: 'logentry-growthexperiments-addsectionimage-rejected';
	}

}
