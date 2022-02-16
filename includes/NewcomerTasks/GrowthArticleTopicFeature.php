<?php

namespace GrowthExperiments\NewcomerTasks;

use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\WarningCollector;
use Message;

/**
 * Customized articletopic: search query that recignizes an extra keyword ('argentina').
 * FIXME This is a hack that will be removed once the feature is not needed; see T301030.
 */
class GrowthArticleTopicFeature extends ArticleTopicFeature {

	public const KEYWORD = 'growtharticletopic';

	public const TAG_PREFIX = 'classification.oneoff.T301028';

	/** @override */
	public const TERMS_TO_LABELS = [
		'argentina' => 'Geography.Countries.Argentina',
	];

	/** @inheritDoc */
	public function parseValue(
		$key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector
	) {
		$topics = explode( '|', $value );
		$invalidTopics = array_diff( $topics, array_keys( self::TERMS_TO_LABELS ) );
		$validTopics = array_values( array_filter( array_map( static function ( $topic ) {
			return self::TERMS_TO_LABELS[$topic];
		}, array_diff( $topics, $invalidTopics ) ) ) );

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
