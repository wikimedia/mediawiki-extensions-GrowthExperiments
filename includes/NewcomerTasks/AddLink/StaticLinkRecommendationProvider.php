<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use OutOfBoundsException;
use StatusValue;
use Wikimedia\Assert\Assert;

class StaticLinkRecommendationProvider implements LinkRecommendationProvider {

	/** @var (LinkRecommendation|StatusValue)[] */
	private $recommendations;

	/** @var LinkRecommendation|StatusValue|null */
	private $default;

	/**
	 * @param (LinkRecommendation|StatusValue)[] $recommendations Title => recommendation
	 *   where title is in a <namespace number>:<dbkey> format.
	 * @param LinkRecommendation|StatusValue|null $default Default recommendation to use for titles
	 *   not present in $recommendations. When unset, will throw an error for such titles.
	 */
	public function __construct( array $recommendations, $default = null ) {
		$this->recommendations = $recommendations;
		$this->default = $default;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( LinkRecommendationTaskType::class, $taskType, '$taskType' );
		$target = $title->getNamespace() . ':' . $title->getDBkey();
		$ret = $this->recommendations[$target] ?? $this->default;
		if ( $ret === null ) {
			throw new OutOfBoundsException( "No recommendation for $target" );
		}
		return $ret;
	}

	public function getDetailed( LinkTarget $title, TaskType $taskType ): LinkRecommendationEvalStatus {
		$recommendationOrStatus = $this->get( $title, $taskType );
		if ( $recommendationOrStatus instanceof LinkRecommendationEvalStatus ) {
			return $recommendationOrStatus;
		}
		if ( $recommendationOrStatus instanceof StatusValue ) {
			return LinkRecommendationEvalStatus::newGood()->merge( $recommendationOrStatus );
		}
		return LinkRecommendationEvalStatus::newGood( $recommendationOrStatus );
	}

}
