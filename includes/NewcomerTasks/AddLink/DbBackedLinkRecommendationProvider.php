<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use MediaWiki\Linker\LinkTarget;

class DbBackedLinkRecommendationProvider implements LinkRecommendationProvider {

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/** @var LinkRecommendationStore */
	private $linkRecommendationStore;

	/**
	 * @param LinkRecommendationProvider $linkRecommendationProvider
	 * @param LinkRecommendationStore $linkRecommendationStore
	 */
	public function __construct(
		LinkRecommendationProvider $linkRecommendationProvider,
		LinkRecommendationStore $linkRecommendationStore
	) {
		$this->linkRecommendationProvider = $linkRecommendationProvider;
		$this->linkRecommendationStore = $linkRecommendationStore;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title ) {
		$linkRecommendation = $this->linkRecommendationStore->getByLinkTarget( $title );
		if ( !$linkRecommendation ) {
			$linkRecommendation = $this->linkRecommendationProvider->get( $title );
			if ( $linkRecommendation instanceof LinkRecommendation ) {
				$this->linkRecommendationStore->insert( $linkRecommendation );
			}
		}
		return $linkRecommendation;
	}

}
