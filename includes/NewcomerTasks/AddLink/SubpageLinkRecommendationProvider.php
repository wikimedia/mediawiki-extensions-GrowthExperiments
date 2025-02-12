<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\SubpageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;
use StatusValue;

/**
 * Enable by adding the following to LocalSettings.php or a similar location:
 *     $class = \GrowthExperiments\NewcomerTasks\AddLink\SubpageLinkRecommendationProvider::class;
 *     $wgHooks['MediaWikiServices'][] = "$class::onMediaWikiServices";
 *     $wgHooks['ContentHandlerDefaultModelFor'][] = "$class::onContentHandlerDefaultModelFor";
 *
 * @inheritDoc
 */
class SubpageLinkRecommendationProvider
	extends SubpageRecommendationProvider
	implements LinkRecommendationProvider
{
	/** @inheritDoc */
	protected static $subpageName = 'addlink';

	/** @inheritDoc */
	protected static $serviceName = 'GrowthExperimentsLinkRecommendationProvider';

	/** @inheritDoc */
	protected static $recommendationTaskTypeClass = LinkRecommendationTaskType::class;

	/**
	 * @inheritDoc
	 * @return LinkRecommendation|StatusValue
	 */
	public function createRecommendation(
		Title $title,
		TaskType $taskType,
		array $data,
		array $suggestionFilters = []
	) {
		return new LinkRecommendation(
			$title,
			$title->getArticleID(),
			$title->getLatestRevID(),
			LinkRecommendation::getLinksFromArray( $data['links'] ),
			// We don't really need the meta field for subpage provider, so provide
			// a fallback if not set
			LinkRecommendation::getMetadataFromArray( $data['meta'] ?? [] )
		);
	}

	public function getDetailed( LinkTarget $title, TaskType $taskType ): LinkRecommendationEvalStatus {
		$recommendation = $this->get( $title, $taskType );
		if ( $recommendation instanceof StatusValue ) {
			return LinkRecommendationEvalStatus::newGood()->merge( $recommendation );
		}
		return LinkRecommendationEvalStatus::newGood( $recommendation );
	}
}
