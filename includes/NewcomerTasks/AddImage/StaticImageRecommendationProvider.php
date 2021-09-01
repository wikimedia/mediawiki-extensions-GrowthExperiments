<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use Wikimedia\Assert\Assert;

/**
 * Simple provider with hardcoded responses for development setups.
 */
class StaticImageRecommendationProvider implements ImageRecommendationProvider {

	/** @var (ImageRecommendation|StatusValue)[] */
	private $recommendations;

	/** @var ImageRecommendation|StatusValue */
	private $default;

	/**
	 * @param (ImageRecommendation|StatusValue|array)[] $recommendations Title => recommendation
	 *   where title is in a `<namespace number>:<dbkey>` format. The recommendation can be an
	 *   ImageRecommendation object, or serialized (loadable with ImageRecommendation::fromArray).
	 * @param ImageRecommendation|StatusValue|array $default Default recommendation to use for titles
	 *   not present in $recommendations.
	 */
	public function __construct( array $recommendations, $default ) {
		$this->recommendations = array_map( [ $this, 'normalize' ], $recommendations );
		$this->default = $this->normalize( $default );
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( ImageRecommendationTaskType::class, $taskType, '$taskType' );
		$target = $title->getNamespace() . ':' . $title->getDBkey();
		return $this->recommendations[$target] ?? $this->default;
	}

	/**
	 * @param ImageRecommendation|StatusValue|array $recommendation
	 * @return ImageRecommendation|StatusValue
	 */
	private function normalize( $recommendation ) {
		Assert::parameterType( [ ImageRecommendation::class, StatusValue::class, 'array' ],
			$recommendation, '$recommendation' );
		return is_array( $recommendation )
			? ImageRecommendation::fromArray( $recommendation )
			: $recommendation;
	}

}
