<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\FeatureManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityUtils;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\AccountSetup\AccountSetupHooks
 */
class AccountSetupHooksTest extends MediaWikiUnitTestCase {

	public static function provideOnPostLoginRedirect(): iterable {
		yield 'not signup' => [
			[],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'baz' => 'fizz' ],
				'type' => 'error',
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'baz' => 'fizz' ],
				'type' => 'error',
			],
		];

		yield 'signup, not in early onboarding treatment' => [
			[
				'earlyOnboarding' => false,
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'baz' => 'fizz' ],
				'type' => 'signup',
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'baz' => 'fizz' ],
				'type' => 'signup',
			],
		];

		yield 'signup, early onboarding, user was editing (action=edit)' => [
			[
				'earlyOnboarding' => true,
				'returnToTitle' => [ 'canExist' => true, 'fragment' => '' ],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'action' => 'edit' ],
				'type' => 'signup',
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'action' => 'edit' ],
				'type' => 'signup',
			],
		];

		yield 'signup, early onboarding, user was editing (veaction=edit)' => [
			[
				'earlyOnboarding' => true,
				'returnToTitle' => [ 'canExist' => true, 'fragment' => '' ],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'veaction' => 'edit' ],
				'type' => 'signup',
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [ 'veaction' => 'edit' ],
				'type' => 'signup',
			],
		];

		yield 'signup, early onboarding, user was editing (mobile editor fragment)' => [
			[
				'earlyOnboarding' => true,
				'returnToTitle' => [ 'canExist' => true, 'fragment' => '/editor/all' ],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [],
				'type' => 'signup',
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [],
				'type' => 'signup',
			],
		];

		yield 'signup, early onboarding, no returnTo redirects to homepage' => [
			[
				'earlyOnboarding' => true,
			],
			[
				'returnTo' => '',
				'returnToQuery' => [ 'baz' => 'fizz' ],
				'type' => 'signup',
			],
			[
				'returnTo' => 'Special:Homepage',
				'returnToQuery' => [ 'baz' => 'fizz' ],
				'type' => 'successredirect',
			],
		];

		yield 'signup, early onboarding, not editing redirects to homepage' => [
			[
				'earlyOnboarding' => true,
				'returnToTitle' => [ 'canExist' => true, 'fragment' => '' ],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => [],
				'type' => 'signup',
			],
			[
				'returnTo' => 'Special:Homepage',
				'returnToQuery' => [],
				'type' => 'successredirect',
			],
		];

		yield 'signup, early onboarding, non-existing title redirects despite action=edit' => [
			[
				'earlyOnboarding' => true,
				'returnToTitle' => [ 'canExist' => false, 'fragment' => '' ],
			],
			[
				'returnTo' => 'Special:Foo',
				'returnToQuery' => [ 'action' => 'edit' ],
				'type' => 'signup',
			],
			[
				'returnTo' => 'Special:Homepage',
				'returnToQuery' => [ 'action' => 'edit' ],
				'type' => 'successredirect',
			],
		];
	}

	/**
	 * @dataProvider provideOnPostLoginRedirect
	 */
	public function testOnPostLoginRedirect( array $overrides, array $initialArgs, array $expected ): void {
		$sut = $this->newAccountSetupHooks( $overrides );

		[
			'returnTo' => $returnTo,
			'returnToQuery' => $returnToQuery,
			'type' => $type,
		] = $initialArgs;

		$returnValue = $sut->onPostLoginRedirect( $returnTo, $returnToQuery, $type );

		$this->assertTrue( $returnValue );
		$this->assertSame( $expected['returnTo'], $returnTo );
		$this->assertSame( $expected['returnToQuery'], $returnToQuery );
		$this->assertSame( $expected['type'], $type );
	}

	/**
	 * @dataProvider provideOnPostLoginRedirect
	 */
	public function testOnCentralAuthPostLoginRedirect( array $overrides, array $initialArgs, array $expected ): void {
		$sut = $this->newAccountSetupHooks( $overrides );

		[
			'returnTo' => $returnTo,
			'returnToQuery' => $returnToQuery,
			'type' => $type,
		] = $initialArgs;
		$returnToQuery = wfArrayToCgi( $returnToQuery );

		$ignoredParam1 = false;
		$ignoredParam2 = '';
		$returnValue = $sut->onCentralAuthPostLoginRedirect(
			$returnTo,
			$returnToQuery,
			$ignoredParam1,
			$type,
			$ignoredParam2
		);

		$this->assertTrue( $returnValue );
		$this->assertSame( $expected['returnTo'], $returnTo );
		$this->assertSame( $expected['returnToQuery'], wfCgiToArray( $returnToQuery ) );
	}

	public function testOnPostLoginRedirectSkippedForTempUser(): void {
		$sut = $this->newAccountSetupHooks( [
			'earlyOnboarding' => true,
			'isTemp' => true,
		] );
		$returnTo = 'Foo:Bar';
		$returnToQuery = [];
		$type = 'signup';

		$returnValue = $sut->onPostLoginRedirect( $returnTo, $returnToQuery, $type );

		$this->assertTrue( $returnValue );
		$this->assertSame( 'Foo:Bar', $returnTo );
		$this->assertSame( [], $returnToQuery );
		$this->assertSame( 'signup', $type );
	}

	public function testOnCentralAuthPostLoginRedirectSkippedForTempUser(): void {
		$sut = $this->newAccountSetupHooks( [
			'earlyOnboarding' => true,
			'isTemp' => true,
		] );
		$returnTo = 'Foo:Bar';
		$returnToQuery = '';
		$type = 'signup';
		$stickHTTPS = false;
		$injectedHtml = '';

		$returnValue = $sut->onCentralAuthPostLoginRedirect(
			$returnTo, $returnToQuery, $stickHTTPS, $type, $injectedHtml
		);

		$this->assertTrue( $returnValue );
		$this->assertSame( 'Foo:Bar', $returnTo );
		$this->assertSame( '', $returnToQuery );
	}

	public function testPostLoginDirectHookSkippedIfCentralAuthLoaded(): void {
		$sut = $this->newAccountSetupHooks( [
			'centralAuthLoaded' => true,
			'earlyOnboarding' => true,
		] );
		$returnTo = 'Foo:Bar';
		$returnToQuery = [];
		$type = 'signup';

		$returnValue = $sut->onPostLoginRedirect( $returnTo, $returnToQuery, $type );

		$this->assertTrue( $returnValue );
		$this->assertSame( 'Foo:Bar', $returnTo );
		$this->assertSame( [], $returnToQuery );
		$this->assertSame( 'signup', $type );
	}

	private function newAccountSetupHooks( array $overrides = [] ): AccountSetupHooks {
		$homepageTitle = $this->createMock( Title::class );
		$homepageTitle->method( 'getPrefixedText' )->willReturn( 'Special:Homepage' );
		$specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$specialPageFactory->method( 'getTitleForAlias' )
			->with( 'Homepage' )
			->willReturn( $homepageTitle );

		$featureManager = $this->createMock( FeatureManager::class );
		$featureManager->method( 'isEarlyOnboardingExperimentTreatment' )
			->willReturn( $overrides['earlyOnboarding'] ?? false );

		$titleFactory = $this->createMock( TitleFactory::class );
		if ( isset( $overrides['returnToTitle'] ) ) {
			$returnToTitle = $this->createMock( Title::class );
			$returnToTitle->method( 'canExist' )->willReturn( $overrides['returnToTitle']['canExist'] );
			$returnToTitle->method( 'getFragment' )->willReturn( $overrides['returnToTitle']['fragment'] );
			$titleFactory->method( 'newFromText' )->willReturn( $returnToTitle );
		}

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->with( 'CentralAuth' )
			->willReturn( $overrides['centralAuthLoaded'] ?? false );

		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isTemp' )
			->willReturn( $overrides['isTemp'] ?? false );

		return new AccountSetupHooks(
			$specialPageFactory,
			$featureManager,
			$titleFactory,
			$extensionRegistry,
			$userIdentityUtils
		);
	}

}
