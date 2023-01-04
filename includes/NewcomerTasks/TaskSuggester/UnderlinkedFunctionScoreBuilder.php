<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use CirrusSearch\Search\Rescore\BoostFunctionBuilder;
use Elastica\Query\FunctionScore;
use Elastica\Script\Script;

/**
 * A CirrusSearch rescore function which prioritizes underlinked articles and is otherwise random.
 * @see https://www.mediawiki.org/wiki/Extension:CirrusSearch/Scoring#Rescoring
 */
class UnderlinkedFunctionScoreBuilder implements BoostFunctionBuilder {

	/** Function type used in the rescore profile */
	public const TYPE = 'growth_underlinked';

	/** @var float */
	private $weight;

	/** @var int */
	private $minimumLength;

	/**
	 * @param float $weight Weight of the underlinkedness metric (vs. a random factor) in sorting.
	 * @param int $minimumLength Do not consider articles shorter than this underlinked.
	 */
	public function __construct( float $weight, int $minimumLength ) {
		$this->weight = $weight;
		$this->minimumLength = $minimumLength;
	}

	/**
	 * @inheritDoc
	 */
	public function append( FunctionScore $container ) {
		// For articles shorter than the minimum length, the underlinkedness score is 0.
		// Otherwise, it is the chance that a randomly picked word in the
		// article is not a link. (Approximately - doesn't take multi-word links into account.)
		// Since this is very close to 1, a power function is used to smooth it to the [0,1] range.
		// See https://phabricator.wikimedia.org/T317546#8246903 for why .length is used

		// Chosen arbitrarily because it gave nice values for a few sample articles.
		$smoothingFactor = 4;

		$script = /** @lang JavaScript */
<<<'SCRIPT'
		doc['text_bytes'] >= minimumLength
			? pow(
				max(
					0,
					1 - (
						doc['outgoing_link.token_count'].length
						/ max( 1, doc['text.word_count'] )
					)
				),
				smoothingFactor
			)
			: 0
SCRIPT;
		$script = trim( preg_replace( '/\s+/', ' ', $script ) );
		$params = [
			'minimumLength' => $this->minimumLength,
			'smoothingFactor' => $smoothingFactor,
		];
		$container->addScriptScoreFunction(
			new Script( $script, $params, Script::LANG_EXPRESSION ),
			null,
			$this->weight
		);
		// Mix with a random factor so everyone doesn't get the same list of articles.
		$container->addRandomScoreFunction(
			random_int( 1, PHP_INT_MAX ),
			null,
			1 - $this->weight,
			'_seq_no'
		);
		$container->setScoreMode( FunctionScore::SCORE_MODE_SUM );
	}

}
