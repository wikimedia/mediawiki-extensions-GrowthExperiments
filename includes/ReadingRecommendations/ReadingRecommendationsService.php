<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;

/**
 * Computes a user's daily reading recommendations.
 *
 * For now every user gets the same general recommendations: the first SLOTS
 * articles of the day's Featured pool. The pool is one seeded random search
 * per wiki per day, so the list changes at local midnight and is identical
 * for everyone on the wiki. Interest-based recommendations come with T435522.
 */
class ReadingRecommendationsService {

	public const SLOTS = 4;

	public const CONSTRUCTOR_OPTIONS = WikiDay::CONSTRUCTOR_OPTIONS;

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly FeaturedArticlePool $featuredArticlePool
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * The recommendations for today: up to SLOTS items.
	 *
	 * @param UserIdentity $user
	 * @return ReadingRecommendation[]
	 */
	public function getRecommendations( UserIdentity $user ): array {
		$day = WikiDay::today( $this->options );
		$pool = $this->featuredArticlePool->getPool( $day )->getTitles();
		return array_map(
			static fn ( LinkTarget $title ) => new ReadingRecommendation( $title ),
			array_slice( $pool, 0, self::SLOTS )
		);
	}
}
