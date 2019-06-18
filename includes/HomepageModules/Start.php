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

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		return (bool)$this->tasks;
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
	protected function getHeader() {
		return $this->getHeaderTextElement();
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryHeader() {
		$icon = $this->getHeaderIcon(
			$this->getHeaderIconName(),
			false
		);
		$text = $this->getHeaderTextElement();
		$navIcon = $this->getNavIcon();
		return $icon . $text . $navIcon;
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
		}, $this->tasks ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return implode( "\n", array_map( function ( BaseTaskModule $module ) {
			return $module->render( HomepageModule::RENDER_MOBILE_SUMMARY );
		}, $this->tasks ) );
	}
}
