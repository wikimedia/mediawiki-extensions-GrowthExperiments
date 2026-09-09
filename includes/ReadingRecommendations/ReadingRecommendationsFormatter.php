<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Search\Entity\SearchResultThumbnail;
use MediaWiki\Search\SearchResultThumbnailProvider;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use Wikibase\Client\Store\DescriptionLookup;

/**
 * Formats reading recommendations for export to the client.
 */
class ReadingRecommendationsFormatter {

	public const THUMBNAIL_SIZE = 160;

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly SearchResultThumbnailProvider $thumbnailProvider,
		private readonly TitleFormatter $titleFormatter,
		private readonly ?DescriptionLookup $descriptionLookup
	) {
	}

	/**
	 * @param ReadingRecommendation[] $recommendations
	 * @return array[] JSON-serializable items:
	 *   title, description (string|null), thumbnail ({url, width, height}|null),
	 *   relatedTo (interest title, null for a general recommendation),
	 *   url, pageId. The attribution parameter on the URL comes with T436317.
	 */
	public function format( array $recommendations ): array {
		if ( !$recommendations ) {
			return [];
		}

		$titles = array_map(
			fn ( ReadingRecommendation $recommendation ) =>
				$this->titleFactory->newFromLinkTarget( $recommendation->getTitle() ),
			$recommendations
		);
		$linkBatch = $this->linkBatchFactory->newLinkBatch( $titles );
		$linkBatch->setCaller( __METHOD__ );
		$linkBatch->execute();

		$existingByPageId = [];
		foreach ( $titles as $i => $title ) {
			if ( $title->exists() ) {
				$existingByPageId[$title->getArticleID()] = [ $recommendations[$i], $title ];
			}
		}

		// TODO: If a page does not exist (e.g. deleted), then the user would see
		// fewer recommendations than the expected 4.
		if ( !$existingByPageId ) {
			return [];
		}

		$pages = array_map( static fn ( array $pair ) => $pair[1], $existingByPageId );
		$thumbnails = $this->thumbnailProvider->getThumbnails( $pages, self::THUMBNAIL_SIZE );

		// Local first, so a local short description wins over the central one.
		$descriptions = $this->descriptionLookup ? $this->descriptionLookup->getDescriptions(
			array_values( $pages ),
			[ DescriptionLookup::SOURCE_LOCAL, DescriptionLookup::SOURCE_CENTRAL ]
		) : [];

		$items = [];
		foreach ( $existingByPageId as $pageId => [ $recommendation, $title ] ) {
			/** @var Title $title */
			$interest = $recommendation->getInterest();
			$items[] = [
				'title' => $this->titleFormatter->getPrefixedText( $recommendation->getTitle() ),
				'description' => $descriptions[$pageId] ?? null,
				'thumbnail' => $this->formatThumbnail( $thumbnails[$pageId] ?? null ),
				'relatedTo' => $interest !== null
					? $this->titleFormatter->getPrefixedText( $interest )
					: null,
				'url' => $title->getLinkURL(),
				'pageId' => $pageId,
			];
		}
		return $items;
	}

	/**
	 * @param SearchResultThumbnail|null $thumbnail
	 * @return array{url: string, width: int, height: int}|null
	 */
	private function formatThumbnail( ?SearchResultThumbnail $thumbnail ): ?array {
		// Core has no equivalent of Impact's guard against files that transform
		// to a degenerate thumbnail, like PDF page images (T429314), so filter
		// non-images and zero-sized thumbnails here.
		if ( !$thumbnail ) {
			return null;
		}
		$width = $thumbnail->getWidth();
		$height = $thumbnail->getHeight();
		if ( $width === null || $width <= 0
			|| $height === null || $height <= 0
			|| !str_starts_with( $thumbnail->getMimeType(), 'image/' )
		) {
			return null;
		}
		return [
			'url' => $thumbnail->getUrl(),
			'width' => $width,
			'height' => $height,
		];
	}
}
