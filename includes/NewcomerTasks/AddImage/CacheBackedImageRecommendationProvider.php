<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use WANObjectCache;
use Wikimedia\Assert\Assert;

/**
 * Get image recommendation data from cache if possible; fall back to service provider otherwise.
 *
 * Used as a drop-in replacement for the uncached ServiceImageRecommendationProvider. The static
 * method is called in CacheDecorator.php when a TaskSet includes ImageRecommendationTaskType objects.
 */
class CacheBackedImageRecommendationProvider implements ImageRecommendationProvider {

	/** @var WANObjectCache */
	private $cache;

	/** @var ImageRecommendationProvider */
	private $imageRecommendationProvider;

	/**
	 * @param WANObjectCache $cache
	 * @param ImageRecommendationProvider $imageRecommendationProvider
	 */
	public function __construct(
		WANObjectCache $cache,
		ImageRecommendationProvider $imageRecommendationProvider
	) {
		$this->cache = $cache;
		$this->imageRecommendationProvider = $imageRecommendationProvider;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( ImageRecommendationTaskType::class, $taskType, '$taskType' );
		return self::getWithSetCallback( $this->cache, $this->imageRecommendationProvider, $taskType, $title );
	}

	/**
	 * @param WANObjectCache $cache
	 * @param ImageRecommendationProvider $imageRecommendationProvider
	 * @param TaskType $taskType
	 * @param LinkTarget $title
	 * @return false|mixed
	 */
	public static function getWithSetCallback(
		WANObjectCache $cache,
		ImageRecommendationProvider $imageRecommendationProvider,
		TaskType $taskType,
		LinkTarget $title
	) {
		return $cache->getWithSetCallback(
			self::makeKey( $cache, $taskType->getId(), $title->getDBkey() ),
			// The recommendation won't change, but other metadata might and caching for longer might be
			// problematic if e.g. the image got vandalized.
			$cache::TTL_MINUTE * 5,
			static function ( $oldValue, &$ttl ) use ( $title, $taskType, $imageRecommendationProvider, $cache ) {
				$response = $imageRecommendationProvider->get( $title, $taskType );
				if ( $response instanceof StatusValue ) {
					$ttl = $cache::TTL_UNCACHEABLE;
				}
				return $response;
			}
		);
	}

	/**
	 * @param WANObjectCache $cache
	 * @param string $taskTypeId
	 * @param string $dbKey
	 * @return string
	 */
	public static function makeKey( WANObjectCache $cache, string $taskTypeId, string $dbKey ): string {
		return $cache->makeKey( 'GrowthExperiments', 'Recommendations', $taskTypeId, $dbKey );
	}
}
