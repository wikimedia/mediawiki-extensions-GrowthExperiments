<?php

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleValue;

/**
 * Recommended Article in the ReadingRecommendations module.
 *
 * The interest is the user's interest article the recommendation was found from.
 * It is null for a general recommendation, such as one taken from the Featured article pool.
 */
class ReadingRecommendation {

	public function __construct(
		private readonly LinkTarget $title,
		private readonly ?LinkTarget $interest = null,
	) {
	}

	public function getTitle(): LinkTarget {
		return $this->title;
	}

	public function getInterest(): ?LinkTarget {
		return $this->interest;
	}

	public function isGeneral(): bool {
		return $this->interest === null;
	}

	/**
	 * Plain array form for storing in the cache.
	 * @return array{title: array{ns: int, dbkey: string}, interest: ?array{ns: int, dbkey: string}}
	 */
	public function toArray(): array {
		return [
			'title' => self::linkTargetToArray( $this->title ),
			'interest' => $this->interest ? self::linkTargetToArray( $this->interest ) : null,
		];
	}

	/**
	 * @param array{title: array{ns: int, dbkey: string}, interest: ?array{ns: int, dbkey: string}} $data
	 */
	public static function fromArray( array $data ): self {
		return new self(
			self::linkTargetFromArray( $data['title'] ),
			isset( $data['interest'] ) ? self::linkTargetFromArray( $data['interest'] ) : null,
		);
	}

	/**
	 * @return array{ns: int, dbkey: string}
	 */
	private static function linkTargetToArray( LinkTarget $target ): array {
		return [
			'ns' => $target->getNamespace(),
			'dbkey' => $target->getDBkey(),
		];
	}

	/**
	 * @param array{ns: int, dbkey: string} $data
	 */
	private static function linkTargetFromArray( array $data ): LinkTarget {
		return new TitleValue( $data['ns'], $data['dbkey'] );
	}
}
