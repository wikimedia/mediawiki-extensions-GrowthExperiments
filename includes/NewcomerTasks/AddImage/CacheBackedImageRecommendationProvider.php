<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;
use Wikimedia\Assert\Assert;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

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

	private StatsFactory $statsFactory;

	/**
	 * @param WANObjectCache $cache
	 * @param ImageRecommendationProvider $imageRecommendationProvider
	 * @param StatsFactory $statsFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		ImageRecommendationProvider $imageRecommendationProvider,
		StatsFactory $statsFactory
	) {
		$this->cache = $cache;
		$this->imageRecommendationProvider = $imageRecommendationProvider;
		$this->statsFactory = $statsFactory;
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
			$this->statsFactory,
		);
	}

	/**
	 * @param WANObjectCache $cache
	 * @param ImageRecommendationProvider $imageRecommendationProvider
	 * @param TaskType $taskType
	 * @param LinkTarget $title
	 * @param string $fname The context in which this method is called. Used for instrumenting cache misses.
	 * @param StatsFactory $statsFactory
	 * @return false|mixed
	 */
	public static function getWithSetCallback(
		WANObjectCache $cache,
		ImageRecommendationProvider $imageRecommendationProvider,
		TaskType $taskType,
		LinkTarget $title,
		string $fname,
		StatsFactory $statsFactory,
	) {
		$wasCacheHit = true;
		$dataToBeReturned = $cache->getWithSetCallback(
			self::makeKey( $cache, $taskType->getId(), $title->getDBkey() ),
			// The recommendation won't change, but other metadata might and caching
			// for longer might be problematic if e.g. the image got vandalized.
			$cache::TTL_MINUTE * 5,
			static function ( $oldValue, &$ttl ) use (
				$title, $taskType, $imageRecommendationProvider, $cache, &$wasCacheHit
			) {
				$wasCacheHit = false;

				$response = $imageRecommendationProvider->get( $title, $taskType );
				if ( $response instanceof StatusValue ) {
					$ttl = $cache::TTL_UNCACHEABLE;
				}
				return $response;
			}
		);

		// When TaskSetListener->run calls the method, we're warming the cache and do not want to track hit/miss rates.
		// We want to instrument cache misses when we get here from the ::get method,
		// because that's called in BeforePageDisplay where we expect to have a cached result.
		if ( $fname === __CLASS__ . '::get' ) {
			$statsFactory->withComponent( 'GrowthExperiments' )
				->getCounter( 'cache_backed_image_recommendation_provider_total' )
				->setLabel( 'action', $wasCacheHit ? 'hit' : 'miss' )
				->increment();
		}
		return $dataToBeReturned;
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
