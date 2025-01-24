<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use InvalidArgumentException;
use MediaWiki\Content\JsonContent;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use StatusValue;
use Wikimedia\Assert\Assert;

/**
 * Base class for recommendation providers for testing purposes. Looks for a `/<type>.json`
 * subpage of the target page, and returns the contents of that page as link data. `<type>`
 * is a keyword that's different for each recommendation type.
 *
 * Enable the subclass(es) you want by adding the following to LocalSettings.php or a similar place:
 *     $class = <subpage recommendation provider subclass>;
 *     $wgHooks['MediaWikiServices'][] = "$class::onMediaWikiServices";
 *     $wgHooks['ContentHandlerDefaultModelFor'][] = "$class::onContentHandlerDefaultModelFor";
 */
abstract class SubpageRecommendationProvider implements RecommendationProvider {

	/** @var string The name to use for subpages (without .json). Subclasses must provide this. */
	protected static $subpageName = null;

	/**
	 * @var string The MediaWiki service to replace, which returns the provider object.
	 *   Subclasses must provide this.
	 */
	protected static $serviceName = null;

	/** @var string|array Name of the Recommendation subclass(es). Subclasses of this class must provide this. */
	protected static $recommendationTaskTypeClass = null;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var RecommendationProvider */
	private $fallbackRecommendationProvider;

	/**
	 * Create the recommendation object from the JSON representation.
	 * @param Title $title The page the recommendation is for.
	 * @param TaskType $taskType
	 * @param array $data Recommendation data (ie. the content of the JSON subpage).
	 * @param array $suggestionFilters
	 * @return Recommendation|StatusValue
	 */
	abstract public function createRecommendation(
		Title $title,
		TaskType $taskType,
		array $data,
		array $suggestionFilters = []
	);

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		RecommendationProvider $fallbackRecommendationProvider
	) {
		Assert::precondition( static::$subpageName !== null,
			'subclasses must override $subpageName' );
		$this->wikiPageFactory = $wikiPageFactory;
		$this->fallbackRecommendationProvider = $fallbackRecommendationProvider;
	}

	/** @inheritDoc */
	public function get( LinkTarget $title, TaskType $taskType ) {
		Assert::parameterType( static::$recommendationTaskTypeClass, $taskType, '$taskType' );

		$subpageName = static::$subpageName;
		$subpageTitleText = $title->getDBkey() . "/$subpageName.json";
		$subpageTitle = new TitleValue( $title->getNamespace(), $subpageTitleText );
		try {
			$subpage = $this->wikiPageFactory->newFromLinkTarget( $subpageTitle );
		} catch ( InvalidArgumentException $e ) {
			// happens for nonsensical namespaces, like Media:
			return StatusValue::newFatal( 'rawmessage', $e->getMessage() );
		}

		if ( !$subpage->exists() ) {
			if ( $this->fallbackRecommendationProvider ) {
				return $this->fallbackRecommendationProvider->get( $title, $taskType );
			} else {
				// This is a development-only provider, no point in translating its messages.
				return StatusValue::newFatal( 'rawmessage', "No /$subpageName.json subpage found" );
			}
		}

		$content = $subpage->getContent();
		if ( !$content instanceof JsonContent ) {
			return StatusValue::newFatal( 'rawmessage',
				"/$subpageName.json subpage is not a JSON page." );
		}
		$dataStatus = FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC );
		if ( !$dataStatus->isOK() ) {
			return $dataStatus;
		}
		$data = $dataStatus->getValue();

		// Turn $title into a real Title
		$title = $this->wikiPageFactory->newFromLinkTarget( $title )->getTitle();

		return $this->createRecommendation( $title, $taskType, $data, $taskType->getSuggestionFilters() );
	}

	/**
	 * MediaWikiServices hook handler, for development setups only.
	 * @param MediaWikiServices $services
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/MediaWikiServices
	 */
	public static function onMediaWikiServices( MediaWikiServices $services ) {
		Assert::precondition( static::$serviceName !== null,
			'subclasses must override $serviceName' );
		$services->addServiceManipulator( static::$serviceName,
			static function (
				RecommendationProvider $recommendationProvider,
				MediaWikiServices $services
			) {
				return new static( $services->getWikiPageFactory(), $recommendationProvider );
			} );
	}

	/**
	 * ContentHandlerDefaultModelFor hook handler, for development setups only.
	 * @param Title $title
	 * @param string &$model
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ContentHandlerDefaultModelFor
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, &$model ) {
		// This is for development, so we want to ignore $wgNamespacesWithSubpages.
		$titleText = $title->getText();
		$titleParts = explode( '/', $titleText );
		$subpage = end( $titleParts );
		if ( $subpage === static::$subpageName . '.json' && $subpage !== $titleText ) {
			$model = CONTENT_MODEL_JSON;
		}
	}

}
