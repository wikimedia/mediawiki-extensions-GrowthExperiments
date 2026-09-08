<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\HomepageModules\ReadingRecommendations;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Output\OutputPage;
use MediaWikiUnitTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;

/**
 * @covers \GrowthExperiments\HomepageModules\ReadingRecommendations
 */
class ReadingRecommendationsTest extends MediaWikiUnitTestCase {

	private const FIXTURE_PATH = __DIR__ . '/../../../../modules/' .
		'ext.growthExperiments.Homepage.ReadingRecommendations/fixtures/recommendations.json';

	private ?string $fixtureFile = null;

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	protected function tearDown(): void {
		if ( $this->fixtureFile !== null ) {
			unlink( $this->fixtureFile );
		}
		parent::tearDown();
	}

	public static function provideRenderModes(): array {
		return [
			'desktop' => [
				ReadingRecommendations::RENDER_DESKTOP,
				'growthexperiments-homepage-reading-recommendations-personalize-title',
			],
			'mobile summary' => [
				ReadingRecommendations::RENDER_MOBILE_SUMMARY,
				'growthexperiments-homepage-reading-recommendations-personalize-text',
			],
		];
	}

	/**
	 * @dataProvider provideRenderModes
	 */
	public function testRenderPlaceholder( string $mode, string $expectedBodyMessage ) {
		$module = $this->getModule();

		$html = $module->render( $mode );

		$this->assertStringContainsString(
			'growthexperiments-homepage-module-reading-recommendations',
			$html
		);
		$this->assertStringContainsString(
			'growthexperiments-homepage-reading-recommendations-header',
			$html
		);
		$this->assertStringContainsString( $expectedBodyMessage, $html );
		$this->assertStringNotContainsString( 'growthexperiments-reading-recommendations-list', $html );
	}

	public static function provideSettingsThatKeepFixtureOff(): array {
		return [
			'no file, no developer setup' => [ null, false ],
			'file without developer setup' => [ self::FIXTURE_PATH, false ],
			'developer setup without file' => [ null, true ],
			'missing file' => [ __DIR__ . '/does-not-exist.json', true ],
		];
	}

	/**
	 * @dataProvider provideSettingsThatKeepFixtureOff
	 */
	public function testFixtureIsOffUnlessFileAndDeveloperSetupAreSet( ?string $fixtureFile, bool $developerSetup ) {
		$module = $this->getModule( $fixtureFile, $developerSetup );

		$html = $module->render( ReadingRecommendations::RENDER_DESKTOP );
		$data = $module->getJsData( ReadingRecommendations::RENDER_DESKTOP );

		$this->assertStringNotContainsString( 'growthexperiments-reading-recommendations-list', $html );
		$this->assertStringContainsString(
			'growthexperiments-homepage-reading-recommendations-personalize-title',
			$html
		);
		$this->assertSame( [], $data['recommendations'] );
	}

	/**
	 * @dataProvider provideRenderModes
	 */
	public function testFixtureModeRendersList( string $mode ) {
		$html = $this->getModule( self::FIXTURE_PATH, true )->render( $mode );

		$this->assertStringContainsString( 'growthexperiments-reading-recommendations-list', $html );
		foreach ( self::getFixture() as $item ) {
			$this->assertStringContainsString( htmlspecialchars( $item['title'] ), $html );
			$this->assertStringContainsString( htmlspecialchars( $item['url'] ), $html );
		}
		$this->assertStringContainsString( 'upload.wikimedia.org', $html );
		$this->assertStringContainsString(
			'growthexperiments-homepage-reading-recommendations-related-to',
			$html
		);
		$this->assertStringNotContainsString(
			'growthexperiments-homepage-reading-recommendations-personalize-title',
			$html
		);
		$this->assertStringNotContainsString(
			'growthexperiments-homepage-reading-recommendations-personalize-text',
			$html
		);
	}

	/**
	 * @dataProvider provideRenderModes
	 */
	public function testFixtureModeExportsFixture( string $mode ) {
		$data = $this->getModule( self::FIXTURE_PATH, true )->getJsData( $mode );

		$this->assertSame( self::getFixture(), $data['recommendations'] );
		$this->assertSame( $mode, $data['renderMode'] );
		// No details view, so no overlay pre-render in mobile summary mode.
		$this->assertArrayNotHasKey( 'overlay', $data );
	}

	public function testFixtureExportsEveryCardState() {
		$data = $this->getModule( self::FIXTURE_PATH, true )->getJsData( ReadingRecommendations::RENDER_DESKTOP );

		$states = array_map( static fn ( array $item ) => [
			'interest' => $item['relatedTo'] !== null,
			'thumbnail' => $item['thumbnail'] !== null,
			'description' => $item['description'] !== null,
		], $data['recommendations'] );
		$this->assertContains( [ 'interest' => true, 'thumbnail' => true, 'description' => true ], $states );
		$this->assertContains( [ 'interest' => true, 'thumbnail' => false, 'description' => true ], $states );
		$this->assertContains( [ 'interest' => false, 'thumbnail' => true, 'description' => false ], $states );
		$this->assertContains( [ 'interest' => false, 'thumbnail' => false, 'description' => false ], $states );
	}

	public static function provideInvalidFixtures(): array {
		$cases = [
			'malformed JSON' => [ '[' ],
			'null' => [ 'null' ],
			'scalar' => [ '42' ],
			'object instead of list' => [ '{"title":"Example"}' ],
			'null row' => [ '[null]' ],
		];
		$item = [ 'title' => 'Example', 'url' => '/wiki/Example', 'pageId' => 1 ];
		$invalidItems = [
			'missing required fields' => [],
			'invalid title' => array_replace( $item, [ 'title' => [] ] ),
			'invalid URL' => array_replace( $item, [ 'url' => null ] ),
			'invalid page ID' => array_replace( $item, [ 'pageId' => '1' ] ),
			'invalid description' => $item + [ 'description' => [] ],
			'invalid related interest' => $item + [ 'relatedTo' => true ],
			'invalid thumbnail' => $item + [ 'thumbnail' => 'image.jpg' ],
			'incomplete thumbnail' => $item + [ 'thumbnail' => [ 'url' => 'image.jpg' ] ],
		];
		foreach ( $invalidItems as $name => $invalidItem ) {
			$cases[$name] = [ json_encode( [ $item, $invalidItem ], JSON_THROW_ON_ERROR ) ];
		}
		return $cases;
	}

	/**
	 * @dataProvider provideInvalidFixtures
	 */
	public function testInvalidFixtureFallsBackToPlaceholder( string $json ) {
		$path = $this->createFixtureFile( $json );
		foreach ( self::provideRenderModes() as [ $mode, $expectedBodyMessage ] ) {
			$module = $this->getModule( $path, true );
			$html = $module->render( $mode );
			$data = $module->getJsData( $mode );

			$this->assertSame( [], $data['recommendations'] );
			$this->assertStringContainsString( $expectedBodyMessage, $html );
			$this->assertStringNotContainsString( 'growthexperiments-reading-recommendations-list', $html );
		}
	}

	/**
	 * @dataProvider provideRenderModes
	 */
	public function testFixtureDefaultsMissingOptionalFieldsToNull( string $mode ) {
		$item = [ 'title' => 'Example', 'url' => '/wiki/Example', 'pageId' => 1 ];
		$path = $this->createFixtureFile( json_encode( [ $item ], JSON_THROW_ON_ERROR ) );
		$module = $this->getModule( $path, true );
		$data = $module->getJsData( $mode );
		$html = $module->render( $mode );

		$this->assertSame(
			[ $item + [ 'description' => null, 'thumbnail' => null, 'relatedTo' => null ] ],
			$data['recommendations']
		);
		$this->assertStringContainsString( '<a href="/wiki/Example">Example</a>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<p ', $html );
	}

	public function testMobileSummaryHasNoDetailsView() {
		$module = $this->getModule();

		$html = $module->render( ReadingRecommendations::RENDER_MOBILE_SUMMARY );

		$this->assertFalse( $module->supports( ReadingRecommendations::RENDER_MOBILE_DETAILS ) );
		$this->assertSame( '', $module->render( ReadingRecommendations::RENDER_MOBILE_DETAILS ) );
		$this->assertStringNotContainsString( 'data-overlay-route', $html );
		$this->assertStringNotContainsString( 'growthexperiments-homepage-module-header-nav-icon', $html );
	}

	private function getModule(
		?string $fixtureFile = null,
		bool $developerSetup = false
	): ReadingRecommendations {
		$contextMock = $this->createMock( IContextSource::class );
		$contextMock->method( 'getOutput' )
			->willReturn( $this->createMock( OutputPage::class ) );
		$contextMock->method( 'getConfig' )
			->willReturn( new HashConfig( [
				'GEReadingRecommendationsFixtureFile' => $fixtureFile,
				'GEDeveloperSetup' => $developerSetup,
			] ) );
		$contextMock->method( 'msg' )
			->willReturnCallback( fn ( string $key ) => $this->getMockMessage( $key ) );
		return new ReadingRecommendations( $contextMock, new HashConfig( [] ) );
	}

	private function createFixtureFile( string $json ): string {
		$path = tempnam( sys_get_temp_dir(), 'reading-recommendations-' );
		$this->assertNotFalse( $path );
		$this->fixtureFile = $path;
		file_put_contents( $path, $json );
		return $path;
	}

	/** @return array[] */
	private static function getFixture(): array {
		return json_decode( file_get_contents( self::FIXTURE_PATH ), true, 512, JSON_THROW_ON_ERROR );
	}
}
