<?php

namespace GrowthExperiments;

use DateTime;
use MediaWiki\Http\HttpRequestFactory;
use WANObjectCache;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Minimal client for the Wikimedia Analytics Query Service.
 */
class AqsEditInfoService extends EditInfoService {

	private HttpRequestFactory $requestFactory;
	private WANObjectCache $cache;

	/** @var string Wiki name, in AQS format (domain prefix, e.g. 'en.wikipedia') */
	private string $wiki;

	/**
	 * @param HttpRequestFactory $requestFactory
	 * @param WANObjectCache $cache
	 * @param string $wiki Wiki name, in AQS format (domain prefix, e.g. 'en.wikipedia')
	 */
	public function __construct(
		HttpRequestFactory $requestFactory,
		WANObjectCache $cache,
		string $wiki
	) {
		$this->requestFactory = $requestFactory;
		$this->cache = $cache;
		$this->wiki = $wiki;
	}

	/** @inheritDoc */
	public function getEditsPerDay() {
		// AQS edit processing seems to be done monthly, but sometimes the processing lags,
		// so use the day two months ago.
		// The number shown above "edits in the last day" will be somewhat fake,
		// but hopefully no one cares.
		$day = new DateTime( '@' . ConvertibleTimestamp::time() . '-2 month -1 day' );
		$dayAfter = new DateTime( '@' . ConvertibleTimestamp::time() . '-2 month' );

		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'GrowthExperiments', 'AQS', 'edits',
				$this->wiki, $day->format( 'Ymd' ) ),
			WANObjectCache::TTL_DAY,
			function ( $oldValue, &$ttl, &$setOpts ) use ( $day, $dayAfter ) {
				$url = 'https://wikimedia.org/api/rest_v1/metrics/edits/aggregate/' . $this->wiki
					. '/user/content/daily/' . $day->format( 'Ymd' ) . '/' . $dayAfter->format( 'Ymd' );

				$status = Util::getJsonUrl( $this->requestFactory, $url );
				if ( !$status->isOK() ) {
					// Use short cache TTL for errors
					$ttl = WANObjectCache::TTL_MINUTE;
					return $status;
				}

				$data = $status->getValue();
				$edits = 0;
				// There should be 0 or 1 rows depending on whether there was any edit on the given day.
				foreach ( $data['items'][0]['results'] ?? [] as $row ) {
					$edits += $row['edits'];
				}
				return $edits;
			}
		);
	}

}
