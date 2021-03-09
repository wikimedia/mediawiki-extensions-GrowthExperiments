<?php

namespace GrowthExperiments\Maintenance;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Query\ArticleTopicFeature;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Util;
use LogicException;
use Maintenance;
use MediaWiki\MediaWikiServices;
use Status;
use StatusValue;
use Title;
use WikiMap;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

/**
 * Maintenance script for importing ORES topics from production to a test instance.
 */
class ImportOresTopics extends Maintenance {

	/** Fetch ORES topics from a production wiki. */
	public const TOPIC_SOURCE_PROD = 'prod';
	/** Use random topics. */
	public const TOPIC_SOURCE_RANDOM = 'random';

	/** @var CirrusSearch */
	private $cirrusSearch;

	/** @var bool Are we on the beta cluster? */
	private $isBeta;

	/** @var string Source of ORES topic information; one of TOPIC_SOURCE_* */
	private $topicSource;

	/** @var bool Use verbose output. */
	private $verbose;

	/** @var string|null MediaWiki API URL for the production wiki. */
	private $apiUrl;

	/** @var string|null Wiki ID of the production wiki. */
	private $wikiId;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->requireExtension( 'CirrusSearch' );

		$this->addDescription( 'Import ORES topics from a production wiki' );
		$this->addOption( 'count', 'Number of articles to fetch a topic for.', true, true );
		$this->addOption( 'topicSource', "Topic source: 'prod' for fetching from a production wiki '
			. '(assumes a wiki with titles imported from production), 'random': random topics", false, true );
		$this->addOption( 'apiUrl', "MediaWiki API URL of the wiki the articles are from. '
			. 'Only with --topicSource=prod. Can be auto-guessed in the beta cluster.", false, true );
		$this->addOption( 'wikiId', "Wiki ID to use when fetching scores from the ORES API. '
			. 'Only with --topicSource=prod. Can be auto-guessed in the beta cluster.", false, true );
		$this->addOption( 'verbose', 'Use verbose output' );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		$this->init();

		$totalCount = $this->getOption( 'count' );
		$batchSize = min( $this->getBatchSize(), $totalCount );
		$offset = 0;
		while ( $totalCount > 0 ) {
			// Exclude Selenium test articles. The search query regex syntax does not seem to
			// allow for \d.
			$titles = $this->search( '-intitle:/[0-9]{10}/', $batchSize, $offset );
			if ( !$titles ) {
				$this->fatalError( 'No more articles found' );
			} elseif ( $this->verbose ) {
				$this->output( 'Found ' . count( $titles ) . " articles\n" );
			}
			$topics = $this->getTopics( $titles );
			foreach ( $topics as $title => $titleTopics ) {
				if ( $this->verbose ) {
					$topicList = urldecode( http_build_query( $titleTopics, '', ', ' ) );
					$this->output( "Adding topics for $title: $topicList\n" );
				}
				$this->cirrusSearch->updateWeightedTags( Title::newFromText( $title )->toPageIdentity(),
					'classification.ores.articletopic', array_keys( $titleTopics ), $titleTopics );
			}
			$totalCount -= count( $topics );
			$offset += $batchSize;
		}
	}

	private function init() {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		if ( !$growthServices->getConfig()->get( 'GEDeveloperSetup' ) ) {
			$this->fatalError( 'This script cannot be safely run in production. (If the current '
				. 'environment is not production, $wgGEDeveloperSetup should be set to true.)' );
		}

		$this->cirrusSearch = new CirrusSearch();
		$this->isBeta = preg_match( '/\.beta\.wmflabs\./', $this->getConfig()->get( 'Server' ) );

		$this->topicSource = $this->getOption( 'topicSource', self::TOPIC_SOURCE_PROD );
		if ( !in_array( $this->topicSource, [ self::TOPIC_SOURCE_PROD, self::TOPIC_SOURCE_RANDOM ] ) ) {
			$this->fatalError( "Invalid value for --topicSource: {$this->topicSource}" );
		}
		if ( $this->topicSource == self::TOPIC_SOURCE_PROD ) {
			$this->apiUrl = $this->getOption( 'apiUrl' );
			$this->wikiId = $this->getOption( 'wikiId' );
			if ( $this->isBeta ) {
				$this->apiUrl = $this->apiUrl ?? $this->getApiUrl();
				$this->wikiId = $this->wikiId ?? WikiMap::getCurrentWikiId();
			} elseif ( !$this->apiUrl ) {
				$this->fatalError( '--apiUrl is required when --topicSource is prod, '
					. 'unless running in the beta cluster' );
			} elseif ( !$this->wikiId ) {
				$this->fatalError( '--wikiId is required when --topicSource is prod, '
					. 'unless running in the beta cluster' );
			}
		}
		$this->verbose = $this->hasOption( 'verbose' );
	}

	/**
	 * @param Title[] $titles
	 * @return float[][] title => topic => score
	 */
	private function getTopics( array $titles ): array {
		if ( $this->topicSource === self::TOPIC_SOURCE_RANDOM ) {
			return $this->getTopicsByRandom( $titles );
		} elseif ( $this->topicSource === self::TOPIC_SOURCE_PROD ) {
			return $this->getTopicsFromOres( $titles, $this->apiUrl, $this->wikiId );
		}
		throw new LogicException( 'cannot get here' );
	}

	/**
	 * For a set of titles, set random ORES data.
	 * @param Title[] $titles
	 * @return float[][] title => topic => score
	 */
	private function getTopicsByRandom( array $titles ): array {
		$topicScores = [];
		foreach ( $titles as $title ) {
			$randomTopics = $oresTopics = array_rand( array_flip( ArticleTopicFeature::TERMS_TO_LABELS ), 3 );
			$topicScores[$title->getPrefixedText()] = array_combine( $randomTopics, array_map( function ( $_ ) {
				return mt_rand() / mt_getrandmax();
			}, $randomTopics ) );
		}
		return $topicScores;
	}

	/**
	 * @return string
	 */
	private function getApiUrl(): string {
		$title = Title::newFromText( 'Title' );
		$devUrl = $title->getFullURL();
		$prodUrl = preg_replace( '/\.beta\.wmflabs\./', '.', $devUrl );
		if ( $devUrl === $prodUrl ) {
			// Ensure we are not doing something unexpected, such as accidentally running in production
			$this->fatalError( 'Could not guess production URL' );
		}
		$urlParts = wfParseUrl( $prodUrl );
		$urlParts['path'] = '/w/api.php';
		unset( $urlParts['query'] );
		return wfAssembleUrl( $urlParts );
	}

	/**
	 * For a set of titles, fetch the ORES topic data for the articles with the same titles
	 * from a Wikimedia production wiki.
	 * @param Title[] $titles
	 * @param string $apiUrl MediaWiki API URL to use for converting titles to page IDs.
	 * @param string $wikiId Wiki ID to use for the ORES queries.
	 * @return float[][] title => topic => score
	 */
	private function getTopicsFromOres( array $titles, string $apiUrl, string $wikiId ): array {
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();

		$titleStrings = array_map( function ( Title $title ) {
			return $title->getPrefixedText();
		}, $titles );
		$result = Util::getApiUrl(
			$requestFactory,
			$apiUrl,
			[
				'action' => 'query',
				'prop' => 'revisions',
				'rvprop' => 'ids',
				'titles' => implode( '|', $titleStrings ),
			]
		);
		if ( !$result->isOK() ) {
			$this->fatalError( Status::wrap( $result )->getWikiText( false, false, 'en' ) );
		}
		$data = $result->getValue();

		$titleToRevId = [];
		foreach ( $data['query']['pages'] as $row ) {
			if ( isset( $row['revisions'] ) ) {
				$titleToRevId[$row['title']] = $row['revisions'][0]['revid'];
			}
		}
		$revIdToTitle = array_flip( $titleToRevId );

		if ( $this->verbose ) {
			$missingTitles = array_diff( $titleStrings, array_values( $revIdToTitle ) );
			if ( $missingTitles ) {
				$this->output( 'not found on the production wiki: ' . implode( ', ', $missingTitles ) . "\n" );
			}
		}
		if ( !$titleToRevId ) {
			return [];
		}

		$oresApiUrl = "https://ores.wikimedia.org/v3/scores/$wikiId?" . wfArrayToCgi( [
			'models' => 'articletopic',
			'revids' => implode( '|', $titleToRevId ),
		] );
		$result = Util::getJsonUrl( $requestFactory, $oresApiUrl );
		if ( !$result->isOK() ) {
			$this->fatalError( Status::wrap( $result )->getWikiText( false, false, 'en' ) );
		}
		$data = $result->getValue();

		$topics = [];
		foreach ( $data[$wikiId]['scores'] as $revId => $scores ) {
			$topicScores = [];
			foreach ( $scores['articletopic']['score']['prediction'] as $topic ) {
				$topicScores[$topic] = $scores['articletopic']['score']['probability'][$topic];
			}
			$topics[$revIdToTitle[$revId]] = $topicScores;
		}
		return $topics;
	}

	/**
	 * Do a CirrusSearch query.
	 * @param string $query Search query
	 * @param int $limit
	 * @param int $offset
	 * @return Title[]
	 */
	private function search( string $query, int $limit, int $offset ): array {
		$searchEngine = MediaWikiServices::getInstance()->newSearchEngine();
		$searchEngine->setLimitOffset( $limit, $offset );
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setShowSuggestion( false );
		$searchEngine->setSort( 'random' );
		$matches = $searchEngine->searchText( $query )
			?? StatusValue::newFatal( 'rawmessage', 'Search is disabled' );
		if ( $matches instanceof StatusValue ) {
			if ( $matches->isOK() ) {
				$matches = $matches->getValue();
			} else {
				$this->fatalError( Status::wrap( $matches )->getWikiText( false, false, 'en' ) );
			}
		}
		return $matches->extractTitles();
	}

}

$maintClass = ImportOresTopics::class;
require_once RUN_MAINTENANCE_IF_MAIN;
