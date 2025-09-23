<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use OOUI\Widget;

class CardWidgetFactory {

	/**
	 * @param \MessageLocalizer $messageLocalizer
	 * @param bool $topicMatching
	 * @param bool $topicMatchModeIsAND
	 * @param string $dir
	 * @param TaskSet|\StatusValue $taskSet
	 * @return Widget
	 */
	public static function newFromTaskSet(
		\MessageLocalizer $messageLocalizer, bool $topicMatching, bool $topicMatchModeIsAND, string $dir, $taskSet
	): Widget {
		if ( $taskSet instanceof TaskSet ) {
			if ( $taskSet->count() ) {
				return new EditCardWidget( [
					'task' => $taskSet[0],
					'dir' => $dir,
				] );
			} else {
				return new NoResultsCardWidget( [
					'localizer' => $messageLocalizer,
					'topicMatching' => $topicMatching,
					'topicMatchModeIsAND' => $topicMatchModeIsAND,
				] );
			}
		}
		return new ErrorCardWidget( [ 'localizer' => $messageLocalizer ] );
	}
}
