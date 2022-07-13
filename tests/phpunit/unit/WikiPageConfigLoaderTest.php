<?php

namespace GrowthExperiments\Tests;

use ApiRawMessage;
use BagOStuff;
use Content;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use GrowthExperiments\Config\Validation\GrowthConfigValidation;
use GrowthExperiments\Config\Validation\NoValidationValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use JsonContent;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiUnitTestCase;
use MessageSpecifier;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use StatusValue;
use Title;
use TitleFactory;
use TitleValue;
use WikitextContent;

/**
 * @covers \GrowthExperiments\Config\WikiPageConfigLoader
 * @covers \GrowthExperiments\Util::getJsonUrl
 * @covers \GrowthExperiments\Util::getRawUrl
 */
class WikiPageConfigLoaderTest extends MediaWikiUnitTestCase {

	/** @var array */
	private $oldWgUrlProtocols;

	protected function setUp(): void {
		// work around wfParseUrl using a global
		global $wgUrlProtocols;
		parent::setUp();
		$this->oldWgUrlProtocols = $wgUrlProtocols;
		$wgUrlProtocols = [ 'http://', 'https://' ];
	}

	protected function tearDown(): void {
		global $wgUrlProtocols;
		parent::tearDown();
		$wgUrlProtocols = $this->oldWgUrlProtocols;
	}

	private function internalTestLoad(
		$titleValue, $isExternal, $localUrl, $fullUrl,
		$httpResponse, $requestFactoryExpectedInvokeCount,
		$lookupResult, $revisionLookupExpectedInvokeCount,
		$expectedData
	) {
		$requestFactory = $this->getMockRequestFactory( $fullUrl, $httpResponse,
			$requestFactoryExpectedInvokeCount );
		$revisionLookup = $this->getMockRevisionLookup( $titleValue, $lookupResult,
			$revisionLookupExpectedInvokeCount );
		$titleFactory = $this->getMockTitleFactory( $fullUrl, $localUrl, $isExternal );
		$configValidator = $this->getMockBuilder( GrowthConfigValidation::class )
			->disableOriginalConstructor()
			->getMock();
		$configValidator
			->expects(
				(
					$expectedData instanceof StatusValue &&
					!$expectedData->isOK()
				) ? $this->never() : $this->once() )
			->method( 'validate' )
			->willReturn( StatusValue::newGood() );
		$configValidatorFactory = $this->getMockBuilder( ConfigValidatorFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$configValidatorFactory
			->method( 'newConfigValidator' )
			->willReturn( $configValidator );
		$loader = new WikiPageConfigLoader(
			$configValidatorFactory,
			$requestFactory,
			$revisionLookup,
			$titleFactory
		);
		$data = $loader->load( $titleValue );
		$this->assertResultSame( $expectedData, $data );

		// call it again to check via the exactly(1) assertions that caching works
		$data = $loader->load( $titleValue );
		$this->assertResultSame( $expectedData, $data );
	}

	/**
	 * @dataProvider provideLoadHttp
	 */
	public function testLoadHttp( $httpResponse, $expectedData ) {
		$titleValue = new TitleValue( NS_MEDIAWIKI, 'MediaWiki:Page.json', '', 'r' );
		$isExternal = true;
		$site = 'http://remote.wiki';
		$localUrl = "/wiki/MediaWiki:Page.json?ctype=raw";
		$fullUrl = "$site$localUrl";

		$this->internalTestLoad( $titleValue, $isExternal, $localUrl, $fullUrl, $httpResponse,
			1, null, 0, $expectedData );
	}

	/**
	 * @dataProvider provideLoadLocal
	 */
	public function testLoadLocal( $lookupResult, $expectedData ) {
		$titleValue = new TitleValue( NS_MEDIAWIKI, 'MediaWiki:Page.json' );
		$isExternal = false;
		$site = 'http://local.wiki';
		$localUrl = "/wiki/MediaWiki:Page.json";
		$fullUrl = $site;

		$this->internalTestLoad( $titleValue, $isExternal, $localUrl, $fullUrl, '',
			0, $lookupResult, 1, $expectedData );
	}

	public function provideLoadHttp() {
		return [
			'success' => [
				'response' => '{ "foo": "bar" }',
				'expected data' => [ 'foo' => 'bar' ],
			],
			'error' => [
				'response' => StatusValue::newFatal( 'foo' ),
				'expected data' => StatusValue::newFatal( 'foo' ),
			],
		];
	}

	public function provideLoadLocal() {
		return [
			'success' => [
				'response' => new JsonContent( '{ "foo": "bar" }' ),
				'expected data' => [ 'foo' => 'bar' ],
			],
			'no such page' => [
				'response' => false,
				'expected data' => StatusValue::newFatal( new ApiRawMessage( 'x',
					'newcomer-tasks-configuration-loader-title-not-found' ) ),
			],
			'revdeleted' => [
				'response' => null,
				'expected data' => StatusValue::newFatal( new ApiRawMessage( 'x',
					'newcomer-tasks-configuration-loader-content-error' ) ),
			],
			'non-json' => [
				'response' => new WikitextContent( 'foo' ),
				'expected data' => StatusValue::newFatal( new ApiRawMessage( 'x',
					'newcomer-tasks-configuration-loader-content-error' ) ),
			],
		];
	}

	public function testSetCache() {
		$cache = $this->getMockBuilder( BagOStuff::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get' ] )
			->getMockForAbstractClass();
		$cache->expects( $this->once() )
			->method( 'get' )
			->willReturn( [] );
		/** @var BagOStuff $cache */

		$title = new TitleValue( NS_MAIN, 'X' );
		$configValidatorFactory = $this->getMockBuilder( ConfigValidatorFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$configValidatorFactory
			->method( 'newConfigValidator' )
			->willReturn( new NoValidationValidator() );
		$loader = new WikiPageConfigLoader(
			$configValidatorFactory,
			$this->getMockRequestFactory( '', '', 0 ),
			$this->getMockRevisionLookup( $title, false, 0 ),
			$this->getMockTitleFactory( '', '', false )
		);
		$loader->setCache( $cache, 0 );
		$this->assertSame( [], $loader->load( $title ) );
	}

	/**
	 * @param string $url Should look like a real URL (have scheme, domain, path)
	 * @param string $titleText
	 * @param bool $isExternal
	 * @return Title|MockObject
	 */
	protected function getMockTitle( $url, $titleText, $isExternal = true ) {
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFullURL', 'getNamespace', 'getDBKey', 'isExternal' ] )
			->getMock();
		$title->method( 'isExternal' )->willReturn( $isExternal );
		$title->method( 'getFullURL' )->willReturn( $url );
		$title->method( 'getNamespace' )->willReturn( 0 );
		$title->method( 'getDBKey' )->willReturn( $titleText );
		return $title;
	}

	/**
	 * @param string $expectedUrl
	 * @param string|Status $result A content string or an error status.
	 * @param int $invokeCount The number of times the factory is expected to be used.
	 * @return HttpRequestFactory|MockObject
	 */
	protected function getMockRequestFactory( $expectedUrl, $result, $invokeCount = 1 ) {
		$content = null;
		if ( !( $result instanceof StatusValue ) ) {
			$content = $result;
			$result = Status::newGood();
		}

		$request = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'execute', 'getContent' ] )
			->getMock();
		$request->method( 'execute' )->willReturn( $result );
		$request->method( 'getContent' )->willReturn( $content );

		$requestFactory = $this->getMockBuilder( HttpRequestFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'create', 'getUserAgent' ] )
			->getMock();
		$requestFactory->method( 'getUserAgent' )->willReturn( 'Foo' );
		$requestFactory->expects( $this->exactly( $invokeCount ) )
			->method( 'create' )
			->with( $expectedUrl, $this->anything(), $this->anything() )
			->willReturn( $request );
		return $requestFactory;
	}

	/**
	 * @param LinkTarget $expectedTitle
	 * @param false|null|Content $content A content object, or false for revision lookup
	 *   failure, or null for slot access failure.
	 * @param int $invokeCount The number of times the factory is expected to be used.
	 * @return RevisionLookup|MockObject
	 */
	protected function getMockRevisionLookup( LinkTarget $expectedTitle, $content, $invokeCount = 1 ) {
		$revisionLookup = $this->getMockBuilder( RevisionLookup::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getRevisionByTitle' ] )
			->getMockForAbstractClass();
		$revisionLookup->expects( $this->exactly( $invokeCount ) )
			->method( 'getRevisionByTitle' )
			->willReturnCallback( function ( LinkTarget $title ) use ( $expectedTitle, $content ) {
				$this->assertSame( $expectedTitle->getNamespace(), $title->getNamespace() );
				$this->assertSame( $expectedTitle->getText(), $title->getText() );
				$this->assertSame( $expectedTitle->getInterwiki(), $title->getInterwiki() );
				if ( $content === false ) {
					return null;
				}
				$revision = $this->getMockBuilder( RevisionRecord::class )
					->disableOriginalConstructor()
					->onlyMethods( [ 'getContent' ] )
					->getMockForAbstractClass();
				$revision->expects( $this->once() )
					->method( 'getContent' )
					->willReturn( $content );
				return $revision;
			} );
		return $revisionLookup;
	}

	/**
	 * Works around the URL handling ugliness in PageConfigurationLoader::getRawUrl.
	 * @param string $fullUrl
	 * @param string $localUrl
	 * @param bool $isExternal
	 * @return TitleFactory|MockObject
	 */
	protected function getMockTitleFactory( $fullUrl, $localUrl, $isExternal ) {
		$titleFactory = $this->getMockBuilder( TitleFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'newFromLinkTarget', 'makeTitle' ] )
			->getMock();
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFullURL', 'getLocalURL', 'isExternal' ] )
			->getMock();
		$titleFactory->method( 'newFromLinkTarget' )
			->willReturn( $title );
		$titleFactory->method( 'makeTitle' )
			->willReturn( $title );
		$title->method( 'getFullURL' )
			->willReturn( $fullUrl );
		$title->method( 'getLocalURL' )
			->willReturn( $localUrl );
		$title->method( 'isExternal' )
			->willReturn( $isExternal );
		return $titleFactory;
	}

	/**
	 * Assert that a result (JSON array or error status) matches the expected value.
	 * @param array|StatusValue $expectedData
	 * @param array|StatusValue $data
	 */
	private function assertResultSame( $expectedData, $data ) {
		if ( $expectedData instanceof StatusValue ) {
			$this->assertInstanceOf( StatusValue::class, $data );
			foreach ( $expectedData->getErrors() as [ 'message' => $expectedMessage ] ) {
				if ( $expectedMessage instanceof ApiRawMessage ) {
					// avoid breaking the test on wording changes, as long as the error code matches
					$code = $expectedMessage->getApiCode();
					$this->assertNotEmpty(
						array_filter( $data->getErrors(), static function ( $error ) use ( $code ) {
							return $error['message'] instanceof ApiRawMessage
								&& $error['message']->getApiCode() === $code;
						} ),
						"error result did not have message with code $code: $data"
					);
				} else {
					$key = $expectedMessage instanceof MessageSpecifier
						? $expectedMessage->getKey() : $expectedMessage;
					$this->assertTrue( $data->hasMessage( $expectedMessage ),
						"error result did not have message with key $key: $data" );
				}
			}
		} else {
			$this->assertIsArray( $data );
			$this->assertEquals( $expectedData, $data );
		}
	}
}
