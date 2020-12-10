<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModule;
use Html;
use IContextSource;
use InvalidArgumentException;
use OOUI\IconWidget;
use SpecialPage;

/**
 * BaseModule is a base class for homepage modules.
 * It provides utilities and a default structure (header, subheader, body, footer).
 *
 * @package GrowthExperiments\HomepageModules
 */
abstract class BaseModule implements HomepageModule {

	private const BASE_CSS_CLASS = 'growthexperiments-homepage-module';
	protected const MODULE_STATE_COMPLETE = 'complete';
	protected const MODULE_STATE_INCOMPLETE = 'incomplete';
	protected const MODULE_STATE_ACTIVATED = 'activated';
	protected const MODULE_STATE_UNACTIVATED = 'unactivated';
	protected const MODULE_STATE_NOEMAIL = 'noemail';
	protected const MODULE_STATE_UNCONFIRMED = 'unconfirmed';
	protected const MODULE_STATE_CONFIRMED = 'confirmed';

	/**
	 * Modes that are supported by this module.Subclasses that don't support certain modes should
	 * override this to list only the modes they support. For more granular control, override
	 * supports() instead.
	 * @var string[]
	 */
	protected static $supportedModes = [
		HomepageModule::RENDER_DESKTOP,
		HomepageModule::RENDER_MOBILE_SUMMARY,
		HomepageModule::RENDER_MOBILE_DETAILS
	];

	/**
	 * @var IContextSource
	 */
	private $ctx;

	/**
	 * @var string Name of the module
	 */
	private $name;

	/**
	 * @var int Current rendering mode
	 */
	private $mode;
	/**
	 * @var ExperimentUserManager
	 */
	private $experimentUserManager;

	/**
	 * @param string $name Name of the module
	 * @param IContextSource $ctx
	 * @param ExperimentUserManager $experimentUserManager
	 */
	public function __construct( $name, IContextSource $ctx, ExperimentUserManager $experimentUserManager ) {
		$this->name = $name;
		$this->ctx = $ctx;
		$this->experimentUserManager = $experimentUserManager;
	}

	/**
	 * @inheritDoc
	 */
	public function render( $mode ) {
		if ( !$this->supports( $mode ) ) {
			return '';
		}
		$this->setMode( $mode );
		if ( !$this->shouldRender() ) {
			return '';
		}

		$this->outputDependencies();
		return $this->getHtml();
	}

	/**
	 * @inheritDoc
	 */
	public function getJsData( $mode ) {
		if ( !$this->supports( $mode ) ) {
			return [];
		}

		$data = [];
		if ( $this->canRender()
			&& $mode == HomepageModule::RENDER_MOBILE_SUMMARY
		) {
			$this->setMode( HomepageModule::RENDER_MOBILE_DETAILS_OVERLAY );
			$data = [
				'overlay' => $this->renderMobileDetailsForOverlay(),
				'rlModules' => $this->getModules(),
				'heading' => $this->getHeaderText(),
			];
		}
		$this->setMode( $mode );
		return $data;
	}

	/**
	 * @inheritDoc
	 */
	public function supports( $mode ) {
		return in_array( $mode, static::$supportedModes );
	}

	/**
	 * Get the module HTML in the given mode.
	 *
	 * @return string Html rendering of the module
	 */
	protected function getHtml() {
		if ( $this->mode === HomepageModule::RENDER_DESKTOP ) {
			$html = $this->renderDesktop();
		} elseif ( $this->mode === HomepageModule::RENDER_MOBILE_SUMMARY ) {
			$html = $this->renderMobileSummary();
		} elseif ( $this->mode === HomepageModule::RENDER_MOBILE_DETAILS ) {
			$html = $this->renderMobileDetails();
		} else {
			throw new InvalidArgumentException( 'Invalid rendering mode: ' . $this->mode );
		}
		return $html;
	}

	/**
	 * @return string HTML rendering for desktop.
	 */
	protected function renderDesktop() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ),
			$this->buildSection( 'body', $this->getBody() ),
			$this->buildSection( 'footer', $this->getFooter() )
		);
	}

	/**
	 * @return string HTML rendering for mobile summary.
	 */
	protected function renderMobileSummary() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getMobileSummaryHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'body', $this->getMobileSummaryBody() )
		);
	}

	/**
	 * @return string HTML rendering for mobile details.
	 */
	protected function renderMobileDetails() {
		return $this->buildModuleWrapper(
			$this->buildSection( 'header', $this->getMobileDetailsHeader(), $this->getHeaderTag() ),
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ),
			$this->buildSection( 'body', $this->getBody() ),
			$this->buildSection( 'footer', $this->getFooter() )
		);
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

	/**
	 * @return IContextSource Current context
	 */
	final protected function getContext() {
		return $this->ctx;
	}

	/**
	 * @return string Current rendering mode (one of the HomepageModule::RENDER_* constants).
	 */
	final protected function getMode() {
		return $this->mode;
	}

	/**
	 * @param string $mode Rendering mode
	 */
	protected function setMode( $mode ) {
		$this->mode = $mode;
	}

	/**
	 * Implement this function to provide the module header.
	 *
	 * @return string HTML content of the header. Will be wrapped in a section.
	 */
	protected function getHeader() {
		return $this->getHeaderTextElement();
	}

	private function getBackIcon() {
		return Html::rawElement(
			'a',
			[
				'href' => SpecialPage::getTitleFor( 'Homepage' )->getLinkURL(),
			],
			new IconWidget( [
				'icon' => 'arrowPrevious',
				'classes' => [ self::BASE_CSS_CLASS . '-header-back-icon' ],
			] )
		);
	}

	/**
	 * @return string HTML element containing the header text.
	 */
	protected function getHeaderTextElement() {
		return Html::element(
			'div',
			[ 'class' => self::BASE_CSS_CLASS . '-header-text' ],
			$this->getHeaderText()
		);
	}

	/**
	 * @return IconWidget The navigation icon.
	 */
	protected function getNavIcon() {
		return new IconWidget( [
			'icon' => 'arrowNext',
			'classes' => [ self::BASE_CSS_CLASS . '-header-nav-icon' ],
		] );
	}

	/**
	 * @param string $name Name of the icon
	 * @param bool $invert Whether the icon should be inverted
	 * @return string HTML
	 */
	protected function getHeaderIcon( $name, $invert ) {
		return Html::rawElement(
			'div',
			[ 'class' => self::BASE_CSS_CLASS . '-header-icon' ],
			new IconWidget( [
				'icon' => $name,
				// HACK: IconWidget doesn't let us set 'invert' => true, and setting
				// 'classes' => [ 'oo-ui-image-invert' ] doesn't work either, because
				// Theme::getElementClasses() will unset it again. So instead, trick that code into
				// thinking this is a checkbox icon, which will cause it to invert the icon
				'classes' => $invert ?
					[ 'oo-ui-image-invert', 'oo-ui-checkboxInputWidget-checkIcon' ] :
					[]
			] )
		);
	}

	/**
	 * @return string HTML string to be used as header of the mobile summary.
	 */
	protected function getMobileSummaryHeader() {
		return $this->getHeaderTextElement() . $this->getNavIcon();
	}

	/**
	 * @return string HTML string to be used as header of the mobile details.
	 */
	protected function getMobileDetailsHeader() {
		$icon = $this->getBackIcon();
		$text = $this->getHeaderTextElement();
		return $icon . $text;
	}

	/**
	 * Override this function to provide the header text.
	 *
	 * @return string
	 */
	abstract protected function getHeaderText();

	/**
	 * Override this function to provide the name of the header icon.
	 *
	 * @return string
	 */
	abstract protected function getHeaderIconName();

	/**
	 * @return bool Whether the header icon should be inverted.
	 */
	protected function shouldInvertHeaderIcon() {
		return false;
	}

	/**
	 * Override this function to change the default header tag.
	 *
	 * @return string Tag to use with the header, e.g. h2, h3, h4
	 */
	protected function getHeaderTag() {
		return 'h2';
	}

	/**
	 * Implement this function to provide the module body.
	 *
	 * @return string HTML content of the body
	 */
	abstract protected function getBody();

	/**
	 * Implement this function to provide the module body
	 * when rendered as a mobile summary.
	 *
	 * @return string HTML content of the body
	 */
	abstract protected function getMobileSummaryBody();

	/**
	 * Override this function to provide an optional module subheader.
	 *
	 * @return string HTML content of the subheader
	 */
	protected function getSubheader() {
		return '';
	}

	/**
	 * Override this function to change the default subheader tag.
	 *
	 * @return string Tag to use with the subheader, e.g. h2, h3, h4
	 */
	protected function getSubheaderTag() {
		return 'h3';
	}

	/**
	 * Override this function to provide an optional module footer.
	 *
	 * @return string HTML content of the footer
	 */
	protected function getFooter() {
		return '';
	}

	/**
	 * Override this function to provide module styles that need to be
	 * loaded in the <head> for this module.
	 *
	 * @return string[] Name of the module(s) to load
	 */
	protected function getModuleStyles() {
		return [ 'oojs-ui.styles.icons-movement' ];
	}

	/**
	 * Override this function to provide modules that need to be
	 * loaded for this module.
	 *
	 * @return string[] Name of the module(s) to load
	 */
	protected function getModules() {
		return [];
	}

	/**
	 * Whether the module can be rendered or not.
	 * When this returns false, callers should never attempt to render the module.
	 * @return bool
	 */
	protected function canRender() {
		return true;
	}

	/**
	 * Whether the module is supposed to be present on the homepage.
	 * When canRender() is true but shouldRender() is false, the module should not be displayed,
	 * but callers can choose to pre-render the module to display it dynamically without delay
	 * when it becames enabled.
	 * @return bool
	 */
	protected function shouldRender() {
		return $this->canRender();
	}

	/**
	 * Override this function to add additional CSS classes to the top-level
	 * <div> of this module.
	 *
	 * @return string[] Additional CSS classes
	 */
	protected function getCssClasses() {
		return [];
	}

	/**
	 * Build a module section.
	 *
	 * $content is HTML, do not pass plain text. Use ->escaped() or ->parse() for messages.
	 *
	 * @param string $name Name of the section, used to generate a class
	 * @param string $content HTML content of the section
	 * @param string $tag HTML tag to use for the section
	 * @return string
	 */
	protected function buildSection( $name, $content, $tag = 'div' ) {
		return $content ? Html::rawElement(
			$tag,
			[
				'class' => [
					self::BASE_CSS_CLASS . '-section',
					self::BASE_CSS_CLASS . '-section-' . $name,
					self::BASE_CSS_CLASS . '-' . $name
				],
			],
			$content
		) : '';
	}

	/**
	 * Override this function to provide JS config vars needed by this module.
	 *
	 * @return array
	 */
	protected function getJsConfigVars() {
		return [];
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

	private function buildModuleWrapper( ...$sections ) {
		return Html::rawElement(
			'div',
			[
				'class' => array_merge( [
					self::BASE_CSS_CLASS,
					self::BASE_CSS_CLASS . '-' . $this->name,
					self::BASE_CSS_CLASS . '-' . $this->getMode(),
					self::BASE_CSS_CLASS . '-user-variant-' . $this->getUserVariant()
				], $this->getCssClasses() ),
				'data-module-name' => $this->name,
				'data-module-route' => $this->getModuleRoute(),
				'data-mode' => $this->getMode(),
			],
			implode( "\n", $sections )
		);
	}

	protected function outputDependencies() {
		$out = $this->getContext()->getOutput();
		$out->addModuleStyles( [
			'ext.growthExperiments.Homepage.styles',
			'ext.growthExperiments.Homepage.icons'
		] );
		$out->addModuleStyles( $this->getModuleStyles() );
		$out->addModules( $this->getModules() );
		$out->addJsConfigVars( array_merge( $this->getJsConfigVars(), [
			'wgGEHomepageModuleState-' . $this->name => $this->getState(),
			'wgGEHomepageModuleActionData-' . $this->name => $this->getActionData(),
		] ) );
	}

	/**
	 * The component for mw.router to use when routing clicks from mobile
	 * summary HTML. If this is an empty string, no routing occurs.
	 *
	 * @return string
	 */
	protected function getModuleRoute() : string {
		return '#/homepage/' . $this->name;
	}

	/**
	 * @return string
	 */
	private function getUserVariant() : string {
		return $this->experimentUserManager->getVariant( $this->getContext()->getUser() );
	}

}
