<?php

namespace GrowthExperiments\Config;

use CommentStoreComment;
use FormatJson;
use GrowthExperiments\Config\Validation\IConfigValidator;
use InvalidArgumentException;
use JsonContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MWException;
use Psr\Log\LoggerInterface;
use RecentChange;
use Status;
use TitleFactory;

class WikiPageConfigWriter {
	/** @var LinkTarget */
	private $configPage;

	/** @var UserIdentity */
	private $performer;

	/** @var IConfigValidator */
	private $configValidator;

	/** @var WikiPageConfigLoader */
	private $wikiPageConfigLoader;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var UserFactory */
	private $userFactory;

	/** @var LoggerInterface */
	private $logger;

	/** @var array|null */
	private $wikiConfig;

	/** @var string[] List of variables that can be overridden on wiki */
	private $allowList;

	/**
	 * @param IConfigValidator $configValidator
	 * @param WikiPageConfigLoader $wikiPageConfigLoader
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param LoggerInterface $logger
	 * @param string[] $allowList
	 * @param LinkTarget $configPage
	 * @param UserIdentity $performer
	 */
	public function __construct(
		IConfigValidator $configValidator,
		WikiPageConfigLoader $wikiPageConfigLoader,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		UserFactory $userFactory,
		LoggerInterface $logger,
		array $allowList,
		LinkTarget $configPage,
		UserIdentity $performer
	) {
		$this->configValidator = $configValidator;
		$this->wikiPageConfigLoader = $wikiPageConfigLoader;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
		$this->logger = $logger;

		$this->allowList = $allowList;
		$this->configPage = $configPage;
		$this->performer = $performer;
	}

	/**
	 * Return current wiki config, loaded via WikiPageConfigLoader
	 *
	 * @return array
	 */
	private function getCurrentWikiConfig(): array {
		if ( $this->titleFactory->newFromLinkTarget( $this->configPage )->exists() ) {
			$config = $this->wikiPageConfigLoader->load(
				$this->configPage,
				WikiPageConfigLoader::READ_LATEST
			);
			if ( !is_array( $config ) ) {
				if ( $config instanceof Status ) {
					// In case config loader returned a status object, log details that could
					// be useful for debugging.
					$this->logger->error(
						__METHOD__ . ' failed to load config from ' . $this->configPage . ', Status object returned',
						[
							'errorArray' => $config->getErrors()
						]
					);
				}
				throw new InvalidArgumentException( __METHOD__ . ' failed to load config from ' . $this->configPage );
			}
			return $config;
		} else {
			return [];
		}
	}

	/**
	 * Load wiki-config via WikiPageConfigLoader, if some exists
	 */
	private function loadConfig(): void {
		$this->wikiConfig = $this->getCurrentWikiConfig();
	}

	/**
	 * Unset all config variables
	 *
	 * Useful for migration purposes, or for other places where we want to
	 * start with an empty config.
	 */
	public function pruneConfig(): void {
		$this->wikiConfig = [];
	}

	/**
	 * @param string|array $variable Variable name, or a list where the first item is the
	 *   variable name and subsequent items are array keys, e.g. [ 'foo', 'bar', 'baz' ]
	 *   means changing $foo['bar']['baz'] (where $foo stands for the 'foo' variable).
	 * @param mixed $value
	 * @throws InvalidArgumentException when $variable is an array but the variable it refers to isn't.
	 */
	public function setVariable( $variable, $value ): void {
		if ( $this->wikiConfig === null ) {
			$this->loadConfig();
		}

		if ( is_string( $variable ) ) {
			$baseVariable = $variable;
			$fullValue = $value;
		} else {
			$baseVariable = array_shift( $variable );
			$fullValue = $this->wikiConfig[$baseVariable] ?? [];
			$field = &$fullValue;
			foreach ( $variable as $key ) {
				if ( !is_array( $field ) ) {
					throw new InvalidArgumentException( 'Trying to set a sub-field of a non-array' );
				}
				$field = &$field[$key];
			}
			$field = $value;
		}

		$this->configValidator->validateVariable( $baseVariable, $fullValue );
		$this->wikiConfig[$baseVariable] = $fullValue;
	}

	/**
	 * Check if a given variable or a subfield exists.
	 * @param string|array $variable Variable name, or a list where the first item is the
	 *   variable name and subsequent items are array keys, e.g. [ 'foo', 'bar', 'baz' ]
	 *   means checking $foo['bar']['baz'] (where $foo stands for the 'foo' variable).
	 * @return bool Whether the variable exists. The semantics are like array_key_exists().
	 * @throws InvalidArgumentException when $variable is an array but the variable it refers to isn't.
	 */
	public function variableExists( $variable ): bool {
		if ( $this->wikiConfig === null ) {
			$this->loadConfig();
		}
		$variablePath = (array)$variable;
		$config = $this->wikiConfig;
		foreach ( $variablePath as $pathSegment ) {
			if ( !is_array( $config ) ) {
				throw new InvalidArgumentException( 'Trying to check a sub-field of a non-array' );
			}
			if ( !array_key_exists( $pathSegment, $config ) ) {
				return false;
			}
			$config = $config[$pathSegment] ?? [];
		}
		return true;
	}

	/**
	 * @param array $variables
	 */
	public function setVariables( array $variables ): void {
		foreach ( $variables as $variable => $value ) {
			$this->setVariable( $variable, $value );
		}
	}

	/**
	 * @param string $summary
	 * @param bool $minor
	 * @param array|string $tags Tag(s) to apply (defaults to none)
	 * @param bool $bypassWarnings Should warnings/non-fatals stop the operation? Defaults to
	 * true.
	 * @return Status
	 * @throws MWException
	 */
	public function save(
		string $summary = '',
		bool $minor = false,
		$tags = [],
		bool $bypassWarnings = true
	): Status {
		// Load config if not done already, to support null-edits
		if ( $this->wikiConfig === null ) {
			$this->loadConfig();
		}

		// Sort config alphabetically
		ksort( $this->wikiConfig, SORT_STRING );

		$status = Status::newGood();
		$status->merge( $this->configValidator->validate( $this->wikiConfig ) );

		if (
			!$status->isOK() ||
			( !$bypassWarnings && !$status->isGood() )
		) {
			$status->setOK( false );
			return $status;
		}

		// Save only if config was changed, so editing interface
		// doesn't need to make sure config was indeed changed.
		if ( $this->wikiConfig !== $this->getCurrentWikiConfig() ) {
			$page = $this->wikiPageFactory->newFromLinkTarget( $this->configPage );
			$updater = $page->newPageUpdater( $this->performer );
			if ( is_string( $tags ) ) {
				$updater->addTag( $tags );
			} elseif ( is_array( $tags ) ) {
				$updater->addTags( $tags );
			}
			$updater->setContent( SlotRecord::MAIN, new JsonContent(
				FormatJson::encode( $this->wikiConfig )
			) );

			if ( $this->userFactory
				->newFromUserIdentity( $this->performer )
				->authorizeWrite( 'autopatrol', $page )
			) {
				$updater->setRcPatrolStatus( RecentChange::PRC_AUTOPATROLLED );
			}

			$updater->saveRevision(
				CommentStoreComment::newUnsavedComment( $summary ),
				$minor ? EDIT_MINOR : 0
			);
			$status->merge( $updater->getStatus() );
		}

		// Invalidate config cache regardless of whether any variable was changed
		// to let users to invalidate cache when they wish so (similar to action=purge
		// or null edit concepts)
		$this->wikiPageConfigLoader->invalidate( $this->configPage );

		return $status;
	}
}
