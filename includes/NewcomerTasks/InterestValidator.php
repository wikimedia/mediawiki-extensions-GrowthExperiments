<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic;
use MediaWiki\Page\PageLookup;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;

/**
 * Tells apart the interests that can get suggestions from the ones that cannot.
 *
 * Interests come from a user preference and from the API, so each entry can be any string.
 * Every caller that reads interests must remove the invalid ones, so that the code below
 * the entry points only sees interests which can get suggestions.
 *
 * The article behind an interest can be deleted after the user picked it. We treat such an
 * interest as invalid for as long as the article is missing, but we leave it in the
 * preference, so it should come back if the article is restored. The same goes for a
 * redirect whose target is missing, for example when the article was moved and then
 * deleted (T441415).
 */
class InterestValidator {

	public function __construct(
		private readonly TitleParser $titleParser,
		private readonly PageLookup $pageLookup,
		private readonly RedirectLookup $redirectLookup,
	) {
	}

	/**
	 * Tell apart the interests that can get suggestions from the ones that cannot.
	 * @param string[] $interests Prefixed article titles.
	 * @return array{valid:string[],invalid:string[]} Both lists keep the given order.
	 */
	public function validate( array $interests ): array {
		$result = [ 'valid' => [], 'invalid' => [] ];
		foreach ( $interests as $interest ) {
			$result[$this->isValid( $interest ) ? 'valid' : 'invalid'][] = $interest;
		}
		return $result;
	}

	private function isValid( string $interest ): bool {
		try {
			$title = $this->titleParser->parseTitle( $interest );
		} catch ( MalformedTitleException ) {
			return false;
		}
		if ( !InterestBasedTopic::isValidTitle( $title ) ) {
			return false;
		}
		// Skip articles that no longer exist. A morelikethis search for one comes back with
		// a warning, and that currently stops the task query for every other interest too.
		$page = $this->pageLookup->getPageByName( $title->getNamespace(), $title->getDBkey() );
		if ( !$page ) {
			return false;
		}
		if ( !$page->isRedirect() ) {
			return true;
		}
		// The search follows redirects, so the redirect target has to be a valid interest
		// title as well, and it has to exist. A redirect may point to a section; the search
		// uses the whole article, so the section is ignored.
		$target = $this->redirectLookup->getRedirectTarget( $page );
		return $target !== null
			&& InterestBasedTopic::isValidTitle( $target->createFragmentTarget( '' ) )
			&& $this->pageLookup->getPageByName( $target->getNamespace(), $target->getDBkey() ) !== null;
	}

}
