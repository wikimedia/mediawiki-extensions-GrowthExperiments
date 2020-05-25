<?php

namespace GrowthExperiments\Tests\Unit\HelpPanel\Tips;

use GrowthExperiments\HelpPanel\Tips\Renderer\TipRendererInterface;
use GrowthExperiments\HelpPanel\Tips\Tip;
use GrowthExperiments\HelpPanel\Tips\TipConfig;
use GrowthExperiments\HelpPanel\Tips\TipRenderParameters;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\HelpPanel\Tips\Tip
 */
class TipTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( Tip::class, new Tip(
			$this->getTipConfigMock(),
			$this->getTipRendererMock()
		) );
	}

	/**
	 * @covers ::render
	 */
	public function testRender() {
		$renderer = $this->getTipRendererMock();
		$html = '<div>Foo</div>';
		$renderer->method( 'render' )
			->willReturn( $html );
		$tip = new Tip( $this->getTipConfigMock(), $renderer );
		$renderParameters = $this->getMockBuilder( TipRenderParameters::class )
			->disableOriginalConstructor()
			->getMock();
		$this->assertSame( $html, $tip->render( $renderParameters ) );
	}

	/**
	 * @covers ::getConfig
	 */
	public function testGetConfig() {
		$tipConfig = $this->getTipConfigMock();
		$tipConfig->method( 'getMessageKey' )
			->willReturn( 'foo' );
		$tip = new Tip( $tipConfig, $this->getTipRendererMock() );
		$this->assertSame( 'foo', $tip->getConfig()->getMessageKey() );
	}

	/**
	 * @covers ::factory
	 */
	public function testFactory() {
		$this->assertInstanceOf(
			Tip::class,
			Tip::factory(
				$this->getMockBuilder( TipConfig::class )
					->disableOriginalConstructor()
					->getMock(),
				$this->getMockBuilder( TipRendererInterface::class )
					->getMock()
			)
		);
	}

	private function getTipConfigMock() {
		return $this->getMockBuilder( TipConfig::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function getTipRendererMock() {
		return $this->getMockBuilder( TipRendererInterface::class )
			->disableOriginalConstructor()
			->getMock();
	}
}
