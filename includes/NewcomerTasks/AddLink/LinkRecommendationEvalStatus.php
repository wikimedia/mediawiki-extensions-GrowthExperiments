<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use StatusValue;

class LinkRecommendationEvalStatus extends StatusValue {

	public const NOT_GOOD_CAUSE_ALL_RECOMMENDATIONS_PRUNED = 'all-recommendations-pruned';
	public const NOT_GOOD_CAUSE_OTHER = 'other';

	public function getLinkRecommendation(): LinkRecommendation {
		if ( !$this->isGood() ) {
			throw new \LogicException( 'Cannot get LinkRecommendation from a failed status' );
		}
		if ( $this->getValue() === null ) {
			throw new \LogicException( 'Cannot get LinkRecommendation from a status without a value' );
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

	public function setNotGoodCause( string $cause ): void {
		if ( !$this->statusData ) {
			$this->statusData = [];
		}

		$this->statusData['notGoodCause'] = $cause;
	}

	public function getNotGoodCause(): string {
		if ( $this->isGood() ) {
			throw new \LogicException( 'Status is good.' );
		}

		return $this->statusData['notGoodCause'] ?? self::NOT_GOOD_CAUSE_OTHER;
	}

	public function getNumberOfPrunedRedLinks(): int {
		if ( !$this->statusData ) {
			return 0;
		}
		return $this->statusData[ 'numberOfPrunedRedLinks' ] ?? 0;
	}
}
