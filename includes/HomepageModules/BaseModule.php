<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\HomepageModule;
use Html;
use IContextSource;

/**
 * Class BaseModule is a base class for a small homepage module
 * typically displayed in the sidebar.
 *
 * @package GrowthExperiments\HomepageModules
 */
abstract class BaseModule implements HomepageModule {

	const BASE_CSS_CLASS = 'growthexperiments-homepage-module';

	/**
	 * @var IContextSource
	 */
	private $ctx;

	/**
	 * @var string Name of the module
	 */
	private $name;

	/**
	 * @param string $name Name of the module
	 * @param IContextSource $ctx
	 */
	public function __construct( $name, IContextSource $ctx ) {
		$this->name = $name;
		$this->ctx = $ctx;
	}

	/**
	 * @inheritDoc
	 */
	public function render() {
		if ( !$this->canRender() ) {
			return '';
		}

		$out = $this->getContext()->getOutput();
		$out->addModuleStyles( 'ext.growthExperiments.Homepage.BaseModule.styles' );
		$out->addModuleStyles( $this->getModuleStyles() );
		$out->addModules( $this->getModules() );
		$out->addJsConfigVars( $this->getJsConfigVars() );
		$out->addJsConfigVars( [ 'wgGEHomepageModuleState-' . $this->name => $this->getState() ] );
		return Html::rawElement(
			'div',
			[
				'class' => array_merge( [
					self::BASE_CSS_CLASS,
					self::BASE_CSS_CLASS . '-' . $this->name,
				], $this->getCssClasses() ),
				'data-module-name' => $this->name,
			],
			$this->buildSection( 'header', $this->getHeader(), $this->getHeaderTag() ) .
			$this->buildSection( 'subheader', $this->getSubheader(), $this->getSubheaderTag() ) .
			$this->buildSection( 'body', $this->getBody() ) .
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
	 * Implement this function to provide the module header.
	 *
	 * @return string Text or HTML content of the header
	 */
	abstract protected function getHeader();

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
	 * @return string Text or HTML content of the body
	 */
	abstract protected function getBody();

	/**
	 * Override this function to provide an optional module subheader.
	 *
	 * @return string Text or HTML content of the subheader
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
	 * @return string Text or HTML content of the footer
	 */
	protected function getFooter() {
		return '';
	}

	/**
	 * Override this function to provide module styles that need to be
	 * loaded in the <head> for this module.
	 *
	 * @return string|string[] Name of the module(s) to load
	 */
	protected function getModuleStyles() {
		return '';
	}

	/**
	 * Override this function to provide modules that need to be
	 * loaded for this module.
	 *
	 * @return string|string[] Name of the module(s) to load
	 */
	protected function getModules() {
		return '';
	}

	/**
	 * @return bool Whether the module can be rendered or not.
	 */
	protected function canRender() {
		return true;
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
	 * Build a module section
	 *
	 * @param string $name Name of the section, used to generate a class
	 * @param string $content Text or HTML content of the section
	 * @param string $tag HTML tag to use for the section
	 * @return string
	 */
	protected function buildSection( $name, $content, $tag = 'div' ) {
		return $content ? Html::rawElement(
			$tag,
			[
				'class' => [
					self::BASE_CSS_CLASS . '-section',
					self::BASE_CSS_CLASS . '-' . $name,
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
	 * be included in 'action_data' for all HomepageModule events.
	 *
	 * @return array
	 */
	protected function getState() {
		return [];
	}
}
