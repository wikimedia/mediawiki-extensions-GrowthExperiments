<?php

namespace GrowthExperiments\HomepageModules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * Placeholder for the reading recommendations module, which will show article
 * recommendations based on the user's interests.
 */
class ReadingRecommendations extends BaseModule {

	public const MODULE_ID = 'reading-recommendations';

	/**
	 * No details view: the mobile tile shows the same list as desktop.
	 * @var string[]
	 */
	protected static $supportedModes = [
		self::RENDER_DESKTOP,
		self::RENDER_MOBILE_SUMMARY,
	];

	public function __construct(
		IContextSource $context,
		Config $wikiConfig
	) {
		parent::__construct( self::MODULE_ID, $context, $wikiConfig );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->getContext()->msg(
			'growthexperiments-homepage-reading-recommendations-header'
		)->text();
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'articles';
	}

	/** @inheritDoc */
	protected function getMobileSummaryHeader() {
		// Title only, without the arrow that would point to a details view.
		return $this->getHeaderTextElement();
	}

	/** @inheritDoc */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-content' ]
		);
	}

	/** @inheritDoc */
	protected function getBody() {
		// The div becomes the mount point for the Vue app.
		return Html::rawElement(
			'div',
			[ 'id' => 'reading-recommendations-vue-root' ],
			Html::element(
				'h3',
				[],
				$this->getContext()->msg(
					'growthexperiments-homepage-reading-recommendations-personalize-title'
				)->text()
			) .
			Html::element(
				'p',
				[],
				$this->getContext()->msg(
					'growthexperiments-homepage-reading-recommendations-personalize-text'
				)->text()
			)
		);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		// The div becomes the mount point for the Vue app on the mobile summary tile.
		// The id differs from the desktop one on purpose. On the mobile summary page
		// BaseModule::getJsData() also pre-renders getBody() into the hidden overlay
		// container, even for modules without a details view, so both are in the DOM
		// at once. Once the body renders real content, override getJsData() to skip
		// the overlay instead of rendering the list twice.
		return Html::element(
			'div',
			[
				'id' => 'reading-recommendations-vue-root--mobile',
				'class' => 'growthexperiments-homepage-module-text-light',
			],
			$this->getContext()->msg(
				'growthexperiments-homepage-reading-recommendations-personalize-text'
			)->text()
		);
	}
}
