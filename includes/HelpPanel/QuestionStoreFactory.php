<?php

namespace GrowthExperiments\HelpPanel;

use MediaWiki\MediaWikiServices;
use User;

class QuestionStoreFactory {

	/**
	 * @param User $user
	 * @param string $storage
	 * @return QuestionStore
	 */
	public static function newFromUserAndStorage( User $user, $storage ) {
		return new QuestionStore(
			$user,
			$storage,
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			MediaWikiServices::getInstance()->getContentLanguage()
		);
	}

}
