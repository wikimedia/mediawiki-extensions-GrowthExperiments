<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use IBufferingStatsdDataFactory;
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

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/**
	 * @param WANObjectCache $cache
	 * @param ImageRecommendationProvider $imageRecommendationProvider
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		ImageRecommendationProvider $imageRecommendationProvider,
		IBufferingStatsdDataFactory $statsdDataFactory
	) {
		$this->cache = $cache;
		$this->imageRecommendationProvider = $imageRecommendationProvider;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		return self::getWithSetCallback(
			$this->cache,
			$this->imageRecommendationProvider,
			$taskType,
			$title,
			__METHOD__,
			$this->statsdDataFactory
		);
	}

	/**
	 * @param WANObjectCache $cache
	 * @param ImageRecommendationProvider $imageRecommendationProvider
	 * @param TaskType $taskType
	 * @param LinkTarget $title
	 * @param string $fname The context in which this method is called. Used for instrumenting cache misses.
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @return false|mixed
	 */
	public static function getWithSetCallback(
		WANObjectCache $cache,
		ImageRecommendationProvider $imageRecommendationProvider,
		TaskType $taskType,
		LinkTarget $title,
		string $fname,
		IBufferingStatsdDataFactory $statsdDataFactory
	) {
		return $cache->getWithSetCallback(
			self::makeKey( $cache, $taskType->getId(), $title->getDBkey() ),
			// The recommendation won't change, but other metadata might and caching for longer might be
			// problematic if e.g. the image got vandalized.
			$cache::TTL_MINUTE * 5,
			static function ( $oldValue, &$ttl ) use (
				$title, $taskType, $imageRecommendationProvider, $cache, $fname, $statsdDataFactory
			) {
				// This is a cache miss. That is expected when TaskSetListener->run calls the method, because we're
				// warming the cache. We want to instrument cache misses when we get here from the ::get method,
				// because that's called in BeforePageDisplay where we expect to have a cached result.
				if ( $fname === __CLASS__ . '::get' ) {
					$statsdDataFactory->increment( 'GrowthExperiments.CacheBackedImageRecommendationProvider.miss' );
				}

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
