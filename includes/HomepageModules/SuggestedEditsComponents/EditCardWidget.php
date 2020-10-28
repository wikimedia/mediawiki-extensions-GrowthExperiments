<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use GrowthExperiments\NewcomerTasks\Task\Task;
use OOUI\Tag;
use OOUI\Widget;

class EditCardWidget extends Widget {

	/** @var Task */
	private $task;

	/** @inheritDoc */
	public function __construct( array $config = [] ) {
		$this->task = $config['task'];
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

	/** @return Tag */
	private function getImageContent() : Tag {
		return ( new Tag( 'div' ) )->addClasses( [ 'se-card-image no-image skeleton' ] );
	}

	/** @return Tag */
	private function getTextContent() : Tag {
		return ( new Tag( 'div' ) )
			->addClasses( [ 'se-card-text' ] )
			->setAttributes( [ 'dir' => 'dir' ] )
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
