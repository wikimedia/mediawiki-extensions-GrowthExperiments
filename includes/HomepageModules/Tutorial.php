<?php

namespace GrowthExperiments\HomepageModules;

use IContextSource;
use OOUI\ButtonInputWidget;
use OOUI\FormLayout;
use OOUI\Tag;
use SpecialPage;
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
		$specialHomepageTitle = SpecialPage::getTitleFor(
			'Homepage',
			$this->getHomepageTutorialTitle()->getPrefixedDBkey()
		);
		$form = new FormLayout( [
			'method' => 'post',
			'action' => $specialHomepageTitle->getLinkURL()
		] );
		$button = new ButtonInputWidget( [
			'id' => 'mw-ge-homepage-tutorial-cta',
			'type' => 'submit',
			'label' => $this->getContext()->msg(
				'growthexperiments-homepage-tutorial-cta-text'
			)->text(),
			'flags' => $this->isCompleted() ? [] : [ 'progressive' ]
		] );
		$form->appendContent( $button );
		$button->setAttributes( [ 'data-link-id' => 'tutorial' ] );
		return ( new Tag( 'div' ) )
			->addClasses( [ 'mw-ge-homepage-tutorial-cta' ] )
			->appendContent( $form );
	}
}
