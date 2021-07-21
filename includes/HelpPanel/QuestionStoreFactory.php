<?php

namespace GrowthExperiments\HelpPanel;

use IContextSource;
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
			$services->getDBLoadBalancer(),
			$services->getContentLanguage(),
			$services->getUserOptionsManager(),
			$context->getRequest()->wasPosted()
		);
	}

}
