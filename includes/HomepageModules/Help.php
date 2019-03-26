<?php

namespace GrowthExperiments\HomepageModules;

use ConfigException;
use GrowthExperiments\HelpPanel;
use IContextSource;
use OOUI\ButtonWidget;
use OOUI\Tag;

class Help extends BaseModule {

	const HELP_MODULE_QUESTION_TAG = 'help module question';

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'help', $context );
	}

	/**
	 * @return string
	 */
	protected function getHeader() {
		return $this->getContext()->msg( 'growthexperiments-homepage-help-header' )->text();
	}

	/**
	 * @return string
	 */
	protected function getSubheader() {
		return $this->getContext()->msg( 'growthexperiments-homepage-help-subheader' )->text();
	}

	/**
	 * @return string|string[]
	 */
	protected function getModules() {
		return 'ext.growthExperiments.Homepage.Help';
	}

	/**
	 * @return string
	 * @throws ConfigException
	 */
	protected function getBody() {
		$helpPanelLinkData = HelpPanel::getHelpPanelLinks(
			$this->getContext(),
			$this->getContext()->getConfig()
		);
		return $helpPanelLinkData['helpPanelLinks'] . $helpPanelLinkData['viewMoreLink'];
	}

	/**
	 * @return Tag|string
	 * @throws ConfigException
	 */
	protected function getFooter() {
		return ( new Tag( 'div' ) )
			->addClasses( [ 'mw-ge-homepage-help-cta' ] )
			->appendContent( new ButtonWidget( [
				'id' => 'mw-ge-homepage-help-cta',
				'href' => HelpPanel::getHelpDeskTitle(
					$this->getContext()->getConfig()
				)->getLinkURL(),
				'label' => $this->getContext()->msg(
					'growthexperiments-homepage-help-ask-help-desk'
				)->text(),
				'infusable' => true,
			] ) );
	}

}
