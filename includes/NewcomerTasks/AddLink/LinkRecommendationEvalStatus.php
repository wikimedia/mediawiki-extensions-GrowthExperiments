<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use StatusValue;

/**
 * @inherits StatusValue<LinkRecommendation>
 */
class LinkRecommendationEvalStatus extends StatusValue {

	public function getLinkRecommendation(): LinkRecommendation {
		if ( !$this->isGood() ) {
			throw new \LogicException( 'Cannot get LinkRecommendation from a failed status' );
		}
		if ( $this->getValue() === null ) {
			throw new \LogicException( 'Cannot get LinkRecommendation from a status without a value' );
		}
		if ( $this->getValue() instanceof StatusValue ) {
			/* @phan-suppress-next-line PhanTypeSuspiciousStringExpression */
			throw new \LogicException( 'Unexpected status as value:' . "\n" . $this->getValue() );
		}
		if ( !( $this->getValue() instanceof LinkRecommendation ) ) {
			throw new \LogicException( 'Value is of unexpected type ' . get_debug_type( $this->value ) );
		}

		return $this->getValue();
	}

	public function setNumberOfPrunedRedLinks( int $numberOfPrunedRedLinks ): void {
		if ( !$this->statusData ) {
			$this->statusData = [];
		}
		// TODO: thrown if $this->statusData not array

		$this->statusData['numberOfPrunedRedLinks'] = $numberOfPrunedRedLinks;
	}

	public function setNumberOfPrunedExcludedLinks( int $numberOfPrunedExcludedLinks ): void {
		if ( !$this->statusData ) {
			$this->statusData = [];
		}
		// TODO: thrown if $this->statusData not array

		$this->statusData['numberOfPrunedExcludedLinks'] = $numberOfPrunedExcludedLinks;
	}

	public function setNotGoodCause( NotGoodCause $cause ): void {
		if ( !$this->statusData ) {
			$this->statusData = [];
		}

		$this->statusData['notGoodCause'] = $cause;
	}

	public function getNotGoodCause(): NotGoodCause {
		if ( $this->isGood() ) {
			throw new \LogicException( 'Status is good.' );
		}

		$cause = $this->statusData['notGoodCause'] ?? null;

		return $cause instanceof NotGoodCause ? $cause : NotGoodCause::OTHER;
	}

	public function getNumberOfPrunedRedLinks(): int {
		if ( !$this->statusData ) {
			return 0;
		}
		return $this->statusData[ 'numberOfPrunedRedLinks' ] ?? 0;
	}
}
