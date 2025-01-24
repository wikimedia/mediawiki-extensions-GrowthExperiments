<?php

namespace GrowthExperiments\DashboardModule;

use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use OOUI\IconWidget;
use Wikimedia\Message\MessageSpecifier;

abstract class DashboardModule implements IDashboardModule {
	/**
	 * Override this to change base CSS class used with the elements
	 */
	protected const BASE_CSS_CLASS = 'growthexperiments-dashboard-module';

	/**
	 * Modes that are supported by this module.Subclasses that don't support certain modes should
	 * override this to list only the modes they support. For more granular control, override
	 * supports() instead.
	 * @var string[]
	 */
	protected static $supportedModes = [
		self::RENDER_DESKTOP,
		self::RENDER_MOBILE_SUMMARY,
		self::RENDER_MOBILE_DETAILS
	];

	/** @var IContextSource */
	private $ctx;

	/** @var string Name of the module */
	protected $name;

	/** @var string Rendering mode (one of RENDER_* constants) */
	private $mode;

	/**
	 * @param string $name
	 * @param IContextSource $ctx
	 */
	public function __construct(
		$name,
		IContextSource $ctx
	) {
		$this->name = $name;
		$this->ctx = $ctx;
	}

	final protected function getContext(): IContextSource {
		return $this->ctx;
	}

	/**
	 * Get current user
	 *
	 * Short for $this->getContext()->getUser().
	 */
	final protected function getUser(): User {
		return $this->getContext()->getUser();
	}

	/**
	 * Shortcut to get main config object
	 *
	 * Short for $this->getContext()->getConfig().
	 */
	final protected function getConfig(): Config {
		return $this->getContext()->getConfig();
	}

	/**
	 * @return string Rendering mode (one of RENDER_* constants)
	 */
	final protected function getMode(): string {
		return $this->mode;
	}

	final protected function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $mode Rendering mode (one of RENDER_* constants)
	 */
	protected function setMode( string $mode ) {
		$this->mode = $mode;
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
	 * Override this function to provide module styles that need to be
	 * loaded in the <head> for this module.
	 *
	 * @return string[] Name of the module(s) to load
	 */
	protected function getModuleStyles() {
		return [];
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
	 * Override this function to add additional CSS classes to the top-level
	 * <div> of this module.
	 *
	 * @return string[] Additional CSS classes
	 */
	protected function getCssClasses() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function supports( $mode ) {
		return in_array( $mode, static::$supportedModes );
	}

	/**
	 * @inheritDoc
	 */
	public function getJsData( $mode ) {
		return [];
	}

	/**
	 * Override this function to provide JS config vars needed by this module.
	 *
	 * @return array
	 */
	protected function getJsConfigVars() {
		return [];
	}

	protected function outputDependencies() {
		$out = $this->getContext()->getOutput();
		$out->addModuleStyles( $this->getModuleStyles() );
		$out->addModules( $this->getModules() );
		$out->addJsConfigVars( $this->getJsConfigVars() );
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
	 * Get the module HTML for current mode
	 *
	 * @return string
	 */
	protected function getHtml() {
		if ( $this->mode === self::RENDER_DESKTOP ) {
			$html = $this->renderDesktop();
		} elseif ( $this->mode === self::RENDER_MOBILE_SUMMARY ) {
			$html = $this->renderMobileSummary();
		} elseif ( $this->mode === self::RENDER_MOBILE_DETAILS ) {
			$html = $this->renderMobileDetails();
		} else {
			throw new InvalidArgumentException( 'Invalid rendering mode: ' . $this->mode );
		}
		return $html;
	}

	/**
	 * @param string ...$sections
	 * @return string
	 */
	protected function buildModuleWrapper( ...$sections ) {
		return Html::rawElement(
			'div',
			[
				'class' => array_merge( [
					static::BASE_CSS_CLASS,
					static::BASE_CSS_CLASS . '-' . $this->name,
					static::BASE_CSS_CLASS . '-' . $this->getMode()
				], $this->getCssClasses() ),
				'data-module-name' => $this->name,
				'data-mode' => $this->getMode()
			],
			implode( "\n", $sections )
		);
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
					static::BASE_CSS_CLASS . '-section',
					static::BASE_CSS_CLASS . '-section-' . $name,
					static::BASE_CSS_CLASS . '-' . $name
				]
			],
			$content
		) : '';
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
	 * @return string HTML element containing the header text.
	 */
	protected function getHeaderTextElement() {
		return Html::element(
			'div',
			[ 'class' => static::BASE_CSS_CLASS . '-header-text' ],
			$this->getHeaderText()
		);
	}

	/**
	 * Override this function to provide the header text
	 *
	 * @return string
	 */
	abstract protected function getHeaderText();

	/**
	 * Override this function to change the default header tag.
	 *
	 * @return string Tag to use with the header, eg. h2, h3, h4, ...
	 */
	protected function getHeaderTag() {
		return 'h2';
	}

	/**
	 * Implement this function to provide the module header.
	 *
	 * @return string HTML content of the header. Will be wrapped in a section.
	 */
	protected function getHeader() {
		$html = '';
		if ( $this->shouldHeaderIncludeIcon() ) {
			$html .= $this->getHeaderIcon(
				$this->getHeaderIconName(),
				$this->shouldInvertHeaderIcon()
			);
		}
		$html .= $this->getHeaderTextElement();
		return $html;
	}

	/**
	 * Implement this function to provide the module body.
	 *
	 * @return string HTML content of the body
	 */
	abstract protected function getBody();

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

	private function getBackIcon(): string {
		return Html::rawElement(
			'a',
			[
				'href' => SpecialPage::getTitleFor( 'Homepage' )->getLinkURL(),
			],
			new IconWidget( [
				'icon' => 'arrowPrevious',
				'classes' => [ static::BASE_CSS_CLASS . '-header-back-icon' ],
			] )
		);
	}

	/**
	 * @return IconWidget The navigation icon.
	 */
	protected function getNavIcon() {
		return new IconWidget( [
			'icon' => 'arrowNext',
			'classes' => [ static::BASE_CSS_CLASS . '-header-nav-icon' ],
		] );
	}

	/**
	 * Implement this function to provide the module body
	 * when rendered as a mobile summary.
	 *
	 * @return string HTML content of the body
	 */
	abstract protected function getMobileSummaryBody();

	/**
	 * Provide optional subheader for the module
	 *
	 * @return string HTML content of the subheader
	 */
	protected function getSubheader() {
		return $this->getSubheaderTextElement();
	}

	/**
	 * Override this function to provide an optional subheader for the module
	 *
	 * @return string Text content of the subheader
	 */
	protected function getSubheaderText() {
		return '';
	}

	/**
	 * @return string HTML element containing the header text.
	 */
	protected function getSubheaderTextElement() {
		$text = $this->getSubheaderText();
		return $text ? Html::element(
			'div',
			[ 'class' => static::BASE_CSS_CLASS . '-subheader-text' ],
			$text
		) : '';
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
	 * @param string $name Name of the icon
	 * @param bool $invert Whether the icon should be inverted
	 * @return IconWidget
	 */
	protected function getHeaderIcon( $name, $invert ) {
		$defaultIconClasses = [
			self::BASE_CSS_CLASS . '-header-icon',
			'icon-' . $name
		];
		$invertClasses = $invert ?
			[ 'oo-ui-image-invert', 'oo-ui-checkboxInputWidget-checkIcon' ] :
			[];

		return new IconWidget( [
			'icon' => $name,
			// HACK: IconWidget doesn't let us set 'invert' => true, and setting
			// 'classes' => [ 'oo-ui-image-invert' ] doesn't work either, because
			// Theme::getElementClasses() will unset it again. So instead, trick that code into
			// thinking this is a checkbox icon, which will cause it to invert the icon
			'classes' => array_merge( $defaultIconClasses, $invertClasses )
		] );
	}

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
	 * Override this method if header should include the icon
	 *
	 * No styles provided by default! Remember to position the icon manually via CSS.
	 *
	 * @return bool Should header include the icon?
	 */
	protected function shouldHeaderIncludeIcon(): bool {
		return false;
	}

	/**
	 * Alias for MessageLocalizer::msg
	 *
	 * @param string|string[]|MessageSpecifier $key
	 * @param mixed ...$params
	 * @return Message
	 * @see MessageLocalizer::msg()
	 */
	protected function msg( $key, ...$params ) {
		return $this->getContext()->msg( $key, ...$params );
	}
}
