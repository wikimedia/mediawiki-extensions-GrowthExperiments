<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataFilter;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\Util;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MWTimestamp;
use RequestContext;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Returns information about user's mentees.
 *
 * The class intentionally does not check the user is currently
 * listed as a mentor, because users who were previously mentors may still have
 * mentees. Users who never were a mentor will receive an empty array anyway.
 */
class MenteesHandler extends SimpleHandler {
	/** @var MenteeOverviewDataProvider */
	private $dataProvider;

	/**
	 * @param MenteeOverviewDataProvider $dataProvider
	 */
	public function __construct(
		MenteeOverviewDataProvider $dataProvider
	) {
		$this->dataProvider = $dataProvider;
	}

	/**
	 * @return array
	 * @throws HttpException
	 */
	public function run() {
		$user = $this->getAuthority()->getUser();
		if ( !$user->isRegistered() ) {
			throw new HttpException( 'You must be logged in', 403 );
		}

		$params = $this->getValidatedParams();
		$limit = $params['limit'] ?? 10;
		$offset = $params['offset'] ?? 0;

		$allData = $this->dataProvider->getFormattedDataForMentor( $user );
		$dataFilter = new MenteeOverviewDataFilter( $allData );
		$dataFilter
			->limit( $limit )
			->offset( $offset );

		if ( $params['prefix'] !== null ) {
			$dataFilter->prefix( $params['prefix'] );
		}
		if ( $params['minedits'] !== null ) {
			$dataFilter->minEdits( $params['minedits'] );
		}
		if ( $params['maxedits'] !== null ) {
			$dataFilter->maxEdits( $params['maxedits'] );
		}
		if ( $params['sortby'] !== null ) {
			// validation is done by data filter
			try {
				$orderRaw = $params['order'] ?? 'desc';
				if ( $orderRaw === 'desc' ) {
					$order = MenteeOverviewDataFilter::SORT_ORDER_DESCENDING;
				} elseif ( $orderRaw === 'asc' ) {
					$order = MenteeOverviewDataFilter::SORT_ORDER_ASCENDING;
				} else {
					throw new HttpException( 'Invalid order', 400 );
				}
				$dataFilter->sort( $params['sortby'], $order );
			} catch ( ParameterAssertionException $e ) {
				throw new HttpException( 'Invalid sortby', 400 );
			}
		}

		$data = $dataFilter->filter();
		$context = RequestContext::getMain();
		$nowUnix = MWTimestamp::now( TS_UNIX );
		array_walk( $data, static function ( &$menteeData ) use ( $context, $nowUnix ) {
			if ( isset( $menteeData['last_active'] ) ) {
				$menteeData['last_active'] = [
					'raw' => $menteeData['last_active'],
					'human' => Util::getRelativeTime(
						$context,
						$nowUnix - MWTimestamp::getInstance(
							$menteeData['last_active']
						)->getTimestamp()
					)
				];
			}

			if ( $menteeData['registration'] ) {
				$menteeData['registration'] = [
					'raw' => $menteeData['registration'],
					'human' => Util::getRelativeTime(
						$context,
						$nowUnix - MWTimestamp::getInstance(
							$menteeData['registration']
						)->getTimestamp()
					)
				];
			}
		} );

		return [
			'mentees' => $data,
			'totalRows' => $dataFilter->getTotalRows(),
			'limit' => $limit,
			'offset' => $offset
		];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'offset' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'prefix' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'minedits' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'maxedits' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'sortby' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'order' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
