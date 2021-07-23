<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use OOUI\HtmlSnippet;
use OOUI\Tag;

class CardWrapper {

	/** @var TaskSet|\StatusValue */
	private $taskSet;

	/** @var \MessageLocalizer */
	private $messageLocalizer;

	/** @var Task */
	private $task;

	/** @var \StatusValue */
	private $error;

	/** @var string */
	private $dir;

	/** @var bool */
	private $topicMatching;

	/**
	 * @param \MessageLocalizer $messageLocalizer
	 * @param bool $topicMatching
	 * @param string $dir
	 * @param TaskSet|\StatusValue $taskSet
	 */
	public function __construct(
		\MessageLocalizer $messageLocalizer, bool $topicMatching, string $dir, $taskSet
	) {
		$this->taskSet = $taskSet;
		$this->task = $taskSet instanceof TaskSet && $taskSet->count() ? $taskSet[0] : null;
		$this->error = $taskSet instanceof \StatusValue ? $taskSet : null;
		$this->messageLocalizer = $messageLocalizer;
		$this->dir = $dir;
		$this->topicMatching = $topicMatching;
	}

	/**
	 * @return string
	 * @throws \OOUI\Exception
	 */
	public function render() {
		$card = CardWidgetFactory::newFromTaskSet(
			$this->messageLocalizer,
			$this->topicMatching,
			$this->dir,
			$this->taskSet
		);
		return ( new Tag( 'div' ) )->addClasses( [
			'suggested-edits-card-wrapper',
			$card instanceof EditCardWidget ? '' : 'pseudo-card'
		] )->appendContent(
			$this->getPreviousNextButtonHtml( 'Previous' ),
			( new Tag( 'div' ) )
				->addClasses( [ 'suggested-edits-card' ] )
				->appendContent( $card ),
			$this->getPreviousNextButtonHtml( 'Next' )
		);
	}

	/**
	 * @param string $direction Should be one of "Previous" or "Next" (case sensitive)
	 * @return Tag
	 * @throws \OOUI\Exception
	 */
	private function getPreviousNextButtonHtml( string $direction ): Tag {
		return ( new Tag( 'div' ) )->addClasses( [ 'suggested-edits-' . strtolower( $direction ) ] )
			->appendContent( new HtmlSnippet( ( new PreviousNext( [
				'direction' => $direction,
				'message' => $this->messageLocalizer->msg(
					'growthexperiments-homepage-suggestededits-' . strtolower( $direction ) . '-card'
				)->text(),
				'hidden' => !$this->task || $this->error
			] ) )->toString() ) );
	}

}
