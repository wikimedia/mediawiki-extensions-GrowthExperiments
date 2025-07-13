<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataFilter;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\Util;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
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

	private MenteeOverviewDataProvider $dataProvider;
	private StarredMenteesStore $starredMenteesStore;
	private UserFactory $userFactory;
	private TitleFactory $titleFactory;
	private TitleParser $titleParser;
	private LinkBatchFactory $linkBatchFactory;

	public function __construct(
		MenteeOverviewDataProvider $dataProvider,
		StarredMenteesStore $starredMenteesStore,
		UserFactory $userFactory,
		TitleFactory $titleFactory,
		TitleParser $titleParser,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->dataProvider = $dataProvider;
		$this->starredMenteesStore = $starredMenteesStore;
		$this->userFactory = $userFactory;
		$this->titleFactory = $titleFactory;
		$this->titleParser = $titleParser;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * @return array
	 * @throws HttpException
	 */
	public function run() {
		$authority = $this->getAuthority();
		if ( !$authority->isNamed() ) {
			throw new HttpException( 'You must be logged in', 403 );
		}
		$user = $authority->getUser();

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
		if ( $params['activedaysago'] !== null ) {
			$dataFilter->activeDaysAgo( $params['activedaysago'] );
		}
		if ( $params['onlystarred'] === true ) {
			$dataFilter->onlyIds(
				array_map( static function ( UserIdentity $mentee ) {
					return $mentee->getId();
				}, $this->starredMenteesStore->getStarredMentees( $user ) )
			);
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
			} catch ( ParameterAssertionException ) {
				throw new HttpException( 'Invalid sortby', 400 );
			}
		}

		$data = $dataFilter->filter();
		$context = RequestContext::getMain();
		$nowUnix = (int)MWTimestamp::now( TS_UNIX );
		$batch = $this->linkBatchFactory->newLinkBatch();
		$batch->setCaller( __METHOD__ );
		array_walk( $data, function ( &$menteeData ) use ( $context, $nowUnix, $batch ) {
			if ( isset( $menteeData['last_active'] ) ) {
				$menteeData['last_active'] = [
					'raw' => $menteeData['last_active'],
					'human' => Util::getRelativeTime(
						$context,
						$nowUnix - (int)MWTimestamp::getInstance(
							$menteeData['last_active']
						)->getTimestamp( TS_UNIX )
					)
				];
			}

			if ( $menteeData['registration'] ) {
				$menteeData['registration'] = [
					'raw' => $menteeData['registration'],
					'human' => $context->getLanguage()->sprintfDate(
						'Y-m-d',
						$menteeData['registration']
					)
				];
			}

			if ( $menteeData['username'] ) {
				$batch->addObj( $this->titleParser->parseTitle(
					$menteeData['username'],
					NS_USER
				) );
			}
		} );

		$batch->execute();
		array_walk( $data, function ( &$menteeData ) {
			if ( $menteeData['username'] ) {
				$menteeData['userpage_exists'] = $this->titleFactory->newFromText(
					$menteeData['username'],
					NS_USER
				)->isKnown();
				$menteeData['usertalk_exists'] = $this->titleFactory->newFromText(
					$menteeData['username'],
					NS_USER_TALK
				)->isKnown();
				$menteeData['user_is_hidden'] = $this->userFactory->newFromName(
					$menteeData['username']
				)->isHidden();
			}
		} );

		return [
			'mentees' => array_values( $data ),
			'totalRows' => $dataFilter->getTotalRows(),
			'assignedMentees' => count( $allData ),
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
			'activedaysago' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
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
			'onlystarred' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
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
