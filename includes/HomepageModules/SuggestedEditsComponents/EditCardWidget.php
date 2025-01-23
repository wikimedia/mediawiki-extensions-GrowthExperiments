<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use GrowthExperiments\NewcomerTasks\Task\Task;
use OOUI\Tag;
use OOUI\Widget;

class EditCardWidget extends Widget {

	/** @var Task */
	private $task;

	/** @var string */
	private $dir;

	/**
	 * @param array $config Configuration options
	 *   - Task $config['task'] Task to show
	 *   - string $config['dir'] Text direction ('ltr' or 'rtl')
	 *   - any option understood by Widget
	 */
	public function __construct( array $config = [] ) {
		$this->task = $config['task'];
		$this->dir = $config['dir'];
		parent::__construct( array_merge(
			$config,
			[ 'classes' => [ 'suggested-edits-task-card-wrapper' ] ]
		) );

		$this->appendContent(
			// We should be able to construct the same URL used on the client-side
			// here. But we may not want to, in order to allow for event logging code to
			// load on the client, so leaving this as is for now.
			( new Tag( 'a' ) )->setAttributes( [ 'href' => '#' ] )
			->addClasses( [ 'se-card-content' ] )
			->appendContent(
				$this->getImageContent(),
				$this->getTextContent()
			)
		);
	}

	private function getImageContent(): Tag {
		return ( new Tag( 'div' ) )->addClasses( [ 'se-card-image', 'no-image', 'skeleton',
			'mw-ge-tasktype-' . $this->task->getTaskType()->getId() ] );
	}

	private function getTextContent(): Tag {
		return ( new Tag( 'div' ) )
			->addClasses( [ 'se-card-text' ] )
			->setAttributes( [ 'dir' => $this->dir ] )
			->appendContent(
				( new Tag( 'h3' ) )
					->addClasses( [ 'se-card-title' ] )
				->appendContent( $this->task->getTitle()->getText() ),
				( new Tag( 'div' ) )
				->addClasses( [ 'se-card-extract skeleton' ] )
			)->appendContent(
				( new Tag( 'div' ) )->addClasses( [ 'se-card-pageviews skeleton' ] )
			);
	}

}
