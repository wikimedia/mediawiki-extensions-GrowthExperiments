<?php

namespace GrowthExperiments\HelpPanel;

use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;

class QuestionStoreFactory {

	/**
	 * @param IContextSource $context
	 * @param string $storage
	 * @return QuestionStore
	 */
	public static function newFromContextAndStorage( IContextSource $context, $storage ) {
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
