<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

/**
 * Helper class to search mentees by username prefix
 *
 * Used by search box of the mentor dashboard's mentee overview module
 * (as part of autocomplete).
 */
class MenteeOverviewPrefixSearchDataFilter {
	/** @var array */
	private $data;

	/** @var string */
	private $prefix = '';

	/** @var int */
	private $limit = 10;

	/**
	 * @param array $data Data to filter (must come from MenteeOverviewDataProvider)
	 */
	public function __construct(
		array $data
	) {
		$this->data = $data;
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
	 * Limit number of usernames returned
	 *
	 * @param int $limit
	 * @return static
	 */
	public function limit( int $limit ) {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Get all usernames matching given prefix
	 *
	 * @return string[]
	 */
	public function getUsernames(): array {
		$usernames = array_column( $this->data, 'username' );

		$prefixLen = strlen( $this->prefix );
		// filtering only makes sense with prefixes longer than 0 characters
		if ( $prefixLen !== 0 ) {
			$usernames = array_filter( $usernames, function ( $username ) use ( $prefixLen ) {
				return substr( $username, 0, $prefixLen ) === $this->prefix;
			} );
		}

		// Sort the data
		sort( $usernames );

		return array_slice( $usernames, 0, $this->limit, true );
	}
}
