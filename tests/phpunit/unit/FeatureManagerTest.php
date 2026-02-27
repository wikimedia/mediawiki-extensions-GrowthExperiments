<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\StaticExperimentManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\FeatureManager
 */
class FeatureManagerTest extends MediaWikiUnitTestCase {
	public function testShouldShowReviseToneTasksForUser() {
		$sut = $this->getFeatureManager( [
			'growthexperiments-revise-tone' => 'treatment',
		] );
		$user = new UserIdentityValue( 0, __CLASS__ );
		$this->assertTrue( $sut->shouldShowReviseToneTasksForUser( $user ) );
	}

	public function testShouldNotShowReviseToneTasksForUser() {
		$sut = $this->getFeatureManager( [
			'growthexperiments-revise-tone' => 'control',
		] );
		$user = new UserIdentityValue( 0, __CLASS__ );
		$this->assertFalse( $sut->shouldShowReviseToneTasksForUser( $user ) );
	}

	/**
	 * Provide a configured FeatureManager with all relevant config feature flags enabled
	 *
	 * @param array|string|null $defaultVariant
	 * @return FeatureManager
	 */
	private function getFeatureManager( $defaultVariant ): FeatureManager {
		$extensionRegistryMock = $this->createMock( ExtensionRegistry::class );
		$matcher = $this->exactly( 2 );
		$returnCallback = static function (
			string $actualExtensionName,
			string $expectedExtensionName,
			bool $returnValue,
				   $ctx,
		) {
			$ctx->assertEquals( $expectedExtensionName, $actualExtensionName );
			return $returnValue;
		};
		$self = $this;
		$extensionRegistryMock->expects( $matcher )
			->method( 'isLoaded' )
			->willReturnCallback( static function ( string $extensionName ) use (
				$matcher, $returnCallback, $self
			) {
				return match ( $matcher->getInvocationCount() ) {
					1 => $returnCallback( $extensionName, 'WikimediaMessages', true, $self ),
					2 => $returnCallback( $extensionName, 'VisualEditor', true, $self ),
				};
			} );
		$config = new HashConfig( [
			'GEReviseToneSuggestedEditEnabled' => true,
			'GEHomepageSuggestedEditsEnabled' => true,
		] );
		$sut = new FeatureManager( $extensionRegistryMock, $config );
		$sut->setExperimentManager( new StaticExperimentManager( new ServiceOptions( [ 'GEHomepageDefaultVariant' ], [
			'GEHomepageDefaultVariant' => $defaultVariant ?: 'control',
		] ) ) );
		return $sut;
	}
}
