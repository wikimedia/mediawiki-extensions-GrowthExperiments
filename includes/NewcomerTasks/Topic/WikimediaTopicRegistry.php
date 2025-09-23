<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use MediaWiki\Collation\CollationFactory;
use MediaWiki\Extension\WikimediaMessages\ArticleTopicFiltersRegistry;
use MessageLocalizer;

class WikimediaTopicRegistry implements ITopicRegistry {

	public const GROWTH_ORES_TOPIC_GROUPS = [
		"culture",
		"history-and-society",
		"science-technology-and-math",
		"geography",
	];
	public const GROWTH_ORES_TOPICS = [
		"africa" => [
			"group" => "geography",
			"oresTopics" => [
				"africa",
				"central-africa",
				"eastern-africa",
				"northern-africa",
				"southern-africa",
				"western-africa",
			],
		],
		"architecture" => [
			"group" => "culture",
			"oresTopics" => [ "architecture" ],
		],
		"art" => [ "group" => "culture", "oresTopics" => [ "visual-arts" ] ],
		"asia" => [
			"group" => "geography",
			"oresTopics" => [
				"asia",
				"central-asia",
				"east-asia",
				"south-asia",
				"southeast-asia",
				"west-asia",
			],
		],
		"biography" => [
			"group" => "history-and-society",
			"oresTopics" => [ "biography" ],
		],
		"biology" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "biology" ],
		],
		"business-and-economics" => [
			"group" => "history-and-society",
			"oresTopics" => [ "business-and-economics" ],
		],
		"central-america" => [
			"group" => "geography",
			"oresTopics" => [ "central-america" ],
		],
		"chemistry" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "chemistry" ],
		],
		"comics-and-anime" => [
			"group" => "culture",
			"oresTopics" => [ "comics-and-anime" ],
		],
		"computers-and-internet" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "internet-culture", "software", "computing" ],
		],
		"earth-and-environment" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "geographical", "earth-and-environment" ],
		],
		"education" => [
			"group" => "history-and-society",
			"oresTopics" => [ "education" ],
		],
		"engineering" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "engineering" ],
		],
		"entertainment" => [
			"group" => "culture",
			"oresTopics" => [ "entertainment", "radio" ],
		],
		"europe" => [
			"group" => "geography",
			"oresTopics" => [
				"north-asia",
				"eastern-europe",
				"europe",
				"northern-europe",
				"southern-europe",
				"western-europe",
			],
		],
		"fashion" => [ "group" => "culture", "oresTopics" => [ "fashion" ] ],
		"food-and-drink" => [
			"group" => "history-and-society",
			"oresTopics" => [ "food-and-drink" ],
		],
		"general-science" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "stem" ],
		],
		"history" => [
			"group" => "history-and-society",
			"oresTopics" => [ "history" ],
		],
		"literature" => [
			"group" => "culture",
			"oresTopics" => [ "literature", "books" ],
		],
		"mathematics" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "mathematics" ],
		],
		"medicine-and-health" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "medicine-and-health" ],
		],
		"military-and-warfare" => [
			"group" => "history-and-society",
			"oresTopics" => [ "military-and-warfare" ],
		],
		"music" => [ "group" => "culture", "oresTopics" => [ "music" ] ],
		"north-america" => [
			"group" => "geography",
			"oresTopics" => [ "north-america" ],
		],
		"oceania" => [ "group" => "geography", "oresTopics" => [ "oceania" ] ],
		"performing-arts" => [
			"group" => "culture",
			"oresTopics" => [ "performing-arts" ],
		],
		"philosophy-and-religion" => [
			"group" => "history-and-society",
			"oresTopics" => [ "philosophy-and-religion" ],
		],
		"physics" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "physics", "space" ],
		],
		"politics-and-government" => [
			"group" => "history-and-society",
			"oresTopics" => [ "politics-and-government" ],
		],
		"society" => [
			"group" => "history-and-society",
			"oresTopics" => [ "society" ],
		],
		"south-america" => [
			"group" => "geography",
			"oresTopics" => [ "south-america" ],
		],
		"sports" => [ "group" => "culture", "oresTopics" => [ "sports" ] ],
		"technology" => [
			"group" => "science-technology-and-math",
			"oresTopics" => [ "technology" ],
		],
		"transportation" => [
			"group" => "history-and-society",
			"oresTopics" => [ "transportation" ],
		],
		"tv-and-film" => [
			"group" => "culture",
			"oresTopics" => [ "films", "television" ],
		],
		"video-games" => [
			"group" => "culture",
			"oresTopics" => [ "video-games" ],
		],
		"women" => [
			"group" => "history-and-society",
			"oresTopics" => [ "women" ],
		],
	];
	/** @var Topic[]|null */
	private ?array $topics;

	/** @var ?callable */
	private $campaignConfigCallback;
	private CollationFactory $collationFactory;
	private MessageLocalizer $messageLocalizer;

	public function __construct(
		MessageLocalizer $messageLocalizer,
		CollationFactory $collationFactory
	) {
		$this->topics = null;
		$this->collationFactory = $collationFactory;
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Filter out topics retrieved from ArticleTopicFiltersRegistry that
	 * are not found in the Growth's internal collection self::GROWTH_ORES_TOPICS
	 * @return string[] An array of allowed topic IDs
	 */
	private function getAllowedTopics(): array {
		return array_filter( $this->getAllTopics(), static function ( string $topicId ) {
			return array_key_exists( $topicId, self::GROWTH_ORES_TOPICS );
		} );
	}

	/**
	 * Get article ORES topics from ArticleTopicFiltersRegistry. In a public
	 * method to facilitate its mocking in tests. Consider injecting ArticleTopicFiltersRegistry
	 * instead when a service is available for it.
	 *
	 * @return string[] An array of topic IDs
	 */
	public function getAllTopics(): array {
		return ArticleTopicFiltersRegistry::getTopicList();
	}

	/** @inheritDoc */
	public function getTopics(): array {
		if ( $this->topics !== null ) {
			return $this->topics;
		}
		$topics = array_map( static function ( string $topicId ) {
			$group = self::GROWTH_ORES_TOPICS[$topicId]['group'];
			$oresTopics = self::GROWTH_ORES_TOPICS[$topicId]['oresTopics'];
			return new OresBasedTopic( $topicId, $group, $oresTopics );
		}, $this->getAllowedTopics() );

		$this->sortTopics( $topics, self::GROWTH_ORES_TOPIC_GROUPS );

		// FIXME T301030 remove when campaign is done.
		$campaignTopics = array_map( static function ( $topic ) {
			return new CampaignTopic( $topic[ 'id' ], $topic[ 'searchExpression' ] );
		}, $this->getCampaignTopics() );

		array_unshift( $topics, ...$campaignTopics );

		$this->topics = $topics;

		return $topics;
	}

	/** @inheritDoc */
	public function getTopicsMap(): array {
		$topics = $this->getTopics();
		return array_combine( array_map( static function ( Topic $topic ) {
			return $topic->getId();
		}, $topics ), $topics );
	}

	/**
	 * Get campaign-specific topics
	 */
	private function getCampaignTopics(): array {
		if ( is_callable( $this->campaignConfigCallback ) ) {
			$getCampaignConfig = $this->campaignConfigCallback;
			return $getCampaignConfig()->getCampaignTopics();
		}
		return [];
	}

	/**
	 * Set the callback used to retrieve CampaignConfig, used to show campaign-specific topics
	 */
	public function setCampaignConfigCallback( callable $callback ) {
		$this->campaignConfigCallback = $callback;
	}

	/**
	 * Inject the message localizer.
	 * @param MessageLocalizer $messageLocalizer
	 * @internal To be used by ResourceLoader callbacks only.
	 * @note This is an ugly hack. Normal requests use the global RequestContext as a localizer,
	 *   which is a bit of a kitchen sink, but conceptually can be thought of as a service.
	 *   ResourceLoader provides the ResourceLoaderContext, which is not global and can only be
	 *   obtained by code directly invoked by ResourceLoader. The ConfigurationLoader depends
	 *   on whichever of the two is available, so the localizer cannot be injected in the service
	 *   wiring file, and a factory would not make sense conceptually (there should never be
	 *   multiple configuration loaders). So we provide this method so that the ResourceLoader
	 *   callback can finish the dependency injection.
	 */
	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ): void {
		$this->messageLocalizer = $messageLocalizer;
	}

	// TODO: add test coverage for the sorting

	/**
	 * Sorts topics in-place, based on the group configuration and alphabetically within that.
	 * @param Topic[] &$topics
	 * @param string[] $groups
	 */
	public function sortTopics( array &$topics, array $groups ) {
		if ( !$topics ) {
			return;
		}

		$collation = $this->collationFactory->getCategoryCollation();

		usort( $topics, function ( Topic $left, Topic $right ) use ( $groups, $collation ) {
			$leftGroup = $left->getGroupId();
			$rightGroup = $right->getGroupId();
			if ( $leftGroup !== $rightGroup ) {
				return array_search( $leftGroup, $groups, true ) - array_search( $rightGroup, $groups, true );
			}

			$leftSortKey = $collation->getSortKey(
				$left->getName( $this->messageLocalizer )->text() );
			$rightSortKey = $collation->getSortKey(
				$right->getName( $this->messageLocalizer )->text() );
			return strcmp( $leftSortKey, $rightSortKey );
		} );
	}
}
