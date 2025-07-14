<?php

namespace GrowthExperiments\Maintenance;

use CirrusSearch\CirrusSearchServices;
use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\WeightedTagsUpdater;
use Generator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Util;
use LogicException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use RuntimeException;
use StatusValue;
use Wikimedia\Assert\PreconditionException;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script for importing article topics from production to a test instance.
 */
class ImportArticleTopics extends Maintenance {

	/** Fetch article topics from a production wiki. */
	public const TOPIC_SOURCE_PROD = 'prod';
	/** Use random topics. */
	public const TOPIC_SOURCE_RANDOM = 'random';

	private WeightedTagsUpdater $weightedTagsUpdater;
	private TitleFactory $titleFactory;
	private LinkBatchFactory $linkBatchFactory;

	private bool $isBeta;

	/** @var string Source of article topic information; one of TOPIC_SOURCE_* */
	private string $topicSource;

	/** @var bool Use verbose output. */
	private bool $verbose;

	/** @var string|null MediaWiki API URL for the production wiki. */
	private ?string $apiUrl;

	/** @var string|null Wiki ID of the production wiki. */
	private ?string $wikiId;

	/** @var bool|null Does the wiki have an 'articletopic' model? */
	private ?bool $wikiHasArticleTopicModel = null;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->requireExtension( 'CirrusSearch' );

		$this->addDescription( 'Import article topics from a production wiki' );
		$this->addOption( 'count', 'Number of articles to fetch a topic for.', false, true );
		$this->addOption( 'topicSource', "Topic source: 'prod' for fetching from a production wiki '
			. '(assumes a wiki with titles imported from production), 'random': random topics", false, true );
		$this->addOption( 'apiUrl', "MediaWiki API URL of the wiki the articles are from. '
			. 'Only with --topicSource=prod. Can be auto-guessed in the beta cluster.", false, true );
		$this->addOption( 'wikiId', "Wiki ID to use when fetching scores from the article topic API. '
			. 'Only with --topicSource=prod. Can be auto-guessed in the beta cluster.", false, true );
		$this->addOption( 'pageList', 'Name of a file containing the list of pages to import topics for, '
			. 'one title per line. When omitted, pages with no topics are selected randomly.', false, true );
		$this->addOption( 'verbose', 'Use verbose output' );
		$this->setBatchSize( 20 );
	}

	public function execute() {
		$this->init();

		$gen = $this->getPages( $this->getBatchSize() );
		foreach ( $gen as $titleBatch ) {
			$topics = $this->getTopics( $titleBatch );
			foreach ( $topics as $pageName => $titleTopics ) {
				if ( $this->verbose ) {
					$topicList = urldecode( http_build_query( $titleTopics, '', ', ' ) );
					$this->output( "Adding topics for $pageName: $topicList\n" );
				}
				try {
					$this->weightedTagsUpdater->updateWeightedTags( Title::newFromText( $pageName )->toPageIdentity(),
						'classification.prediction.articletopic', $titleTopics );
				} catch ( PreconditionException $e ) {
					// Page did not exist
					$this->error( $pageName . ': ' . $e->getMessage() );
				}
			}
			$gen->send( count( $topics ) );
		}
	}

	private function init() {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( !$growthServices->getGrowthConfig()->get( 'GEDeveloperSetup' ) ) {
			$this->fatalError( 'This script cannot be safely run in production. (If the current '
				. 'environment is not production, $wgGEDeveloperSetup should be set to true.)' );
		}

		$cirrusSearchServices = CirrusSearchServices::wrap( $services );
		$this->weightedTagsUpdater = $cirrusSearchServices->getWeightedTagsUpdater();
		$this->titleFactory = $services->getTitleFactory();
		$this->linkBatchFactory = $services->getLinkBatchFactory();
		$this->isBeta = preg_match( '/\.beta\.wmflabs\./', $this->getConfig()->get( 'Server' ) );

		$this->topicSource = $this->getOption( 'topicSource', self::TOPIC_SOURCE_PROD );
		if ( !in_array( $this->topicSource, [ self::TOPIC_SOURCE_PROD, self::TOPIC_SOURCE_RANDOM ] ) ) {
			$this->fatalError( "Invalid value for --topicSource: {$this->topicSource}" );
		}
		if ( $this->topicSource == self::TOPIC_SOURCE_PROD ) {
			$this->apiUrl = $this->getOption( 'apiUrl' );
			$this->wikiId = $this->getOption( 'wikiId' );
			if ( $this->isBeta ) {
				$this->apiUrl ??= $this->getApiUrl();
				$this->wikiId ??= WikiMap::getCurrentWikiId();
			} elseif ( !$this->apiUrl ) {
				$this->fatalError( '--apiUrl is required when --topicSource is prod, '
					. 'unless running in the beta cluster' );
			} elseif ( !$this->wikiId ) {
				$this->fatalError( '--wikiId is required when --topicSource is prod, '
					. 'unless running in the beta cluster' );
			}
		}
		if ( $this->hasOption( 'pageList' ) && $this->hasOption( 'count' ) ) {
			$this->fatalError( 'It makes no sense to use --count and --pageList together' );
		} elseif ( !$this->hasOption( 'pageList' ) && !$this->hasOption( 'count' ) ) {
			$this->fatalError( 'One of --count or --pageList is required' );
		}

		$this->verbose = $this->hasOption( 'verbose' );
	}

	/**
	 * @param int $batchSize
	 * @return Generator<Title[]>
	 */
	private function getPages( int $batchSize ) {
		$pageList = $this->getOption( 'pageList' );
		if ( $pageList ) {
			if ( $pageList[0] !== '/' ) {
				$pageList = ( $_SERVER['PWD'] ?? getcwd() ) . '/' . $pageList;
			}
			$pages = file_get_contents( $pageList );
			if ( $pages === false ) {
				$this->fatalError( "Could not read $pageList" );
			}
			$pages = preg_split( '/\n/', $pages, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( array_chunk( $pages, $batchSize ) as $pageBatch ) {
				$titleBatch = array_filter( array_map( [ $this->titleFactory, 'newFromText' ], $pageBatch ) );
				$this->linkBatchFactory->newLinkBatch( $titleBatch )->execute();
				yield $titleBatch;
			}
		} else {
			$totalCount = $this->getOption( 'count' );
			$batchSize = min( $batchSize, $totalCount );
			$offset = 0;
			while ( $totalCount > 0 ) {
				// Exclude Selenium test articles. The search query regex syntax does not seem to
				// allow for \d.
				$searchTerms = [
					'-intitle:/[0-9]{10}/',
					'-articletopic:' . implode( '|', array_keys( ArticleTopicFeature::TERMS_TO_LABELS ) ),
				];
				$titleBatch = $this->search( implode( ' ', $searchTerms ), $batchSize, $offset );
				if ( !$titleBatch ) {
					$this->fatalError( 'No more articles found' );
				} elseif ( $this->verbose ) {
					$this->output( 'Found ' . count( $titleBatch ) . " articles\n" );
				}
				$fixedCount = yield $titleBatch;
				$totalCount -= $fixedCount;
				$offset += $batchSize;
			}
		}
	}

	/**
	 * @param Title[] $titles
	 * @return int[][] title => topic => score
	 */
	private function getTopics( array $titles ): array {
		if ( $this->topicSource === self::TOPIC_SOURCE_RANDOM ) {
			$topics = $this->getTopicsByRandom( $titles );
		} elseif ( $this->topicSource === self::TOPIC_SOURCE_PROD ) {
			$titleStrings = array_map( static function ( Title $title ) {
				return $title->getPrefixedText();
			}, $titles );
			$wikiId = $this->wikiId;
			$apiUrl = $this->apiUrl;
			$titleMap = [];

			if ( !$this->hasArticleTopicModel( $this->wikiId ) ) {
				$wikiId = 'enwiki';
				$apiUrl = 'https://en.wikipedia.org/w/api.php';
				$titleMap = $this->getSiteLinks( $titleStrings, $this->apiUrl, $missingTitles );
				$titleStrings = array_values( $titleMap );
				if ( $this->verbose && $missingTitles ) {
					$this->output( 'not found on enwiki: ' . implode( ', ', $missingTitles ) . "\n" );
				}
				if ( !$titleStrings ) {
					return [];
				}
			}

			if ( $apiUrl === null || $wikiId === null ) {
				throw new RuntimeException( "No API URL ($apiUrl) or wiki ID ($wikiId)" );
			}

			$titleToRevId = $this->titlesToRevisionIds( $titleStrings, $apiUrl, $missingTitles );
			if ( $this->verbose && $missingTitles ) {
				$this->output( 'not found on the production wiki: ' . implode( ', ', $missingTitles ) . "\n" );
			}
			if ( !$titleToRevId ) {
				return [];
			}
			if ( !$this->hasArticleTopicModel( $this->wikiId ) ) {
				$reverseTitleMap = array_flip( $titleMap );
				$titleToRevId = array_flip( array_map( static function ( string $title ) use ( $reverseTitleMap ) {
					return $reverseTitleMap[$title];
				}, array_flip( $titleToRevId ) ) );
			}

			$topics = $this->getTopicsFromOres( $titleToRevId, $wikiId );
		} else {
			throw new LogicException( 'cannot get here' );
		}
		foreach ( $topics as $title => &$scores ) {
			foreach ( $scores as $topic => &$score ) {
				// Scale probability values to 1-1000. We avoid 0 as ElasticSearch cannot
				// represent it.
				$score = intval( ceil( 1000 * $score ) );
			}
		}
		return $topics;
	}

	/**
	 * For a set of titles, set random article topic data.
	 * @param Title[] $titles
	 * @return int[][] title => topic => score
	 */
	private function getTopicsByRandom( array $titles ): array {
		$topicScores = [];
		foreach ( $titles as $title ) {
			$randomTopics = $articleTopics = array_rand( array_flip( ArticleTopicFeature::TERMS_TO_LABELS ), 3 );
			$topicScores[$title->getPrefixedText()] = array_combine( $randomTopics, array_map( static function ( $_ ) {
				return mt_rand() / mt_getrandmax();
			}, $randomTopics ) );
		}
		return $topicScores;
	}

	private function getApiUrl(): string {
		$title = Title::newFromText( 'Title' );
		$devUrl = $title->getFullURL();
		$prodUrl = preg_replace( '/\.beta\.wmflabs\./', '.', $devUrl );
		if ( $devUrl === $prodUrl ) {
			// Ensure we are not doing something unexpected, such as accidentally running in production
			$this->fatalError( 'Could not guess production URL' );
		}
		$urlUtils = $this->getServiceContainer()->getUrlUtils();
		$urlParts = $urlUtils->parse( $prodUrl ) ?? [];
		$urlParts['path'] = '/w/api.php';
		unset( $urlParts['query'] );
		return UrlUtils::assemble( $urlParts );
	}

	/**
	 * Does the wiki have an 'articletopic' model?
	 * @param string $wikiId
	 * @return bool
	 */
	private function hasArticleTopicModel( string $wikiId ): bool {
		if ( $this->wikiHasArticleTopicModel === null ) {
			$oresApiUrl = 'https://ores.wikimedia.org/v3/scores/';
			$modelData = $this->getJsonData( $oresApiUrl, [ 'model_info' => '' ] );
			$this->wikiHasArticleTopicModel = isset( $modelData[$wikiId]['models']['articletopic'] );
		}
		return $this->wikiHasArticleTopicModel;
	}

	/**
	 * For a set of titles, fetch the article topic data for the articles with the same titles
	 * from a Wikimedia production wiki.
	 * @param int[] $revIds revision IDs (keys will be preserved and used in the return value).
	 * @param string $wikiId Wiki ID to use for the article topic queries.
	 * @return int[][] key => topic => score.
	 */
	private function getTopicsFromOres( array $revIds, string $wikiId ): array {
		$oresApiUrl = "https://ores.wikimedia.org/v3/scores/$wikiId";
		$data = $this->getJsonData( $oresApiUrl, [
			'models' => 'articletopic',
			'revids' => implode( '|', $revIds ),
		] );

		$topics = [];
		$revIdKeys = array_flip( $revIds );
		foreach ( $data[$wikiId]['scores'] as $revId => $scores ) {
			$topicScores = [];
			foreach ( $scores['articletopic']['score']['prediction'] as $topic ) {
				$topicScores[$topic] = $scores['articletopic']['score']['probability'][$topic];
			}
			$topics[$revIdKeys[$revId]] = $topicScores;
		}
		return $topics;
	}

	/**
	 * Gets enwiki sitelinks for a batch of pages.
	 * @param string[] $titles Titles as prefixed text.
	 * @param string $apiUrl
	 * @param string[]|null &$missingTitles Returns the list of titles (as prefixed text) which are not found.
	 * @return string[] Title => enwiki title
	 */
	private function getSiteLinks( array $titles, string $apiUrl, ?array &$missingTitles = null ): array {
		$data = $this->getJsonData( $apiUrl, [
			'action' => 'query',
			'prop' => 'langlinks',
			'rvprop' => 'ids',
			'titles' => implode( '|', $titles ),
			'lllang' => 'en',
			'lllimit' => 'max',
		], true );
		$siteLinks = [];
		foreach ( $data['query']['pages'] as $page ) {
			if ( isset( $page['langlinks'] ) ) {
				$siteLinks[$page['title']] = $page['langlinks'][0]['title'];
			}
		}
		$missingTitles = array_diff( $titles, array_keys( $siteLinks ) );
		return $siteLinks;
	}

	/**
	 * @param string[] $titles Titles as prefixed text.
	 * @param string $apiUrl
	 * @param string[]|null &$missingTitles Returns the list of titles (as prefixed text) which are not found.
	 * @return int[] title as prefixed text => rev ID
	 */
	private function titlesToRevisionIds( array $titles, string $apiUrl, ?array &$missingTitles = null ): array {
		$data = $this->getJsonData( $apiUrl, [
			'action' => 'query',
			'prop' => 'revisions',
			'rvprop' => 'ids',
			'titles' => implode( '|', $titles ),
		], true );

		$titleToRevId = [];
		foreach ( $data['query']['pages'] as $row ) {
			if ( isset( $row['revisions'] ) ) {
				$titleToRevId[$row['title']] = $row['revisions'][0]['revid'];
			}
		}

		$revIdToTitle = array_flip( $titleToRevId );
		$missingTitles = array_diff( $titles, array_values( $revIdToTitle ) );

		return $titleToRevId;
	}

	/**
	 * @param string $url JSON URL
	 * @param string[] $parameters Query parameters
	 * @param bool $isMediaWikiApiUrl
	 * @return mixed A JSON value
	 */
	private function getJsonData( string $url, array $parameters = [], bool $isMediaWikiApiUrl = false ) {
		$requestFactory = $this->getServiceContainer()->getHttpRequestFactory();
		if ( $isMediaWikiApiUrl ) {
			$result = Util::getApiUrl( $requestFactory, $url, $parameters + [ 'errorlang' => 'en' ] );
		} else {
			if ( $parameters ) {
				$url .= '?' . wfArrayToCgi( $parameters );
			}
			$result = Util::getJsonUrl( $requestFactory, $url );
		}
		if ( !$result->isOK() ) {
			$this->fatalError( Status::wrap( $result )->getWikiText( false, false, 'en' ) );
		}
		return $result->getValue();
	}

	/**
	 * Do a CirrusSearch query.
	 * @param string $query Search query
	 * @param int $limit
	 * @param int $offset
	 * @return Title[]
	 */
	private function search( string $query, int $limit, int $offset ): array {
		$searchEngine = $this->getServiceContainer()->newSearchEngine();
		$searchEngine->setLimitOffset( $limit, $offset );
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setShowSuggestion( false );
		$searchEngine->setSort( 'none' );
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

// @codeCoverageIgnoreStart
$maintClass = ImportArticleTopics::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
