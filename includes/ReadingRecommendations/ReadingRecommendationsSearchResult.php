<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Linker\LinkTarget;

/**
 * The outcome of a reading recommendations search.
 *
 * An empty successful result is distinct from an error so callers can choose
 * an appropriate cache lifetime without guessing from the number of titles.
 *
 * @internal
 */
class ReadingRecommendationsSearchResult {

	/**
	 * @param LinkTarget[] $titles
	 */
	private function __construct(
		private readonly array $titles,
		private readonly bool $error
	) {
	}

	/**
	 * @param LinkTarget[] $titles
	 */
	public static function newSuccess( array $titles ): self {
		return new self( $titles, false );
	}

	public static function newError(): self {
		return new self( [], true );
	}

	/**
	 * @return LinkTarget[]
	 */
	public function getTitles(): array {
		return $this->titles;
	}

	public function isError(): bool {
		return $this->error;
	}
}
