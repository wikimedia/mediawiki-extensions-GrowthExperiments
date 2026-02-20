<?php

namespace GrowthExperiments\HelpPanel;

use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;

class QuestionStoreFactory {

	public static function newFromContextAndStorage(
		IContextSource $context,
		string $storage
	): QuestionStore {
		$services = MediaWikiServices::getInstance();
		return new QuestionStore(
			$context->getUser(),
			$storage,
			$services->getRevisionStore(),
			$services->getContentLanguage(),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup(),
			$services->getJobQueueGroup(),
			$context->getRequest()->wasPosted()
		);
	}

}
