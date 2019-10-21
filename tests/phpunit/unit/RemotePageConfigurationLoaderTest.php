<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\RemotePageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use IContextSource;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use Message;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use StatusValue;
use Title;
use TitleFactory;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\RemotePageConfigurationLoader
 * @covers \GrowthExperiments\Util::getJsonUrl
 */
class RemotePageConfigurationLoaderTest extends MediaWikiUnitTestCase {

	private $oldWgUrlProtocols;

	public function setUp(): void {
		// work around wfParseUrl using a global
		global $wgUrlProtocols;
		parent::setUp();
		$this->oldWgUrlProtocols = $wgUrlProtocols;
		$wgUrlProtocols = [ 'http://', 'https://' ];
	}

	public function tearDown(): void {
		global $wgUrlProtocols;
		parent::tearDown();
		$wgUrlProtocols = $this->oldWgUrlProtocols;
	}

	public function testLoadTaskTypes() {
		$url = 'https://bar';
		$expectedLocalUrl = '/w/index.php?title=Foo&action=raw';
		$title = $this->getMockTitle( $url, 'Foo' );

		$requestFactory = $this->getMockRequestFactory( $url . $expectedLocalUrl,
			$this->getConfig() );
		$titleFactory = $this->getMockTitleFactory( $url . '/Foo?action=raw', $expectedLocalUrl );
		$context = $this->getMockContext();
		$configurationLoader = new RemotePageConfigurationLoader( $requestFactory, $titleFactory,
			$context, $title );
		// Run twice to test caching. If caching is broken, the 'once' expectation
		// for HTTP calls in the $requestFactory mock will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertInternalType( 'array', $taskTypes );
			$this->assertNotEmpty( $taskTypes );
			$this->assertInstanceOf( TaskType::class, $taskTypes[0] );
			$this->assertSame( [ 'copyedit', 'references' ], array_map( function ( TaskType $tt ) {
				return $tt->getId();
			}, $taskTypes ) );
			$this->assertSame( [ 'easy', 'medium' ], array_map( function ( TaskType $tt ) {
				return $tt->getDifficulty();
			}, $taskTypes ) );
		}
	}

	/**
	 * @dataProvider provideLoadTaskTypes_error
	 */
	public function testLoadTaskTypes_error( $error ) {
		$url = 'https://bar';
		$expectedLocalUrl = '/w/index.php?title=Foo&action=raw';
		$title = $this->getMockTitle( $url, 'Foo' );
		$requestFactory = $this->getMockRequestFactory( $url . $expectedLocalUrl,
			$this->getConfig( $error ) );
		$titleFactory = $this->getMockTitleFactory( $url . '/Foo?action=raw', $expectedLocalUrl );
		$msg = $this->createMock( Message::class );
		$msg->method( 'exists' )->willReturn( false );
		$context = $this->getMockContext( [
			'growthexperiments-newcomertasks-tasktype-name-foo' => $msg,
		] );
		$configurationLoader = new RemotePageConfigurationLoader( $requestFactory, $titleFactory,
			$context, $title );
		$status = $configurationLoader->loadTaskTypes();
		$this->assertInstanceOf( StatusValue::class, $status );
		if ( $error === 'json' ) {
			$this->assertTrue( $status->hasMessage( 'json-error-syntax' ) );
		} else {
			$this->assertTrue( $status->hasMessage( 'growthexperiments-newcomertasks-config-' . $error ) );
		}
	}

	public function testLoadTaskTypes_httpError() {
		$url = 'https://bar';
		$expectedLocalUrl = '/w/index.php?title=Foo&action=raw';
		$title = $this->getMockTitle( $url, 'Foo' );
		$httpStatus = Status::newFatal( 'http-error' );
		$requestFactory = $this->getMockRequestFactory( $url . $expectedLocalUrl, $httpStatus );
		$titleFactory = $this->getMockTitleFactory( $url . '/Foo?action=raw', $expectedLocalUrl );
		$context = $this->getMockContext();
		$configurationLoader = new RemotePageConfigurationLoader( $requestFactory, $titleFactory,
			$context, $title );
		$status = $configurationLoader->loadTaskTypes();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertEquals( $httpStatus->getErrors(), $status->getErrors() );
	}

	public function provideLoadTaskTypes_error() {
		return [ [ 'json' ], [ 'missingfield' ], [ 'wronggroup' ], [ 'missingmessage' ] ];
	}

	public function testLoadTemplateBlacklist() {
		$this->markTestSkipped( 'Not implemented yet' );
	}

	/**
	 * Test configuration
	 * @param string $error
	 * @return string
	 */
	protected function getConfig( $error = null ) {
		$config = [
			'copyedit' => [
				'icon' => 'articleCheck',
				'group' => 'easy',
				'templates' => [ 'Foo', 'Bar', 'Baz' ],
			],
			'references' => [
				'icon' => 'references',
				'group' => 'medium',
				'templates' => [ 'R1', 'R2', 'R3' ],
			],
		];
		if ( $error === 'json' ) {
			return '@&#!*';
		} elseif ( $error === 'missingfield' ) {
			unset( $config['references']['group'] );
		} elseif ( $error === 'wronggroup' ) {
			$config['references']['group'] = 'hardest';
		} elseif ( $error === 'missingmessage' ) {
			$config['foo'] = [ 'icon' => 'foo', 'group' => 'hard', 'templates' => [ 'T' ] ];
		}
		return json_encode( $config );
	}

	/**
	 * @param string $url Should look like a real URL (have scheme, domain, path)
	 * @param string $titleText
	 * @return Title|MockObject
	 */
	protected function getMockTitle( $url, $titleText ) {
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getFullURL', 'getNamespace', 'getDBKey' ] )
			->getMock();
		$title->method( 'getFullURL' )->willReturn( $url );
		$title->method( 'getNamespace' )->willReturn( 0 );
		$title->method( 'getDBKey' )->willReturn( $titleText );
		return $title;
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

		$request = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->setMethods( [ 'execute', 'getContent' ] )
			->getMock();
		$request->method( 'execute' )->willReturn( $result );
		$request->method( 'getContent' )->willReturn( $content );

		$requestFactory = $this->getMockBuilder( HttpRequestFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'create', 'getUserAgent' ] )
			->getMock();
		$requestFactory->method( 'getUserAgent' )->willReturn( 'Foo' );
		$requestFactory->expects( $this->once() )
			->method( 'create' )
			->with( $expectedUrl, $this->anything(), $this->anything() )
			->willReturn( $request );
		return $requestFactory;
	}

	/**
	 * Works around the URL handling ugliness in RemotePageConfigurationLoader::getRawUrl.
	 * @param string $fullUrl
	 * @param string $localUrl
	 * @return TitleFactory|MockObject
	 */
	protected function getMockTitleFactory( $fullUrl, $localUrl ) {
		$titleFactory = $this->getMockBuilder( TitleFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'newFromLinkTarget', 'makeTitle' ] )
			->getMock();
		$fullUrlTitle = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getFullURL' ] )
			->getMock();
		$fullUrlTitle->method( 'getFullURL' )
			->willReturn( $fullUrl );
		$titleFactory->method( 'newFromLinkTarget' )
			->willReturn( $fullUrlTitle );
		$localUrlTitle = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getLocalURL' ] )
			->getMock();
		$localUrlTitle->method( 'getLocalURL' )
			->willReturn( $localUrl );
		$titleFactory->method( 'makeTitle' )
			->willReturn( $localUrlTitle );
		return $titleFactory;
	}

	/**
	 * @param Message[] $customMessages
	 * @return IContextSource|MockObject
	 */
	protected function getMockContext( array $customMessages = [] ) {
		$context = $this->getMockBuilder( IContextSource::class )
			->setMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$context->method( 'msg' )->willReturnCallback( function ( $key ) use ( $customMessages ) {
			if ( isset( $customMessages[$key] ) ) {
				return $customMessages[$key];
			}
			return $this->getMockMessage( $key );
		} );
		return $context;
	}

}
