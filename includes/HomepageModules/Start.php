<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\HomepageModule;
use IContextSource;

/**
 * Class Start
 *
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
			'tutorial' => new Tutorial( $context ),
			'userpage' => new Userpage( $context ),
		];
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
		foreach ( $this->tasks as $task ) {
			if ( !$task->isCompleted() ) {
				return false;
			}
		}
		return true;
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
			return $module->render();
		}, $this->tasks ) );
	}
}
