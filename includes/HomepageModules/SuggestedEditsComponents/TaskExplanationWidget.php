<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use OOUI\ButtonWidget;
use OOUI\IconWidget;
use OOUI\Tag;
use OOUI\Widget;

/**
 * Server-side rendering of the TaskExplanationWidget in Suggested Edits.
 *
 * Corresponds roughly to ext.growthExperiments.Homepage.SuggestedEdits.TaskExplanationWidget.js
 */
class TaskExplanationWidget extends Widget {

	/** @var \MessageLocalizer */
	private $localizer;

	/** @var TaskType */
	private $taskType;

	/** @inheritDoc */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );
		/** @var TaskSet|\StatusValue $taskSet */
		$taskSet = $config['taskSet'];
		if ( !$taskSet instanceof TaskSet || !$taskSet->count() ) {
			return;
		}
		$this->taskType = $taskSet[0]->getTaskType();
		/** @var \MessageLocalizer $localizer */
		$this->localizer = $config['localizer'];

		$this->appendContent(
			( new Tag( 'div' ) )
				->addClasses( [ 'suggested-edits-task-explanation-wrapper' ] )
				->appendContent(
					$this->getInfoRow(),
					$this->getDifficultyAndTime(),
					$this->getDescriptionRow()
				)
		);
	}

	/**
	 * @return Tag
	 */
	private function getInfoRow(): Tag {
		return ( new Tag( 'div' ) )
			->addClasses( [ 'suggested-edits-taskexplanation-additional-info' ] )
			->appendContent(
				$this->getName(),
				$this->getInfo()
			);
	}

	/**
	 * @return Tag
	 */
	private function getName(): Tag {
		return ( new Tag( 'span' ) )
			->addClasses( [ 'suggested-edits-task-explanation-heading' ] )
			->appendContent( $this->taskType->getName( $this->localizer )->text() );
	}

	/**
	 * @return ButtonWidget
	 */
	private function getInfo(): ButtonWidget {
		return new ButtonWidget( [
			'icon' => 'info-unpadded',
			'framed' => false,
			'label' => $this->taskType->getShortDescription( $this->localizer )->text(),
			'invisibleLabel' => true,
			'classes' => [ 'suggested-edits-task-explanation-info-button' ],
		] );
	}

	/**
	 * @return IconWidget|null
	 */
	private function getIcon(): ?IconWidget {
		$iconData = $this->taskType->getIconData();
		if ( array_key_exists( 'icon', $iconData ) ) {
			return new IconWidget(
				[
					'icon' => $iconData['icon'],
					'classes' => [ 'suggested-edits-task-explanation-icon' ]
				]
			);
		}
		return null;
	}

	/**
	 * @return Tag
	 */
	private function getDescriptionRow(): Tag {
		return ( new Tag( 'p' ) )
			->addClasses( [ 'suggested-edits-short-description' ] )
			->appendContent( $this->taskType->getShortDescription( $this->localizer )->text() );
	}

	/**
	 * @return Tag
	 */
	private function getDifficultyAndTime(): Tag {
		$difficulty = $this->taskType->getDifficulty();
		return ( new Tag( 'div' ) )
			->addClasses( [ 'suggested-edits-taskexplanation-difficulty-and-time' ] )
			->appendContent(
				( new Tag( 'div' ) )
					->addClasses( [ 'suggested-edits-difficulty-time-estimate' ] )
					->appendContent(
						$this->getIcon() ?? '',
						( new Tag( 'div' ) )->addClasses( [
							'suggested-edits-difficulty-indicator suggested-edits-difficulty-indicator-' . $difficulty
						] )->appendContent(
							$this->localizer->msg(
								'growthexperiments-homepage-suggestededits-difficulty-indicator-label-' .
								$difficulty
							)->text()
						),
						( new Tag( 'div' ) )->addClasses( [
							'suggested-edits-difficulty-level suggested-edits-difficulty-level-' . $difficulty
						] )->appendContent( $this->taskType->getTimeEstimate( $this->localizer )->text() )
					)
			);
	}
}
