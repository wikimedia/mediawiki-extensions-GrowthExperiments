<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\HomepageModule;

/**
 * Class Start
 *
 * This is the "Start" module. It shows specific tasks to help
 * new editors getting started.
 *
 * @package GrowthExperiments\HomepageModules
 */
class Start extends BaseModule {

	/**
	 * @var HomepageModule[]
	 */
	private $tasks;

	public function __construct() {
		parent::__construct( 'start' );

		$this->tasks = [
			new TaskDummy( 'Account created', true ),
			new TaskDummy( 'Add your email', false ),
			new TaskDummy( 'Learn to edit', true ),
			new TaskDummy( 'Create your user page', false ),
		];
	}

	protected function canRender() {
		return (bool)$this->tasks;
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return 'ext.growthExperiments.Homepage.Start.styles';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-start-header' )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", array_map( function ( HomepageModule $module ) {
			return $module->render( $this->getContext() );
		}, $this->tasks ) );
	}
}
