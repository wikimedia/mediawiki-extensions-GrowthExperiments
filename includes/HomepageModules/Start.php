<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\HomepageModule;
use IContextSource;

/**
 * This is the "Start" module. It shows specific tasks to help
 * new editors getting started.
 *
 * @package GrowthExperiments\HomepageModules
 */
class Start extends BaseTaskModule {

	/**
	 * @var BaseTaskModule[]
	 */
	private $tasks;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start', $context );

		$this->tasks = [
			'account' => new Account( $context ),
			'email' => new Email( $context ),
			'tutorial' => new Tutorial( $context )
		];
		if ( SuggestedEdits::isEnabled( $context ) ) {
			$this->tasks['startediting'] = new StartEditing( $context );
		} else {
			$this->tasks['userpage'] = new Userpage( $context );
		}
	}

	/**
	 * @return array|BaseTaskModule[]
	 */
	public function getTasks() {
		return $this->tasks;
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		foreach ( $this->getVisibleTasks() as $task ) {
			if ( !$task->isCompleted() ) {
				return false;
			}
		}
		return true;
	}

	/** @inheritDoc */
	protected function setMode( $mode ) {
		parent::setMode( $mode );
		foreach ( $this->tasks as $task ) {
			$task->setMode( $mode );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function shouldInvertHeaderIcon() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		return (bool)$this->getVisibleTasks();
	}

	/**
	 * @inheritDoc
	 */
	protected function getCssClasses() {
		$startEditingTask = $this->tasks['startediting'] ?? null;
		return array_merge(
			parent::getCssClasses(),
			$startEditingTask && $startEditingTask->isCompleted() ?
				[ self::BASE_CSS_CLASS . '-start-startediting-completed' ] :
				[]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-start-header' )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryHeader() {
		// Use grandparent implementation: parent implementation doesn't add $navIcon
		return BaseModule::getMobileSummaryHeader();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'check';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderTag() {
		return 'h2';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", array_map( function ( BaseTaskModule $module ) {
			// Submodules inherit the mode from their parent, even when we force them
			// to render "as desktop".
			$module->setMode( $this->getMode() );
			if ( $module->canRender() ) {
				$module->outputDependencies();
				return $module->renderDesktop();
			}
		}, $this->getVisibleTasks() ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return implode( "\n", array_map( function ( BaseTaskModule $module ) {
			return $module->render( HomepageModule::RENDER_MOBILE_SUMMARY );
		}, $this->getVisibleTasks() ) );
	}

	private function getVisibleTasks() {
		return array_filter( $this->tasks, function ( BaseTaskModule $module ) {
			return $module->isVisible();
		} );
	}
}
