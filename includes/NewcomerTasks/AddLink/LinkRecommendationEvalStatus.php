<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddLink;

use StatusValue;

/**
 * @inherits StatusValue<LinkRecommendation>
 */
class LinkRecommendationEvalStatus extends StatusValue {

	public const NOT_GOOD_CAUSE_ALL_RECOMMENDATIONS_PRUNED = 'all_recommendations_pruned';
	public const NOT_GOOD_CAUSE_ALREADY_STORED = 'already_stored';
	public const NOT_GOOD_CAUSE_KNOWN_UNAVAILABLE = 'known_unavailable';
	public const NOT_GOOD_CAUSE_GOOD_LINKS_COUNT_TOO_SMALL = 'good_links_count_too_small';
	public const NOT_GOOD_CAUSE_MINIMUM_TIME_DID_NOT_PASS = 'minimum_time_since_last_edit_did_not_pass';
	public const NOT_GOOD_CAUSE_DISAMBIGUATION_PAGE = 'disambiguation_page';
	public const NOT_GOOD_CAUSE_EXCLUDED_CATEGORY = 'excluded_category';
	public const NOT_GOOD_CAUSE_EXCLUDED_TEMPLATE = 'excluded_template';
	public const NOT_GOOD_CAUSE_LAST_EDIT_LINK_RECOMMENDATION = 'last_edit_link_recommendation';
	public const NOT_GOOD_CAUSE_LAST_EDIT_LINK_RECOMMENDATION_REVERT = 'last_edit_link_recommendation_revert';
	public const NOT_GOOD_CAUSE_OTHER = 'other';

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
