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

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
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
	public function testRender( string $mode, string $expectedBodyMessage ) {
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
	}

	public function testMobileSummaryHasNoDetailsView() {
		$module = $this->getModule();

		$html = $module->render( ReadingRecommendations::RENDER_MOBILE_SUMMARY );

		$this->assertFalse( $module->supports( ReadingRecommendations::RENDER_MOBILE_DETAILS ) );
		$this->assertSame( '', $module->render( ReadingRecommendations::RENDER_MOBILE_DETAILS ) );
		$this->assertStringNotContainsString( 'data-overlay-route', $html );
		$this->assertStringNotContainsString( 'growthexperiments-homepage-module-header-nav-icon', $html );
	}

	private function getModule(): ReadingRecommendations {
		$contextMock = $this->createMock( IContextSource::class );
		$contextMock->method( 'getOutput' )
			->willReturn( $this->createMock( OutputPage::class ) );
		$contextMock->method( 'msg' )
			->willReturnCallback( fn ( string $key ) => $this->getMockMessage( $key ) );
		return new ReadingRecommendations( $contextMock, new HashConfig( [] ) );
	}
}
