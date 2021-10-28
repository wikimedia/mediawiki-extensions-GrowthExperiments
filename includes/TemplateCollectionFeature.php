<?php

namespace GrowthExperiments;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Query\FilterQueryFeature;
use CirrusSearch\Query\QueryHelper;
use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use Elastica\Query\AbstractQuery;
use TitleFactory;
use Wikimedia\Assert\Assert;

/**
 * Like HasTemplateFeature, but operates on a collection of templates from a pre-defined list
 * instead of directly via user input. The search is case-sensitive, so the templates in the
 * pre-defined list should be also. HasTemplateFeature isn't extended directly to avoid strong
 * coupling.
 */
class TemplateCollectionFeature extends SimpleKeywordFeature implements FilterQueryFeature {

	/** @var array */
	private $templates;
	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'hastemplatecollection' ];
	}

	/**
	 * @param string $collectionName
	 * @param string[] $templates
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( string $collectionName, array $templates, TitleFactory $titleFactory ) {
		Assert::parameter(
			count( $templates ) <= 800,
			'$templates',
			'Maximum number of templates allowed in collection.'
		);
		$this->templates[$collectionName] = $templates;
		$this->titleFactory = $titleFactory;
	}

	/** @inheritDoc */
	public function parseValue(
		$key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector
	) {
		// If an undefined collection name is used in the user-provided input, then just return no results.
		if ( !isset( $this->templates[$value] ) ) {
			return [ 'templates' => [ '' ] ];
		}
		$templates = [];
		foreach ( $this->templates[$value] as $template ) {
			$title = $this->titleFactory->newFromText( $template, NS_TEMPLATE );
			$templates[] = $title->getPrefixedText();
		}
		return [ 'templates' => $templates, 'case_sensitive' => true ];
	}

	/**
	 * Applies the detected keyword from the search term. May apply changes
	 * either to $context directly, or return a filter to be added.
	 *
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped and escaped
	 *  quotes un-escaped.
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$filter = $this->doGetFilterQuery(
			$this->parseValue( $key, $value, $quotedValue, '', '', $context ) );
		return [ $filter, false ];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return $this->doGetFilterQuery( $node->getParsedValue() );
	}

	/**
	 * @param string[][] $parsedValue
	 * @return AbstractQuery
	 */
	protected function doGetFilterQuery( array $parsedValue ) {
		return Filters::booleanOr( array_map(
			static function ( $v )  {
				return QueryHelper::matchPage( 'template.keyword', $v );
			},
			$parsedValue['templates']
		) );
	}

}
