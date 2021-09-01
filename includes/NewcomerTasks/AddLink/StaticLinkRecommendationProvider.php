<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use Wikimedia\Assert\Assert;

class StaticLinkRecommendationProvider implements LinkRecommendationProvider {

	/** @var (LinkRecommendation|StatusValue)[] */
	private $recommendations;

	/** @var LinkRecommendation|StatusValue */
	private $default;

	/**
	 * @param (LinkRecommendation|StatusValue)[] $recommendations Title => recommendation
	 *   where title is in a <namespace number>:<dbkey> format.
	 * @param LinkRecommendation|StatusValue $default Default recommendation to use for titles
	 *   not present in $recommendations.
	 */
	public function __construct( array $recommendations, $default ) {
		$this->recommendations = $recommendations;
		$this->default = $default;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( LinkRecommendationTaskType::class, $taskType, '$taskType' );
		$target = $title->getNamespace() . ':' . $title->getDBkey();
		return $this->recommendations[$target] ?? $this->default;
	}

}
