<?php

namespace GrowthExperiments\Test\Unit\HelpPanel\Tips;

use GrowthExperiments\HelpPanel\Tips\TipRenderParameters;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\HelpPanel\Tips\TipRenderParameters
 */
class TipRenderParametersTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( TipRenderParameters::class,
			new TipRenderParameters( 'foo', [ 'bar' ] )
		);
	}

	/**
	 * @covers ::getMessageKey
	 */
	public function testGetMessageKey() {
		$tipRenderParams = new TipRenderParameters( 'foo' );
		$this->assertSame( 'foo', $tipRenderParams->getMessageKey() );
	}

	/**
	 * @covers ::getExtraParameters
	 */
	public function testGetExtraParameters() {
		$tipRenderParams = new TipRenderParameters( 'foo', [ 'bar' ] );
		$this->assertSame( [ 'bar' ], $tipRenderParams->getExtraParameters() );
	}

}
