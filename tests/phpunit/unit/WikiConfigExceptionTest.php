<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\WikiConfigException;
use MediaWikiUnitTestCase;
use Wikimedia\NormalizedException\NormalizedException;

/**
 * @coversDefaultClass \GrowthExperiments\WikiConfigException
 */
class WikiConfigExceptionTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruction() {
		$exception = new WikiConfigException( 'Foo' );
		$this->assertInstanceOf( WikiConfigException::class, $exception );
		$this->assertInstanceOf( NormalizedException::class, $exception );
	}

}
