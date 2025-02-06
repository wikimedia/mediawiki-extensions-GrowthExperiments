<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use StatusValue;

class LinkRecommendationEvalStatus extends StatusValue {

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
}
