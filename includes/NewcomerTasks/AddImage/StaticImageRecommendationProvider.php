<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use OutOfBoundsException;
use StatusValue;
use Wikimedia\Assert\Assert;

/**
 * Simple provider with hardcoded responses for development setups.
 */
class StaticImageRecommendationProvider implements ImageRecommendationProvider {

	/** @var (ImageRecommendation|StatusValue)[] */
	private $recommendations;

	/** @var ImageRecommendation|StatusValue|null */
	private $default;

	/**
	 * @param (ImageRecommendation|StatusValue|array)[] $recommendations Title => recommendation
	 *   where title is in a `<namespace number>:<dbkey>` format. The recommendation can be an
	 *   ImageRecommendation object, or serialized (loadable with ImageRecommendation::fromArray).
	 * @param ImageRecommendation|StatusValue|array $default Default recommendation to use for titles
	 *   not present in $recommendations. When unset, will throw an error for such titles.
	 */
	public function __construct( array $recommendations, $default ) {
		$this->recommendations = array_map( [ $this, 'normalize' ], $recommendations );
		$this->default = $default ? $this->normalize( $default ) : null;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		$allowedTaskTypes = [ ImageRecommendationTaskType::class, SectionImageRecommendationTaskType::class ];
		Assert::parameterType( $allowedTaskTypes, $taskType, '$taskType' );
		$target = $title->getNamespace() . ':' . $title->getDBkey();
		$ret = $this->recommendations[$target] ?? $this->default;
		if ( $ret === null ) {
			throw new OutOfBoundsException( "No recommendation for $target" );
		}
		return $ret;
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
