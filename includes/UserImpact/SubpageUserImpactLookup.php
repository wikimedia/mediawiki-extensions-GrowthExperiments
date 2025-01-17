<?php

namespace GrowthExperiments\UserImpact;

use GrowthExperiments\Util;
use MediaWiki\Content\JsonContent;
use MediaWiki\Json\FormatJson;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Load user impact data from JSON stored at the page `User:<name>/userimpact.json`,
 * and potentially fall back to another lookup mechanism if it doesn't exist.
 */
class SubpageUserImpactLookup implements UserImpactLookup {

	use ExpensiveUserImpactFallbackTrait;

	private const SUBPAGE_NAME = 'userimpact.json';
	private WikiPageFactory $wikiPageFactory;
	private ?UserImpactLookup $fallbackLookup;

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		?UserImpactLookup $fallbackLookup = null
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->fallbackLookup = $fallbackLookup;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user, int $flags = IDBAccessObject::READ_NORMAL ): ?UserImpact {
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
