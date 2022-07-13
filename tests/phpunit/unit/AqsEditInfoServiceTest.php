<?php

namespace GrowthExperiments;

use HashBagOStuff;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use StatusValue;
use WANObjectCache;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\AqsEditInfoService
 */
class AqsEditInfoServiceTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideGetEditsPerDay
	 * @param int|StatusValue $expected
	 * @param array|Status $httpResponse Error status or JSON data
	 */
	public function testGetEditsPerDay( $expected, $httpResponse ) {
		$cache = new HashBagOStuff();
		$wanObjectCache = new WANObjectCache( [ 'cache' => $cache ] );

		ConvertibleTimestamp::setFakeTime( '2000-02-10 12:00:00' );
		$httpRequestFactory = $this->getMockRequestFactory( 'https://wikimedia.org/api/rest_v1/'
			. 'metrics/edits/aggregate/en.wikipedia/user/content/daily/19991210/19991211',
			$httpResponse instanceof Status ? $httpResponse : json_encode( $httpResponse ) );
		$editInfoService = new AqsEditInfoService(
			$httpRequestFactory,
			$wanObjectCache,
			'en.wikipedia'
		);
		$actual = $editInfoService->getEditsPerDay();
		if ( is_int( $expected ) ) {
			$this->assertIsInt( $actual );
			$this->assertSame( $expected, $actual );
		} else {
			$this->assertInstanceOf( StatusValue::class, $actual );
			/** @var $expected StatusValue */
			/** @var $actual StatusValue */
			$this->assertSame( $expected->getErrors(), $actual->getErrors() );
		}
		// Test caching. The once() call in getMockRequestFactory ensures the cache is actually used.
		$actual2 = $editInfoService->getEditsPerDay();
		$this->assertSame( $actual, $actual2 );
		$this->assertCount( 1, TestingAccessWrapper::newFromObject( $cache )->bag );
	}

	public function provideGetEditsPerDay() {
		$error = Status::newFatal( 'foo' );
		return [
			[ 10, [ 'items' => [ [ 'results' => [ [ 'edits' => 10 ] ] ] ] ] ],
			[ $error, $error ],
		];
	}

	/**
	 * @param string $expectedUrl
	 * @param string|Status $result A content string or an error status.
	 * @return HttpRequestFactory|MockObject
	 */
	protected function getMockRequestFactory( $expectedUrl, $result ) {
		$content = null;
		if ( !( $result instanceof Status ) ) {
			$content = $result;
			$result = Status::newGood();
		}

		$request = $this->createNoOpMock( MWHttpRequest::class, [ 'execute', 'getContent' ] );
		$request->method( 'execute' )->willReturn( $result );
		$request->method( 'getContent' )->willReturn( $content );

		$requestFactory = $this->createNoOpMock( HttpRequestFactory::class, [ 'create', 'getUserAgent' ] );
		$requestFactory->method( 'getUserAgent' )->willReturn( 'Foo' );
		$requestFactory->expects( $this->once() )
			->method( 'create' )
			->with( $expectedUrl, $this->anything(), $this->anything() )
			->willReturn( $request );
		return $requestFactory;
	}

}
