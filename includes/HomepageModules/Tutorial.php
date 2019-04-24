<?php

namespace GrowthExperiments\HomepageModules;

use IContextSource;
use OOUI\ButtonWidget;
use OOUI\Tag;
use Title;

class Tutorial extends BaseTaskModule {

	const TUTORIAL_PREF = 'growthexperiments-homepage-tutorial-completed';
	const TUTORIAL_TITLE_CONFIG = 'GEHomepageTutorialTitle';

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start-tutorial', $context );
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		return $this->getContext()
			->getUser()
			->getBoolOption( self::TUTORIAL_PREF );
	}

	private function getHomepageTutorialTitleValue() {
		return $this->getContext()->getConfig()->get( self::TUTORIAL_TITLE_CONFIG );
	}

	private function getHomepageTutorialTitle() {
		return Title::newFromText( $this->getHomepageTutorialTitleValue() );
	}

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		$tutorialTitle = $this->getHomepageTutorialTitle();
		return $tutorialTitle instanceof Title && $tutorialTitle->exists();
	}

	/**
	 * @inheritDoc
	 */
	protected function getUncompletedIcon() {
		return 'book';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()->msg( 'growthexperiments-homepage-tutorial-header' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return [ 'oojs-ui.styles.icons-editing-citation', 'oojs-ui.styles.icons-interactions' ];
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheader() {
		return $this->getContext()->msg( 'growthexperiments-homepage-tutorial-subheader' )->escaped();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$button = new ButtonWidget( [
			'id' => 'mw-ge-homepage-tutorial-cta',
			'href' => $this->getHomepageTutorialTitle()->getLinkURL(),
			'label' => $this->getContext()->msg(
				'growthexperiments-homepage-tutorial-cta-text'
			)->text(),
		] );
		$button->setAttributes( [ 'data-link-id' => 'tutorial' ] );
		return ( new Tag( 'div' ) )
			->addClasses( [ 'mw-ge-homepage-tutorial-cta' ] )
			->appendContent( $button );
	}
}
