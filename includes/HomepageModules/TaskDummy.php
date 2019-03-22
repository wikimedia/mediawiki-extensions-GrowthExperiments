<?php

namespace GrowthExperiments\HomepageModules;

class TaskDummy extends BaseModule {

	/**
	 * @var string
	 */
	private $header;

	/**
	 * @var bool
	 */
	private $completed;

	/**
	 * TaskDummy constructor.
	 * @param string $header
	 * @param bool $completed
	 */
	public function __construct( $header, $completed ) {
		parent::__construct( 'taskdummy' );
		$this->header = $header;
		$this->completed = $completed;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		return '[' . ( $this->completed ? 'x' : ' ' ) . '] ' . $this->header;
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		if ( $this->completed ) {
			return 'Thanks';
		} else {
			return "Please do this";
		}
	}

}
