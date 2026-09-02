<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsFormatter;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsService;
use JsonException;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use UnexpectedValueException;
use Wikimedia\Minify\CSSMin;

/**
 * The reading recommendations module, which will show article recommendations
 * based on the user's interests.
 *
 * The recommendations come from ReadingRecommendationsService and are put
 * into the export shape by ReadingRecommendationsFormatter. They are exported
 * through getJsData() for the Vue app and rendered as a plain list inside the
 * mount div, so they show without JavaScript and before the app mounts.
 *
 * A fixture file named by GEReadingRecommendationsFixtureFile replaces the
 * service output when GEDeveloperSetup is also enabled; see
 * docs/ReadingRecommendations.md.
 */
class ReadingRecommendations extends BaseModule {

	public const MODULE_ID = 'reading-recommendations';

	private ReadingRecommendationsService $recommendationsService;
	private ReadingRecommendationsFormatter $formatter;
	private ?array $recommendations = null;

	/**
	 * No details view: the mobile tile shows the same list as desktop.
	 * @var string[]
	 */
	protected static $supportedModes = [
		self::RENDER_DESKTOP,
		self::RENDER_MOBILE_SUMMARY,
	];

	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ReadingRecommendationsService $recommendationsService,
		ReadingRecommendationsFormatter $formatter
	) {
		parent::__construct( self::MODULE_ID, $context, $wikiConfig );
		$this->recommendationsService = $recommendationsService;
		$this->formatter = $formatter;
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		if ( !$this->supports( $mode ) ) {
			return [];
		}
		// There is no details view, so skip BaseModule's overlay pre-render in
		// mobile summary mode; it would render the list a second time.
		return [
			'recommendations' => $this->getRecommendations(),
			'renderMode' => $mode,
		];
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->getContext()->msg(
			'growthexperiments-homepage-reading-recommendations-header'
		)->text();
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'articles';
	}

	/** @inheritDoc */
	protected function getMobileSummaryHeader() {
		// Title only, without the arrow that would point to a details view.
		return $this->getHeaderTextElement();
	}

	/** @inheritDoc */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[
				'oojs-ui.styles.icons-content',
				'ext.growthExperiments.Homepage.ReadingRecommendations.styles',
			]
		);
	}

	/** @inheritDoc */
	protected function getBody() {
		// The div becomes the mount point for the Vue app.
		return Html::rawElement(
			'div',
			[ 'id' => 'reading-recommendations-vue-root' ],
			$this->getListHtml() ?: (
				Html::element(
					'h3',
					[],
					$this->getContext()->msg(
						'growthexperiments-homepage-reading-recommendations-personalize-title'
					)->text()
				) .
				Html::element(
					'p',
					[],
					$this->getContext()->msg(
						'growthexperiments-homepage-reading-recommendations-personalize-text'
					)->text()
				)
			)
		);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		// The div becomes the mount point for the Vue app on the mobile summary
		// tile. The id differs from the desktop one on purpose.
		return Html::rawElement(
			'div',
			[ 'id' => 'reading-recommendations-vue-root--mobile' ],
			$this->getListHtml() ?: Html::element(
				'p',
				[ 'class' => 'growthexperiments-homepage-module-text-light' ],
				$this->getContext()->msg(
					'growthexperiments-homepage-reading-recommendations-personalize-text'
				)->text()
			)
		);
	}

	/**
	 * The recommendations as a plain list, or an empty string when there are none.
	 */
	private function getListHtml(): string {
		$recommendations = $this->getRecommendations();
		if ( !$recommendations ) {
			return '';
		}
		$itemsHtml = '';
		foreach ( $recommendations as $item ) {
			$itemsHtml .= $this->getListItemHtml( $item );
		}
		return Html::rawElement(
			'ul',
			[ 'class' => 'growthexperiments-reading-recommendations-list' ],
			$itemsHtml
		);
	}

	/**
	 * One recommendation as a Codex CSS-only link card, the same classes the
	 * Vue CdxCard renders, so the page does not shift when the app mounts.
	 */
	private function getListItemHtml( array $item ): string {
		if ( $item['thumbnail'] ) {
			// CSSMin quotes and escapes the URL, so it cannot close url() and append
			// further declarations. CdxThumbnail escapes the same characters.
			$thumbnailContent = Html::element( 'span', [
				'class' => 'cdx-thumbnail__image',
				'style' => 'background-image: ' . CSSMin::buildUrlValue( $item['thumbnail']['url'] ) . ';',
			] );
		} else {
			$thumbnailContent = Html::rawElement(
				'span',
				[ 'class' => 'cdx-thumbnail__placeholder' ],
				Html::element( 'span', [ 'class' => 'cdx-thumbnail__placeholder__icon' ] )
			);
		}
		$text = Html::element(
			'span',
			[ 'class' => 'cdx-card__text__title' ],
			$item['title']
		);
		if ( $item['description'] !== null ) {
			$text .= Html::element(
				'span',
				[ 'class' => 'cdx-card__text__description' ],
				$item['description']
			);
		}
		if ( $item['relatedTo'] !== null ) {
			$text .= Html::element(
				'span',
				[ 'class' => 'cdx-card__text__supporting-text' ],
				$this->getContext()->msg(
					'growthexperiments-homepage-reading-recommendations-related-to'
				)->params( $item['relatedTo'] )->text()
			);
		}
		$card = Html::rawElement(
			'a',
			[ 'class' => 'cdx-card cdx-card--is-link', 'href' => $item['url'] ],
			Html::rawElement(
				'span',
				[ 'class' => 'cdx-thumbnail cdx-card__thumbnail' ],
				$thumbnailContent
			) .
			Html::rawElement( 'span', [ 'class' => 'cdx-card__text' ], $text )
		);
		return Html::rawElement(
			'li',
			[ 'class' => 'growthexperiments-reading-recommendations-list-item' ],
			$card
		);
	}

	/**
	 * The recommendations to show, as the rows the Vue app receives.
	 *
	 * @return array[]
	 */
	private function getRecommendations(): array {
		$this->recommendations ??= $this->loadFixture() ?: $this->formatter->format(
			$this->recommendationsService->getRecommendations( $this->getContext()->getUser() )
		);
		return $this->recommendations;
	}

	/**
	 * The rows from the developer fixture file, or an empty list when unavailable or invalid.
	 *
	 * @return array[]
	 */
	private function loadFixture(): array {
		$config = $this->getContext()->getConfig();
		$path = $config->get( 'GEReadingRecommendationsFixtureFile' );
		if ( !$path || !$config->get( 'GEDeveloperSetup' ) || !is_readable( $path ) ) {
			return [];
		}
		try {
			$json = file_get_contents( $path );
			if ( $json === false ) {
				throw new UnexpectedValueException( 'Could not read the fixture file.' );
			}
			$items = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
			if ( !is_array( $items ) || !array_is_list( $items ) ) {
				throw new UnexpectedValueException( 'Expected a list of recommendations.' );
			}
			foreach ( $items as $index => $item ) {
				if ( !is_array( $item ) ||
					!is_string( $item['title'] ?? null ) ||
					!is_string( $item['url'] ?? null ) ||
					!is_int( $item['pageId'] ?? null )
				) {
					throw new UnexpectedValueException(
						"Recommendation $index requires a string title and url, and an integer pageId."
					);
				}
				$item += [ 'description' => null, 'thumbnail' => null, 'relatedTo' => null ];
				foreach ( [ 'description', 'relatedTo' ] as $field ) {
					if ( $item[$field] !== null && !is_string( $item[$field] ) ) {
						throw new UnexpectedValueException( "Recommendation $index: $field must be a string or null." );
					}
				}
				$thumbnail = $item['thumbnail'];
				if ( $thumbnail !== null && (
					!is_array( $thumbnail ) ||
					!is_string( $thumbnail['url'] ?? null ) ||
					!is_int( $thumbnail['width'] ?? null ) ||
					!is_int( $thumbnail['height'] ?? null )
				) ) {
					throw new UnexpectedValueException(
						"Recommendation $index: thumbnail requires a string url and integer width and height."
					);
				}
				$items[$index] = $item;
			}
			return $items;
		} catch ( JsonException | UnexpectedValueException $e ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				'Unable to load reading recommendations fixture {path}: {error}',
				[ 'path' => $path, 'error' => $e->getMessage() ]
			);
			return [];
		}
	}
}
