<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\DashboardModule\DashboardModule;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * BaseModule is a base class for homepage modules.
 * It provides utilities and a default structure (header, subheader, body, footer).
 */
abstract class BaseModule extends DashboardModule {

	protected const BASE_CSS_CLASS = 'growthexperiments-homepage-module';
	protected const MODULE_STATE_COMPLETE = 'complete';
	protected const MODULE_STATE_INCOMPLETE = 'incomplete';
	protected const MODULE_STATE_ACTIVATED = 'activated';
	protected const MODULE_STATE_UNACTIVATED = 'unactivated';
	protected const MODULE_STATE_NOEMAIL = 'noemail';
	protected const MODULE_STATE_UNCONFIRMED = 'unconfirmed';
	protected const MODULE_STATE_NOTRENDERED = 'notrendered';

	/**
	 * @var AbstractExperimentManager
	 */
	private $experimentUserManager;

	/** @var Config */
	private $wikiConfig;

	/**
	 * @var bool
	 */
	private $shouldWrapModuleWithLink;

	/**
	 * @var string
	 */
	private $pageURL = null;

	/**
	 * @param string $name Name of the module
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 * @param AbstractExperimentManager $experimentUserManager
	 * @param bool $shouldWrapModuleWithLink
	 */
	public function __construct(
		string $name,
		IContextSource $ctx,
		Config $wikiConfig,
		AbstractExperimentManager $experimentUserManager,
		bool $shouldWrapModuleWithLink = true
	) {
		parent::__construct( $name, $ctx );

		$this->wikiConfig = $wikiConfig;
		$this->experimentUserManager = $experimentUserManager;
		$this->shouldWrapModuleWithLink = $shouldWrapModuleWithLink;
	}

	/**
	 * Sets the page base URL where the module is being rendered.
	 * Can be later used for generating links from inside the module.
	 */
	public function setPageURL( string $url ): void {
		$this->pageURL = $url;
	}

	/**
	 * Gets the base page URL where the module is being rendered.
	 * @return string|null
	 */
	public function getPageURL(): ?string {
		return $this->pageURL;
	}

	/**
	 * Gets whether the module will be wrapped in a link to its
	 * full screen view or not
	 */
	public function shouldWrapModuleWithLink(): bool {
		return $this->shouldWrapModuleWithLink;
	}

	/**
	 * Get an array of data needed by the Javascript code related to this module.
	 * The data will be available in the 'homepagemodules' JS configuration field, keyed by module name.
	 * Keys currently in use:
	 * - html: module HTML
	 * - overlay: mobile overlay HTML
	 * - rlModules: ResourceLoader modules this module depends on
	 * - heading: module header text
	 * 'html' is only present when the module supports dynamic loading, 'overlay' and 'heading'
	 * in mobile summary/overlay mode, and 'rlModules' in both cases.
	 *
	 * @param string $mode One of RENDER_DESKTOP, RENDER_MOBILE_SUMMARY, RENDER_MOBILE_DETAILS
	 * @return array
	 */
	public function getJsData( $mode ) {
		if ( !$this->supports( $mode ) ) {
			return [];
		}

		$data = [];
		if ( $this->canRender()
			&& $mode == self::RENDER_MOBILE_SUMMARY
		) {
			$this->setMode( self::RENDER_MOBILE_DETAILS_OVERLAY );
			$data = [
				'overlay' => $this->renderMobileDetailsForOverlay(),
				'rlModules' => $this->getModules(),
				'heading' => $this->getHeaderText(),
			];
		}
		$this->setMode( $mode );
		$data[ 'renderMode' ] = $mode;
		return $data;
	}

	/**
	 * @return string HTML rendering for overlay. Same as mobile details but without header.
	 */
	protected function renderMobileDetailsForOverlay() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ),
			$this->buildSection( 'body', $this->getBody() ),
			$this->buildSection( 'footer', $this->getFooter() )
		);
	}

	final protected function getGrowthWikiConfig(): Config {
		return $this->wikiConfig;
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return [ 'oojs-ui.styles.icons-movement' ];
	}

	/**
	 * Override this function to provide the state of this module. It will
	 * be included in 'state' for all HomepageModule events.
	 *
	 * @return string
	 */
	public function getState() {
		return '';
	}

	/**
	 * Override this function to provide the action data of this module. It will
	 * be included in 'action_data' for HomepageModule events.
	 *
	 * @return array
	 */
	protected function getActionData() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	protected function buildModuleWrapper( string ...$sections ): string {
		$moduleContent = Html::rawElement(
			'div',
			[
				'class' => [
					self::BASE_CSS_CLASS,
					self::BASE_CSS_CLASS . '-' . $this->name,
					self::BASE_CSS_CLASS . '-' . $this->getMode(),
					self::BASE_CSS_CLASS . '-user-variant-' . $this->getUserVariant(),
					...$this->getCssClasses(),
				],
				'data-module-name' => $this->name,
				'data-mode' => $this->getMode(),
			],
			implode( "\n", $sections )
		);

		if (
			$this->getMode() === self::RENDER_MOBILE_SUMMARY &&
			$this->supports( self::RENDER_MOBILE_DETAILS ) &&
			$this->shouldWrapModuleWithLink()
		) {
			return Html::rawElement( 'a', [
				'href' => $this->getPageURL() . '/' . $this->getName(),
				'data-overlay-route' => $this->getModuleRoute()
			], $moduleContent );
		}

		return $moduleContent;
	}

	protected function outputDependencies() {
		parent::outputDependencies();

		$out = $this->getContext()->getOutput();
		$out->addModuleStyles( [
			'ext.growthExperiments.Homepage.styles',
			'ext.growthExperiments.icons'
		] );
		$out->addJsConfigVars( [
			'wgGEHomepageModuleState-' . $this->getName() => $this->getState(),
			'wgGEHomepageModuleActionData-' . $this->getName() => $this->getActionData(),
		] );
	}

	/**
	 * The component for mw.router to use when routing clicks from mobile
	 * summary HTML. If this is an empty string, no routing occurs.
	 */
	protected function getModuleRoute(): string {
		return '#/homepage/' . $this->name;
	}

	private function getUserVariant(): string {
		return $this->experimentUserManager->getVariant( $this->getContext()->getUser() );
	}

}
