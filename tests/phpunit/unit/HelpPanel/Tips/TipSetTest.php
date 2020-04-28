<?php

namespace GrowthExperiments\Tests\Unit\HelpPanel\Tips;

use GrowthExperiments\HelpPanel\Tips\ParameterMapper;
use GrowthExperiments\HelpPanel\Tips\Renderer\DefaultTipRenderer;
use GrowthExperiments\HelpPanel\Tips\Renderer\TipRendererInterface;
use GrowthExperiments\HelpPanel\Tips\Tip;
use GrowthExperiments\HelpPanel\Tips\TipConfig;
use GrowthExperiments\HelpPanel\Tips\TipRenderException;
use GrowthExperiments\HelpPanel\Tips\TipRenderParameters;
use GrowthExperiments\HelpPanel\Tips\TipSet;
use MediaWikiUnitTestCase;
use Wikimedia\Assert\ParameterElementTypeException;

/**
 * @coversDefaultClass \GrowthExperiments\HelpPanel\Tips\TipSet
 */
class TipSetTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( TipSet::class, new TipSet( '1', [] ) );
		$this->assertInstanceOf( TipSet::class, new TipSet( '1', [
			$this->getTipMock()
		] ) );
	}

	/**
	 * @covers ::__construct
	 */
	public function testInvalidConstruct() {
		$this->expectException( ParameterElementTypeException::class );
		new TipSet( '1', [ new \stdClass() ] );
	}

	/**
	 * @covers ::getStep
	 */
	public function testGetStep() {
		$tipSet = new TipSet( '42', [ $this->getTipMock() ] );
		$this->assertSame( '42', $tipSet->getStep() );
	}

	/**
	 * @covers ::getIterator
	 */
	public function testGetIterator() {
		$tipSet = new TipSet( '42', [
			$this->getTipMock(),
			$this->getTipMock()
		] );
		$iterator = $tipSet->getIterator();
		$this->assertSame( 2, $iterator->count() );
	}

	/**
	 * @covers ::render
	 */
	public function testRender() {
		$mapper = $this->getMockBuilder( ParameterMapper::class )
			->disableOriginalConstructor()
			->getMock();
		$tipRenderParameters = new TipRenderParameters( 'foo' );
		$mapper->method( 'getParameters' )
			->willReturn( $tipRenderParameters );
		$tipConfig = new TipConfig( 'test', 'foo', 'bar', 'vector', 'copyedit' );
		$contextMock = $this->getMockBuilder( \RequestContext::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock = $this->getMockBuilder( \Message::class )
			->disableOriginalConstructor()
			->getMock();
		$messageMock->method( 'parse' )
			->willReturn( '<div>foo</div>' );
		$contextMock->method( 'msg' )
			->willReturn( $messageMock );
		$tip = new Tip(
			$tipConfig,
			new DefaultTipRenderer( $tipConfig, $contextMock )
		);
		$tipMock = $this->getTipMock();
		$tipMock->method( 'render' )
			->willReturn( '<div>foo</div>' );
		$tipSet = new TipSet( '1', [ $tip ] );
		$this->assertSame(
			'<div class="growthexperiments-quickstart-tips-tip ' .
			'growthexperiments-quickstart-tips-tip-test">' .
			'<div>foo</div></div>',
			current( $tipSet->render( $mapper ) )
		);
	}

	/**
	 * @covers ::render
	 */
	public function testRenderException() {
		$tipRenderer = $this->getMockBuilder( TipRendererInterface::class )
			->disableOriginalConstructor()
			->getMock();
		$tipRenderer->method( 'render' )
			->willThrowException( new TipRenderException() );
		$tipSet = new TipSet( '1', [ new Tip(
			$this->getMockBuilder( TipConfig::class )
				->disableOriginalConstructor()
				->getMock(),
			$tipRenderer
		) ] );
		$this->assertSame( [], $tipSet->render(
			$this->getMockBuilder( ParameterMapper::class )
				->disableOriginalConstructor()
				->getMock()
		) );
	}

	private function getTipMock() {
		return $this->getMockBuilder( Tip::class )
			->disableOriginalConstructor()
			->getMock();
	}
}
