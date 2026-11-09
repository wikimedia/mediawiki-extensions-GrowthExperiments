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
use Closure;
use Elastica\Query\AbstractQuery;
use GrowthExperiments\Config\Schemas\SuggestedEditsSchema;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use Wikimedia\Assert\Assert;

/**
 * Like HasTemplateFeature, but operates on a collection of templates from a pre-defined list
 * instead of directly via user input. The search is case-sensitive, so the templates in the
 * pre-defined list should be also. HasTemplateFeature isn't extended directly to avoid strong
 * coupling.
 */
class TemplateCollectionFeature extends SimpleKeywordFeature implements FilterQueryFeature {

	/**
	 * ElasticSearch doesn't allow more than 1024 clauses in a boolean query. Limit at 800
	 * to leave some space to combine hastemplatecollection with other search terms.
	 */
	public const MAX_TEMPLATES_IN_COLLECTION = SuggestedEditsSchema::MAX_INFOBOX_TEMPLATES;

	private Closure $collectionsCallback;
	/** @var array<string,string[]|LinkTarget[]>|null */
	private ?array $templates = null;
	private TitleFactory $titleFactory;

	/** @inheritDoc */
	protected function getKeywords() {
		return [ 'hastemplatecollection' ];
	}

	/**
	 * @param Closure():(array<string,string[]|LinkTarget[]>) $collectionsCallback Returns the
	 *   template collections, keyed by collection name. It is called once, when a query uses
	 *   the keyword.
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( Closure $collectionsCallback, TitleFactory $titleFactory ) {
		$this->collectionsCallback = $collectionsCallback;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return array<string,string[]|LinkTarget[]>
	 */
	private function getCollections(): array {
		if ( $this->templates === null ) {
			$collections = ( $this->collectionsCallback )();
			foreach ( $collections as $collectionName => $templates ) {
				Assert::postcondition(
					count( $templates ) <= self::MAX_TEMPLATES_IN_COLLECTION,
					'Maximum ' . self::MAX_TEMPLATES_IN_COLLECTION .
						" templates allowed in collection \"$collectionName\"."
				);
			}
			$this->templates = $collections;
		}
		return $this->templates;
	}

	/** @inheritDoc */
	public function parseValue(
		$key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector
	) {
		$collections = $this->getCollections();
		// If an undefined collection name is used in the user-provided input, then just return no results.
		if ( !isset( $collections[$value] ) ) {
			$warningCollector->addWarning( 'growthexperiments-templatecollectionfeature-invalid-collection',
				$value );
			return [ 'templates' => [] ];
		}
		$templates = [];
		foreach ( $collections[$value] as $template ) {
			if ( $template instanceof TitleValue ) {
				$title = $this->titleFactory->newFromLinkTarget( $template );
			} else {
				$title = $this->titleFactory->newFromText( $template );
			}
			$templates[] = $title->getPrefixedText();
		}
		return [ 'templates' => $templates ];
	}

	/** @inheritDoc */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$filter = $this->doGetFilterQuery(
			$this->parseValue( $key, $value, $quotedValue, '', '', $context ) );
		if ( $filter === null && !$negated ) {
			// If there are no templates in a collection, it shouldn't match anything. If the
			// keyword is negated, it should be a no-op, so returning null works.
			$context->setResultsPossible( false );
		}
		return [ $filter, false ];
	}

	/** @inheritDoc */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		// TODO handle the null case once CirrusSearch starts using this method.
		return $this->doGetFilterQuery( $node->getParsedValue() );
	}

	/**
	 * @param string[][] $parsedValue
	 * @return AbstractQuery|null
	 */
	protected function doGetFilterQuery( array $parsedValue ) {
		return Filters::booleanOr( array_map(
			static function ( $v )  {
				return QueryHelper::matchPage( 'template.keyword', $v );
			},
			$parsedValue['templates']
		), false );
	}

}
