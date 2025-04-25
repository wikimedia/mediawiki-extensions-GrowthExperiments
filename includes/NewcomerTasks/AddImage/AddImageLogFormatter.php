<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use MediaWiki\Logging\LogFormatter;

class AddImageLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageKey() {
		$accepted = $this->entry->getParameters()['accepted'];
		return $accepted
			? 'logentry-growthexperiments-addimage-accepted'
			: 'logentry-growthexperiments-addimage-rejected';
	}

}
