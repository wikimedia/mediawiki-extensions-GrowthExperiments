<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use MediaWiki\Extension\WikimediaMessages\ArticleTopicFiltersRegistry;

class WikimediaTopicRegistry implements ITopicRegistry {
	public function getAllTopics(): array {
		return ArticleTopicFiltersRegistry::getTopicList();
	}
}
