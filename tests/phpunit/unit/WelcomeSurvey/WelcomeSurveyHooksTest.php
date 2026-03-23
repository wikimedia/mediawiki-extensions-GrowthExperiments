<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\WelcomeSurvey;
use GrowthExperiments\WelcomeSurveyFactory;
use GrowthExperiments\WelcomeSurveyHooks;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\WelcomeSurveyHooks
 */
class WelcomeSurveyHooksTest extends MediaWikiUnitTestCase {

	public static function provideRedirectScenarios(): iterable {
		yield 'not signup' => [
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz',
				'type' => '',
			],
			[],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz',
				'returnVal' => true,
			],
		];

		yield 'welcome survey disabled' => [
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz',
				'type' => 'signup',
			],
			[
				'config' => [
					'WelcomeSurveyEnabled' => false,
				],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'returnVal' => true,
			],
		];

		yield 'welcome survey disabled, with pre-existing accountJustCreated' => [
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'type' => 'signup',
			],
			[
				'config' => [
					'WelcomeSurveyEnabled' => false,
				],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'returnVal' => true,
			],
		];

		yield 'temp user' => [
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz',
				'type' => 'signup',
			],
			[
				'user' => [
					'isTemp' => true,
				],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'returnVal' => true,
			],
		];

		yield 'temp user, with pre-existing accountJustCreated' => [
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'type' => 'signup',
			],
			[
				'user' => [
					'isTemp' => true,
				],
			],
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'returnVal' => true,
			],
		];

		yield 'signup with pre-existing returnTo' => [
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz',
				'type' => 'signup',
			],
			[],
			[
				'returnTo' => 'Special:WelcomeSurvey',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'returnToQuery' => 'returnto=Foo%3ABar&returntoquery=baz%3Dfizz&group=control&_welcomesurveytoken=123&accountJustCreated=1',
				'returnVal' => false,
			],
		];

		yield 'signup with pre-existing returnTo and pre-existing accountJustCreated' => [
			[
				'returnTo' => 'Foo:Bar',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'type' => 'signup',
			],
			[],
			[
				'returnTo' => 'Special:WelcomeSurvey',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'returnToQuery' => 'returnto=Foo%3ABar&returntoquery=baz%3Dfizz&group=control&_welcomesurveytoken=123&accountJustCreated=1',
				'returnVal' => false,
			],
		];

		yield 'signup with no returnTo' => [
			[
				'returnTo' => '',
				'returnToQuery' => 'baz=fizz',
				'type' => 'signup',
			],
			[],
			[
				'returnTo' => 'Special:WelcomeSurvey',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'returnToQuery' => 'returnto=&returntoquery=baz%3Dfizz&group=control&_welcomesurveytoken=123&accountJustCreated=1',
				'returnVal' => false,
			],
		];

		yield 'signup with no returnTo and pre-existing accountJustCreated' => [
			[
				'returnTo' => '',
				'returnToQuery' => 'baz=fizz&accountJustCreated=1',
				'type' => 'signup',
			],
			[],
			[
				'returnTo' => 'Special:WelcomeSurvey',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'returnToQuery' => 'returnto=&returntoquery=baz%3Dfizz&group=control&_welcomesurveytoken=123&accountJustCreated=1',
				'returnVal' => false,
			],
		];
	}

	/**
	 * @dataProvider provideRedirectScenarios
	 */
	public function testOnCentralAuthPostLoginRedirect( array $initialArgs, array $overrides, array $expected ): void {
		$sut = $this->newWelcomeSurveyHooks( $overrides );

		[
			'returnTo' => $returnTo,
			'returnToQuery' => $returnToQuery,
			'type' => $type,
		] = $initialArgs;
		$unusedInjectedHtml = '';
		$returnValue = $sut->onCentralAuthPostLoginRedirect(
			$returnTo,
			$returnToQuery,
			// unused
			false,
			$type,
			$unusedInjectedHtml,
		);

		[
			'returnTo' => $expectedReturnTo,
			'returnToQuery' => $expectedReturnToQuery,
			'returnVal' => $expectedReturnValue,
		] = $expected;
		$this->assertSame( '', $unusedInjectedHtml );
		$this->assertSame( $expectedReturnTo, $returnTo );
		$this->assertSame( $expectedReturnToQuery, $returnToQuery );
		$this->assertSame( $expectedReturnValue, $returnValue );
	}

	private function newWelcomeSurveyHooks( $overrides = [] ): WelcomeSurveyHooks {
		$config = new HashConfig( array_merge( [
			'WelcomeSurveyEnabled' => true,
		], $overrides['config'] ?? [] ) );

		$user = $this->createMock( User::class );
		$user->method( 'isTemp' )->willReturn( $overrides['user']['isTemp'] ?? false );
		RequestContext::getMain()->setUser( $user );

		$welcomeSurveyFactory = $this->createMock( WelcomeSurveyFactory::class );
		$welcomeSurvey = $this->createMock( WelcomeSurvey::class );
		$welcomeSurvey->method( 'getGroup' )->willReturn( 'control' );
		/**
		 * @see \GrowthExperiments\WelcomeSurvey::getRedirectUrlQuery
		 */
		$welcomeSurvey->method( 'getRedirectUrlQuery' )->willReturnCallback( static fn (
			$group, $returnTo, $returnToQuery
		) => [
				'returnto' => $returnTo,
				'returntoquery' => $returnToQuery,
				'group' => 'control',
				'_welcomesurveytoken' => '123',
			]
		);
		$welcomeSurveyFactory->method( 'newWelcomeSurvey' )->willReturn( $welcomeSurvey );

		$titleFactory = $this->createMock( TitleFactory::class );
		$specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$welcomeSurveyTitle = $this->createMock( Title::class );
		$welcomeSurveyTitle->method( 'getPrefixedText' )->willReturn( 'Special:WelcomeSurvey' );
		$specialPageFactory->method( 'getTitleForAlias' )->willReturn( $welcomeSurveyTitle );

		$campaignConfig = $this->createMock( CampaignConfig::class );
		$campaignLoader = $this->createMock( CampaignLoader::class );
		return new WelcomeSurveyHooks(
			$config,
			$titleFactory,
			$specialPageFactory,
			$welcomeSurveyFactory,
			$campaignConfig,
			$campaignLoader,
		);
	}
}
