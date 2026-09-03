<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;

/**
 * Tells apart the interests that can get suggestions from the ones that cannot.
 *
 * Interests come from a user preference and from the API, so each entry can be any string.
 * Every caller that reads interests must remove the invalid ones, so that the code below
 * the entry points only sees interests which can get suggestions.
 */
class InterestValidator {

	public function __construct(
		private readonly TitleParser $titleParser,
	) {
	}

	/**
	 * Tell apart the interests that can get suggestions from the ones that cannot.
	 * @param string[] $interests Prefixed article titles.
	 * @return array{valid:string[],invalid:string[]} Both lists keep the given order.
	 */
	public function validate( array $interests ): array {
		$result = [ 'valid' => [], 'invalid' => [] ];
		foreach ( $interests as $interest ) {
			$result[$this->isValid( $interest ) ? 'valid' : 'invalid'][] = $interest;
		}
		return $result;
	}

	private function isValid( string $interest ): bool {
		try {
			$title = $this->titleParser->parseTitle( $interest );
		} catch ( MalformedTitleException ) {
			return false;
		}
		return InterestBasedTopic::isValidTitle( $title );
	}

}
