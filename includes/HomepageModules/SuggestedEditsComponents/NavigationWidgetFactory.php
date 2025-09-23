<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use OOUI\ButtonWidget;
use OOUI\HtmlSnippet;
use OOUI\Tag;

class NavigationWidgetFactory {

	/** @var TaskSet|\StatusValue */
	private $taskSet;

	/** @var \MessageLocalizer */
	private $messageLocalizer;

	/** @var Task */
	private $task;

	/** @var \StatusValue */
	private $error;

	/**
	 * NavigationWidgetFactory constructor.
	 * Generate navigation elements for SuggestedEdits module
	 *
	 * @param \MessageLocalizer $messageLocalizer
	 * @param TaskSet|\StatusValue $taskSet
	 */
	public function __construct(
		\MessageLocalizer $messageLocalizer, $taskSet
	) {
		$this->messageLocalizer = $messageLocalizer;
		$this->taskSet = $taskSet;
		$this->task = $taskSet instanceof TaskSet && $taskSet->count() ? $taskSet[0] : null;
		$this->error = $taskSet instanceof \StatusValue ? $taskSet : null;
	}

	/**
	 * Return HTML for previous or next buttons
	 *
	 * @param string $direction Should be one of "Previous" or "Next" (case sensitive)
	 * @return Tag
	 */
	public function getPreviousNextButtonHtml( string $direction ): Tag {
		// The following classes are used here:
		// * suggested-edits-previous
		// * suggested-edits-next

		// The following message keys are used here:
		// * growthexperiments-homepage-suggestededits-previous-card
		// * growthexperiments-homepage-suggestededits-previous-next
		return ( new Tag( 'div' ) )->addClasses( [ 'suggested-edits-' . strtolower( $direction ) ] )
			->appendContent( new HtmlSnippet( ( new PreviousNext( [
				'direction' => $direction,
				'message' => $this->messageLocalizer->msg(
					'growthexperiments-homepage-suggestededits-' . strtolower( $direction ) . '-card'
				)->text(),
				'hidden' => !$this->task || $this->error,
			] ) )->toString() ) );
	}

	/**
	 * Return edit button
	 */
	public function getEditButton(): ButtonWidget {
		return new ButtonWidget( [
			'icon' => 'edit',
			'label' => $this->messageLocalizer->msg(
				'growthexperiments-homepage-suggestededits-edit-card'
			)->text(),
			'flags' => [ 'primary', 'progressive' ],
			'classes' => [ 'suggested-edits-footer-navigation-edit-button' ],
		] );
	}
}
