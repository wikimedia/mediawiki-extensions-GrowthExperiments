<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

/**
 * Reads and validates the interest articles a user picked during account setup.
 *
 * The preference is written client-side as a JSON array of prefixed titles, so
 * every entry is untrusted. Entries that do not parse, are not in the main
 * namespace, are interwiki, or carry a fragment are skipped with a warning.
 */
class InterestArticlesLookup {

	/** @var array<string,LinkTarget[]> Interests by user, for one request. */
	private array $interestsByUser = [];

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly TitleParser $titleParser,
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * The homepage asks for the same user twice in a request, once for the
	 * recommendations and once for the call to action, so the result is kept
	 * for the request: without it every ask re-parses the preference and
	 * repeats the warning for each invalid entry.
	 *
	 * @param UserIdentity $user
	 * @return LinkTarget[] Mainspace titles, deduplicated, in the stored order.
	 */
	public function getInterests( UserIdentity $user ): array {
		$key = $user->getId() . '|' . $user->getName();
		$this->interestsByUser[$key] ??= $this->readInterests( $user );
		return $this->interestsByUser[$key];
	}

	/**
	 * @param UserIdentity $user
	 * @return LinkTarget[]
	 */
	private function readInterests( UserIdentity $user ): array {
		$json = $this->userOptionsLookup->getOption( $user, AccountSetupHooks::INTEREST_ARTICLES_PROP );
		if ( !is_string( $json ) || $json === '' ) {
			return [];
		}
		$entries = FormatJson::decode( $json, true );
		if ( !is_array( $entries ) ) {
			$this->logger->warning( 'InterestArticlesLookup: interest articles are not a JSON array' );
			return [];
		}

		$interests = [];
		foreach ( $entries as $entry ) {
			if ( !is_string( $entry ) ) {
				$this->warnSkipped( 'not a string' );
				continue;
			}
			try {
				$title = $this->titleParser->parseTitle( $entry );
			} catch ( MalformedTitleException ) {
				$this->warnSkipped( 'malformed' );
				continue;
			}
			if ( $title->getNamespace() !== NS_MAIN ) {
				$this->warnSkipped( 'not in the main namespace' );
				continue;
			}
			if ( $title->isExternal() ) {
				$this->warnSkipped( 'interwiki' );
				continue;
			}
			if ( $title->getFragment() !== '' ) {
				$this->warnSkipped( 'has a fragment' );
				continue;
			}
			$interests[$title->getDBkey()] ??= $title;
		}
		return array_values( $interests );
	}

	private function warnSkipped( string $reason ): void {
		$this->logger->warning( 'InterestArticlesLookup: skipped an interest article entry', [
			'reason' => $reason,
		] );
	}
}
