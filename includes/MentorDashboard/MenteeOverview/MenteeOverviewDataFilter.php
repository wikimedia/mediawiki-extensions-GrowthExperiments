<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use LogicException;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\Assert\Assert;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Helper class to filter data about mentees
 *
 * This is consumed by the mentee overview module of the mentor dashboard.
 */
class MenteeOverviewDataFilter {
	public const SORT_BY_NAME = 'username';
	public const SORT_BY_REVERTS = 'reverted';
	public const SORT_BY_BLOCKS = 'blocks';
	public const SORT_BY_QUESTIONS = 'questions';
	public const SORT_BY_EDITCOUNT = 'editcount';
	public const SORT_BY_TENURE = 'registration';
	public const SORT_BY_ACTIVITY = 'last_active';

	public const SORT_ORDER_ASCENDING = 'asc';
	public const SORT_ORDER_DESCENDING = 'desc';

	/** @var int Number of seconds in a day */
	private const SECONDS_DAY = 86400;

	private const TIMESTAMP_SORTS = [
		self::SORT_BY_TENURE,
		self::SORT_BY_ACTIVITY
	];

	private const ALL_SORTS = [
		self::SORT_BY_NAME,
		self::SORT_BY_REVERTS,
		self::SORT_BY_BLOCKS,
		self::SORT_BY_QUESTIONS,
		self::SORT_BY_EDITCOUNT,
		self::SORT_BY_TENURE,
		self::SORT_BY_ACTIVITY
	];

	/** @var array */
	private $data;

	/** @var string */
	private $sortBy = self::SORT_BY_ACTIVITY;

	/** @var string */
	private $sortOrder = self::SORT_ORDER_DESCENDING;

	/** @var int */
	private $limit = 10;

	/** @var int */
	private $offset = 0;

	/** @var string */
	private $prefix = '';

	/** @var int|null */
	private $minEdits = null;

	/** @var int|null */
	private $maxEdits = null;

	/** @var int|null */
	private $activeDaysAgo = null;

	/** @var int[]|null */
	private $onlyIds = null;

	/** @var int|null */
	private $totalRows = null;

	/** @var array|null */
	private $filteredData = null;

	/**
	 * @param array $data Data to filter (must come from MenteeOverviewDataProvider)
	 */
	public function __construct(
		array $data
	) {
		$this->data = $data;
	}

	/**
	 * Set a limit (can be used for pagination)
	 *
	 * @param int $limit
	 * @return static
	 */
	public function limit( int $limit ): MenteeOverviewDataFilter {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Set an offset (can be used for pagination)
	 *
	 * @param int $offset
	 * @return static
	 */
	public function offset( int $offset ): MenteeOverviewDataFilter {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * Only mentees matching a given prefix
	 *
	 * @param string $prefix
	 * @return static
	 */
	public function prefix( string $prefix ) {
		$this->prefix = str_replace( '_', ' ', $prefix );
		return $this;
	}

	/**
	 * Set minimum number of edits
	 *
	 * @param int|null $minEdits
	 * @return static
	 */
	public function minEdits( ?int $minEdits ): MenteeOverviewDataFilter {
		$this->minEdits = $minEdits;
		return $this;
	}

	/**
	 * Set maximum number of edits
	 *
	 * @param int|null $maxEdits
	 * @return static
	 */
	public function maxEdits( ?int $maxEdits ): MenteeOverviewDataFilter {
		$this->maxEdits = $maxEdits;
		return $this;
	}

	/**
	 * Filter by user's activity
	 *
	 * @param int|null $activeDaysAgo
	 * @return static
	 */
	public function activeDaysAgo( ?int $activeDaysAgo ): MenteeOverviewDataFilter {
		$this->activeDaysAgo = $activeDaysAgo;
		return $this;
	}

	/**
	 * Include only users with specified IDs
	 *
	 * Used to filter by starred mentees.
	 *
	 * @param array|null $ids User IDs
	 * @return static
	 */
	public function onlyIds( ?array $ids ): MenteeOverviewDataFilter {
		$this->onlyIds = $ids;
		return $this;
	}

	/**
	 * Set sorting
	 *
	 * @param string $sortBy One of MenteeOverviewDataFilter::SORT_BY_* constants
	 * @param string $order One of MenteeOverviewDataFilter::SORT_ORDER_* constants
	 * @return static
	 */
	public function sort(
		string $sortBy = self::SORT_BY_ACTIVITY,
		string $order = self::SORT_ORDER_DESCENDING
	): MenteeOverviewDataFilter {
		Assert::parameter(
			in_array( $sortBy, self::ALL_SORTS ),
			'$sortBy',
			'must be one of the MenteeOverviewDataFilter::SORT_BY_* constants'
		);
		Assert::parameter(
			in_array( $order, [ self::SORT_ORDER_DESCENDING, self::SORT_ORDER_ASCENDING ] ),
			'$order',
			'must be one of the MenteeOverviewDataFilter::SORT_ORDER_* constants'
		);

		$this->sortBy = $sortBy;
		$this->sortOrder = $order;
		return $this;
	}

	/**
	 * Sort data using $field, interpreting it as a timestamp
	 *
	 * This does NOT check if it actually is a timestamp. Caller needs
	 * to do this.
	 *
	 * @param array &$data
	 * @param string $field
	 */
	private function doSortByTimestamp( array &$data, string $field ) {
		usort( $data, function ( $a, $b ) use ( $field ) {
			$tsA = MWTimestamp::getInstance( $a[$field] ?? false );
			$tsB = MWTimestamp::getInstance( $b[$field] ?? false );
			if ( $this->sortOrder === self::SORT_ORDER_ASCENDING ) {
				return $tsA->getTimestamp() <=> $tsB->getTimestamp();
			} elseif ( $this->sortOrder === self::SORT_ORDER_DESCENDING ) {
				return $tsB->getTimestamp() <=> $tsA->getTimestamp();
			} else {
				throw new LogicException( 'sortOrder is not valid' );
			}
		} );
	}

	/**
	 * Sort data using $field, interpreting it as a number
	 *
	 * This does NOT check if it actually is a number. Caller needs to
	 * do that.
	 *
	 * @param array &$data
	 * @param string $field
	 */
	private function doSortByNumber( array &$data, string $field ) {
		usort( $data, function ( $a, $b ) use ( $field ) {
			if ( $this->sortOrder === self::SORT_ORDER_ASCENDING ) {
				return $a[$field] <=> $b[$field];
			} elseif ( $this->sortOrder === self::SORT_ORDER_DESCENDING ) {
				return $b[$field] <=> $a[$field];
			} else {
				throw new LogicException( 'sortOrder is not valid' );
			}
		} );
	}

	/**
	 * Sort the data
	 *
	 * This function uses $this->sortBy to decide what to filter by,
	 * and calls doSortByTimestamp or doSortByNumber depending
	 * on the data type of the field.
	 */
	private function doSort( array &$data ) {
		if ( in_array( $this->sortBy, self::TIMESTAMP_SORTS ) ) {
			$this->doSortByTimestamp( $data, $this->sortBy );
		} else {
			$this->doSortByNumber( $data, $this->sortBy );
		}
	}

	/**
	 * How many rows would the filters return?
	 *
	 * This ignores offset and limit, and is useful
	 * for pagination purposes.
	 */
	public function getTotalRows(): int {
		if ( $this->totalRows !== null ) {
			return $this->totalRows;
		}
		if ( $this->filteredData === null ) {
			$this->filteredData = $this->filterInternal();
		}
		$this->totalRows = count( $this->filteredData );
		return $this->totalRows;
	}

	/**
	 * Apply filtering rules (but do not apply limit/offset)
	 *
	 * @return array
	 */
	private function filterInternal(): array {
		// Filter the data
		$filteredData = array_filter( $this->data, function ( $menteeData ) {
			if ( $this->prefix !== '' &&
				!str_starts_with( $menteeData['username'], $this->prefix )
			) {
				return false;
			}

			if ( $this->onlyIds !== null && !in_array( $menteeData['user_id'], $this->onlyIds ) ) {
				return false;
			}

			if ( $this->minEdits !== null && $menteeData['editcount'] < $this->minEdits ) {
				return false;
			}

			if ( $this->maxEdits !== null && $menteeData['editcount'] > $this->maxEdits ) {
				return false;
			}

			if ( $this->activeDaysAgo !== null && $menteeData['last_active'] !== null ) {
				$secondsSinceLastActivity = (int)wfTimestamp( TS_UNIX ) -
					(int)ConvertibleTimestamp::convert(
						TS_UNIX,
						$menteeData['last_active']
					);
				if ( $secondsSinceLastActivity >= self::SECONDS_DAY * $this->activeDaysAgo ) {
					return false;
				}
			}

			return true;
		} );

		// Sort the data
		$this->doSort( $filteredData );
		return $filteredData;
	}

	/**
	 * Do the filtering and return data
	 *
	 * @return array Filtered and sorted data
	 */
	public function filter(): array {
		if ( $this->filteredData === null ) {
			$this->filteredData = $this->filterInternal();
		}

		// Apply limit and offset
		return array_slice( $this->filteredData, $this->offset, $this->limit, true );
	}
}
