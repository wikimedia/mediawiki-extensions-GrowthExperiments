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
		return new QuestionStore(
			$context->getUser(),
			$storage,
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			MediaWikiServices::getInstance()->getContentLanguage(),
			$context->getRequest()->wasPosted()
		);
	}

}
