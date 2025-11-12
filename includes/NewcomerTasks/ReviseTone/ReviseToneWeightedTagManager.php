<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\ReviseTone;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Page\ProperPageIdentity;
use Psr\Log\LoggerInterface;

class ReviseToneWeightedTagManager {

	public function __construct(
		private readonly ?WeightedTagsUpdater $weightedTagsUpdater,
		private readonly LoggerInterface $logger,
	) {
	}

	public function deletePageReviseToneWeightedTag( ProperPageIdentity $pageIdentity ): void {
		if ( !$this->weightedTagsUpdater ) {
			$this->logger->error(
				'WeightedTagsUpdater not available, cannot delete revise tone weighted tag for page {page}',
				[
					'page' => $pageIdentity->getDBkey(),
					'exception' => new \RuntimeException,
				],
			);
			return;
		}

		DeferredUpdates::addCallableUpdate( function () use ( $pageIdentity ) {
			$this->weightedTagsUpdater->resetWeightedTags(
				$pageIdentity,
				[ ReviseToneTaskTypeHandler::WEIGHTED_TAG_PREFIX ],
			);
		} );
	}
}
