<?php

namespace GrowthExperiments\UserImpact;

use FormatJson;
use GrowthExperiments\Util;
use JsonContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\User\UserIdentity;
use TitleValue;

/**
 * Load user impact data from JSON stored at the page `User:<name>/userimpact.json`,
 * and potentially fall back to another lookup mechanism if it doesn't exist.
 */
class SubpageUserImpactLookup implements UserImpactLookup {

	private const SUBPAGE_NAME = 'userimpact.json';

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var UserImpactLookup */
	private $fallbackLookup;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UserImpactLookup|null $fallbackLookup
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		UserImpactLookup $fallbackLookup = null
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->fallbackLookup = $fallbackLookup;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user, bool $useLatest = false ): ?UserImpact {
		$subpageTitle = new TitleValue( NS_USER, $user->getName() . '/' . self::SUBPAGE_NAME );
		$subpage = $this->wikiPageFactory->newFromLinkTarget( $subpageTitle );

		if ( !$subpage->exists() ) {
			return $this->fallbackLookup ? $this->fallbackLookup->getUserImpact( $user ) : null;
		}
		$content = $subpage->getContent();
		if ( !( $content instanceof JsonContent ) ) {
			Util::logText( 'Page User:' . $user->getName() . '/'
				. self::SUBPAGE_NAME . ' has unexpected content model ' . $content->getModel() );
			return null;
		}

		$dataStatus = FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC );
		if ( !$dataStatus->isOK() ) {
			Util::logText( 'Invalid JSON content: '
				. $dataStatus->getWikiText( false, false, 'en' ) );
			return null;
		}
		$data = $dataStatus->getValue();

		return UserImpact::newFromJsonArray( $data );
	}

}
