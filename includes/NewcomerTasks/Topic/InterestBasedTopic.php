<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\Topic;

use LogicException;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Language\RawMessage;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleValue;

/**
 * A topic based on a user interest: an article the user selected as standing for a subject
 * they want to edit about. Task suggestions come from a "more like this" search against the
 * interest article. Unlike other topics, interests are per-user and are not part of the
 * topic registry.
 */
class InterestBasedTopic extends Topic {

	/**
	 * @param string $id The prefixed text of the interest article title. Unlike other topic
	 *   IDs, this is not a lowercase-alphanumeric-and-dashes identifier.
	 * @param LinkTarget $title The interest article. This must be a page in the main
	 *   namespace, without fragment or interwiki: the morelikethis search term only uses
	 *   the DB key, and JSON serialization only keeps the namespace and the DB key.
	 */
	public function __construct(
		string $id,
		private readonly LinkTarget $title,
	) {
		parent::__construct( $id );
		if (
			$title->hasFragment() ||
			$title->isExternal() ||
			!$title->inNamespace( NS_MAIN )
		) {
			throw new LogicException(
				'InterestBasedTopic requires local title in ns 0 and no fragment but got ' . (string)$title
			);
		}
	}

	/**
	 * The article this interest stands for.
	 */
	public function getTitle(): LinkTarget {
		return $this->title;
	}

	/** @inheritDoc */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		return new RawMessage( '$1', [ $this->title->getText() ] );
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		return [
			'id' => $this->getId(),
			'title' => [ $this->title->getNamespace(), $this->title->getDBkey() ],
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		return new static( $json['id'], new TitleValue( $json['title'][0], $json['title'][1] ) );
	}

}
