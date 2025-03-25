<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use MediaWiki\Json\JsonDeserializer;
use MediaWiki\Message\Message;
use MessageLocalizer;

/**
 * A topic used for a specific editing campaign. Uses a separate namespace for ID and message keys,
 * and an arbitrary search expression (which could be manually configured by e.g. event organizers).
 */
class CampaignTopic extends Topic {

	/** @var string */
	private $searchExpression;

	/**
	 * @param string $id Topic ID, a string consisting of lowercase alphanumeric characters
	 *   and dashes. E.g. 'biology'. Will be prefixed by 'campaign-' to avoid conflicts with
	 *   regular topics.
	 * @param string $searchExpression The search expression which selects articles belonging
	 *   to this topic. E.g. 'biology' or 'hastemplate:Taxobox'.
	 */
	public function __construct( string $id, string $searchExpression ) {
		parent::__construct( $id, 'campaign' );
		$this->searchExpression = $searchExpression;
	}

	/**
	 * The search expression which selects articles belonging to this topic.
	 */
	public function getSearchExpression(): string {
		return $this->searchExpression;
	}

	/** @inheritDoc */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		// These topic names are defined on-wiki, not in the software.
		return $messageLocalizer->msg( 'growth-campaign-topic-name-' . $this->getId() );
	}

	/** @inheritDoc */
	public function getGroupName( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-topic-group-name-campaign' );
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return [
			'id' => $this->getId(),
			'searchExpression' => $this->getSearchExpression(),
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonDeserializer $deserializer, array $json ) {
		return new self( $json['id'], $json['searchExpression'] );
	}

}
