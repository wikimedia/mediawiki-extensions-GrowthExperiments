<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\Config\Validation\IConfigValidator;
use InvalidArgumentException;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContent;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use RecentChange;
use Wikimedia\Rdbms\IDBAccessObject;

class WikiPageConfigWriter {

	private LinkTarget $configPage;
	private UserIdentity $performer;
	private IConfigValidator $configValidator;
	private WikiPageConfigLoader $wikiPageConfigLoader;
	private WikiPageFactory $wikiPageFactory;
	private TitleFactory $titleFactory;
	private UserFactory $userFactory;
	private HookContainer $hookContainer;
	private LoggerInterface $logger;
	private ?array $wikiConfig = null;

	/**
	 * @param IConfigValidator $configValidator
	 * @param WikiPageConfigLoader $wikiPageConfigLoader
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param HookContainer $hookContainer
	 * @param LoggerInterface $logger
	 * @param LinkTarget $configPage
	 * @param UserIdentity $performer
	 */
	public function __construct(
		IConfigValidator $configValidator,
		WikiPageConfigLoader $wikiPageConfigLoader,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		UserFactory $userFactory,
		HookContainer $hookContainer,
		LoggerInterface $logger,
		LinkTarget $configPage,
		UserIdentity $performer
	) {
		$this->configValidator = $configValidator;
		$this->wikiPageConfigLoader = $wikiPageConfigLoader;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
		$this->hookContainer = $hookContainer;
		$this->logger = $logger;

		$this->configPage = $configPage;
		$this->performer = $performer;
	}

	/**
	 * Return current wiki config, loaded via WikiPageConfigLoader
	 */
	private function getCurrentWikiConfig(): array {
		if ( $this->titleFactory->newFromLinkTarget( $this->configPage )->exists() ) {
			$config = $this->wikiPageConfigLoader->load(
				$this->configPage,
				IDBAccessObject::READ_LATEST
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
			$content = new JsonContent( FormatJson::encode( $this->wikiConfig ) );
			$performerUser = $this->userFactory->newFromUserIdentity( $this->performer );

			// Give AbuseFilter et al. a chance to block the edit (T346235)
			$status->merge( $this->runEditFilterMergedContentHook(
				$performerUser,
				$page->getTitle(),
				$content,
				$summary,
				$minor
			) );

			if ( !$status->isOK() ) {
				return $status;
			}

			$updater = $page->newPageUpdater( $this->performer );
			if ( is_string( $tags ) ) {
				$updater->addTag( $tags );
			} elseif ( is_array( $tags ) ) {
				$updater->addTags( $tags );
			}
			$updater->setContent( SlotRecord::MAIN, $content );

			if ( $performerUser->isAllowed( 'autopatrol' ) ) {
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

	/**
	 * Run the EditFilterMergedContentHook
	 *
	 * @param User $performerUser
	 * @param Title $title
	 * @param Content $content
	 * @param string $summary
	 * @param bool $minor
	 * @return Status
	 */
	private function runEditFilterMergedContentHook(
		User $performerUser,
		Title $title,
		Content $content,
		string $summary,
		bool $minor
	): Status {
		// Ensure context has right values for title and performer, which are available to the
		// config writer. Use the global context for the rest.
		$derivativeContext = new DerivativeContext( RequestContext::getMain() );
		$derivativeContext->setUser( $performerUser );
		$derivativeContext->setTitle( $title );

		$status = new Status();
		$hookRunner = new HookRunner( $this->hookContainer );
		if ( !$hookRunner->onEditFilterMergedContent(
			$derivativeContext,
			$content,
			$status,
			$summary,
			$performerUser,
			$minor
		) ) {
			if ( $status->isGood() ) {
				$status->fatal( 'hookaborted' );
			}
		}
		return $status;
	}
}
