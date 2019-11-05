<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use BagOStuff;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\Util;
use HashBagOStuff;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Message;
use MessageLocalizer;
use StatusValue;
use TitleFactory;
use TitleValue;

/**
 * Load configuration from a remote .json wiki page.
 * For syntax see
 * https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks/Prototype/templates/cs.json
 */
class RemotePageConfigurationLoader implements ConfigurationLoader {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var BagOStuff */
	private $cache;

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var int Cache expiry (0 for unlimited). */
	private $cacheTtl = 0;

	/** @var LinkTarget Title of the config page. Can be an interwiki title. */
	private $pageTitle;

	/** @var TaskType[]|StatusValue|null Cached task type set (or an error). */
	private $taskTypes;

	/** @var LinkTarget[]|StatusValue|null Cached template blacklist (or an error).  */
	private $templateBlacklist;

	/**
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param MessageLocalizer $messageLocalizer
	 * @param LinkTarget $pageTitle Wiki page to load configuration from. Can be an interwiki title.
	 */
	public function __construct(
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		MessageLocalizer $messageLocalizer,
		LinkTarget $pageTitle
	) {
		$this->requestFactory = $requestFactory;
		$this->titleFactory = $titleFactory;
		$this->messageLocalizer = $messageLocalizer;
		$this->pageTitle = $pageTitle;
		$this->cache = new HashBagOStuff();
	}

	/** @inheritDoc */
	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ): void {
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Use a different cache. (Default is in-process caching only.)
	 * @param BagOStuff $cache
	 * @param int $ttl Cache expiry (0 for unlimited).
	 */
	public function setCache( BagOStuff $cache, $ttl ) {
		$this->cache = $cache;
		$this->cacheTtl = $ttl;
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		if ( $this->taskTypes !== null ) {
			return $this->taskTypes;
		}

		$config = $this->loadConfig();
		if ( $config instanceof StatusValue ) {
			$taskTypes = $config;
		} else {
			$taskTypes = $this->parseTaskTypesFromConfig( $config );
		}

		$this->taskTypes = $taskTypes;
		return $taskTypes;
	}

	/** @inheritDoc */
	public function loadTemplateBlacklist() {
		if ( $this->templateBlacklist !== null ) {
			return $this->templateBlacklist;
		}

		$config = $this->loadConfig();
		if ( $config instanceof StatusValue ) {
			$templateBlacklist = $config;
		} else {
			$templateBlacklist = $this->parseTemplateBlacklistFromConfig( $config );
		}

		$this->templateBlacklist = $templateBlacklist;
		return $templateBlacklist;
	}

	/**
	 * Like loadTaskTypes() but without caching.
	 * @param array $config
	 * @return TaskType[]|StatusValue
	 * @suppress PhanTypeArraySuspicious
	 *   Suppress the "Suspicious array access to ?mixed" errors for $taskTypeData;
	 *   no idea what that is about.
	 */
	private function parseTaskTypesFromConfig( array $config ) {
		$status = StatusValue::newGood();
		$taskTypes = [];
		foreach ( $config as $taskTypeId => $taskTypeData ) {
			$requiredFields = [ 'group', 'templates' ];
			foreach ( $requiredFields as $field ) {
				if ( !isset( $taskTypeData[$field] ) ) {
					$status->fatal( 'growthexperiments-homepage-suggestededits-config-missingfield',
						$field, $taskTypeId );
				}
			}
			if ( isset( $taskTypeData['group'] ) &&
				!in_array( $taskTypeData['group'], TaskType::$difficultyClasses, true )
			) {
				$status->fatal( 'growthexperiments-homepage-suggestededits-config-wronggroup',
					$taskTypeData['group'], $taskTypeId );
			}

			if ( $status->isGood() ) {
				$templates = array_map( function ( $template ) {
					return new TitleValue( NS_TEMPLATE, $template );
				}, $taskTypeData['templates'] );
				$taskType = new TemplateBasedTaskType(
					$taskTypeId,
					$taskTypeData['group'],
					[ 'learnMoreLink' => $taskTypeData['learnmore'] ?? null ],
					$templates
				);
				$status->merge( $this->validateMessages( $taskType ) );
				$taskTypes[] = $taskType;
			}
		}
		return $status->isGood() ? $taskTypes : $status;
	}

	/**
	 * Like loadTemplateBlacklist() but without caching.
	 * @param array $config
	 * @return LinkTarget[]|StatusValue
	 */
	private function parseTemplateBlacklistFromConfig( array $config ) {
		// TODO: add templates to the wiki page and implement parsing them here.
		return [];
	}

	/**
	 * Load the configuration page.
	 * @return array|StatusValue
	 */
	private function loadConfig() {
		$cacheKey = $this->cache->makeKey( 'GrowthExperiments', 'NewcomerTasks',
			'config', $this->pageTitle );
		$config = $this->cache->get( $cacheKey );
		if ( $config !== false ) {
			return $config;
		}

		$url = $this->getRawUrl( $this->pageTitle );
		$status = Util::getJsonUrl( $this->requestFactory, $url );
		if ( !$status->isOK() ) {
			return $status;
		}
		$config = $status->getValue();

		$this->cache->set( $cacheKey, $config, $this->cacheTtl );
		return $config;
	}

	/**
	 * Get the action=raw URL for a (probably remote) title.
	 * Normal title methods would return nice URLs, which are usually disallowed for action=raw.
	 * We assume both wikis use the same URL structure.
	 * @param LinkTarget $title
	 * @return string
	 */
	private function getRawUrl( LinkTarget $title ) {
		// Use getFullURL to get the interwiki domain.
		$url = $this->titleFactory->newFromLinkTarget( $title )->getFullURL();
		$parts = wfParseUrl( $url );
		$baseUrl = $parts['scheme'] . $parts['delimiter'] . $parts['host'];

		$localPageTitle = $this->titleFactory->makeTitle( $title->getNamespace(), $title->getDBkey() );
		return $baseUrl . $localPageTitle->getLocalURL( [ 'action' => 'raw' ] );
	}

	/**
	 * Ensure that all messages used by the task type exist.
	 * @param TemplateBasedTaskType $taskType
	 * @return StatusValue
	 */
	private function validateMessages( TemplateBasedTaskType $taskType ) {
		$status = StatusValue::newGood();
		foreach ( [
			$taskType->getName( $this->messageLocalizer ),
			$taskType->getDescription( $this->messageLocalizer ),
			$taskType->getShortDescription( $this->messageLocalizer ),
			$taskType->getTimeEstimate( $this->messageLocalizer )
		] as $msg ) {
			/** @var $msg Message */
			if ( !$msg->exists() ) {
				$status->fatal(
					'growthexperiments-homepage-suggestededits-config-missingmessage',
					$msg->getKey(), $taskType->getId()
				);
			}
		}
		return $status;
	}

}
