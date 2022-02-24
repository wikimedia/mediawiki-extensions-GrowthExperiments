<?php

namespace GrowthExperiments\NewcomerTasks;

use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\WarningCollector;
use Message;

/**
 * Implementation of growtharticletopic: keyword which is similar to articletopic: but
 * uses custom tags which are managed manually.
 * FIXME This is a hack that will be removed once the feature is not needed; see T301030.
 */
class GrowthArticleTopicFeature extends ArticleTopicFeature {

	public const KEYWORD = 'growtharticletopic';

	public const TAG_PREFIX = 'classification.oneoff.T301028';

	/** @inheritDoc */
	public function parseValue(
		$key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector
	) {
		$topics = explode( '|', $value );
		$validTopics = array_filter( $topics, static function ( string $topic ) {
			return preg_match( '/^[a-zA-Z0-9-_]+$/', $topic );
		} );
		$invalidTopics = array_diff( $topics, $validTopics );

		if ( $invalidTopics ) {
			$warningCollector->addWarning( 'growthexperiments-homepage-suggestededits-articletopic-invalid-topic',
				Message::listParam( $invalidTopics, 'comma' ), count( $invalidTopics ) );
		}
		return [ 'topics' => $validTopics, 'tag_prefix' => self::TAG_PREFIX ];
	}

	/** @inheritDoc */
	public function getKeywords() {
		return [ self::KEYWORD ];
	}

}
