<?php

namespace GrowthExperiments\HelpPanel\Tips;

use ArrayIterator;
use IteratorAggregate;
use Traversable;
use Wikimedia\Assert\Assert;

class TipSet implements IteratorAggregate {

	/**
	 * @var $tips TipInterface[]
	 */
	private $tips;
	/**
	 * @var string
	 */
	private $step;

	/**
	 * @param string $step
	 * @param array $tips
	 */
	public function __construct( string $step, array $tips ) {
		Assert::parameterElementType( TipInterface::class, $tips, '$tips' );
		$this->tips = array_values( $tips );
		$this->step = $step;
	}

	/** @inheritDoc */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->tips );
	}

	/**
	 * @param ParameterMapper $mapper
	 * @return array
	 */
	public function render( ParameterMapper $mapper ) {
		return array_filter( array_map( function ( $tip ) use ( $mapper ) {
			try {
				return $tip->render( $mapper->getParameters( $tip, $this->getStep() ) );
			} catch ( TipRenderException $exception ) {
				// If a tip throws an exception, don't render it. This is used
				// for handling cases like the text tip in tipset 6 for expand:
				// we don't want to show the "Learn more about expand" task at
				// all if the newcomer tasks configuration doesn't have that
				// title defined.
				return null;
			}
		}, $this->tips ) );
	}

	/**
	 * @return string
	 */
	public function getStep(): string {
		return $this->step;
	}

}
