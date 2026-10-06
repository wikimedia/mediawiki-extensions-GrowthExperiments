<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\AccountSetup\PostSignupOnboardingEligibility;
use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\AccountSetup\PostSignupOnboardingEligibility
 */
class PostSignupOnboardingEligibilityTest extends MediaWikiUnitTestCase {

	public static function provideCanShowOnboarding(): iterable {
		yield 'all conditions met' => [ [], true ];
		yield 'welcome survey disabled' => [ [ 'enabled' => false ], false ];
		yield 'temp user' => [ [ 'isTemp' => true ], false ];
		yield 'campaign skips welcome survey' => [ [ 'skipCampaign' => true ], false ];
		yield 'popup display mode' => [ [ 'request' => [ 'display' => 'popup' ] ], false ];
	}

	/**
	 * @dataProvider provideCanShowOnboarding
	 */
	public function testCanShowOnboarding( array $overrides, bool $expected ): void {
		$campaignConfig = $this->createMock( CampaignConfig::class );
		$campaignConfig->method( 'shouldSkipWelcomeSurvey' )->willReturn( $overrides['skipCampaign'] ?? false );
		$sut = new PostSignupOnboardingEligibility(
			new HashConfig( [ 'WelcomeSurveyEnabled' => $overrides['enabled'] ?? true ] ),
			$this->createNoOpMock( TitleFactory::class ),
			$campaignConfig,
			$this->createMock( CampaignLoader::class ),
		);

		$user = $this->createMock( User::class );
		$user->method( 'isTemp' )->willReturn( $overrides['isTemp'] ?? false );
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setRequest( new FauxRequest( $overrides['request'] ?? [] ) );

		$this->assertSame( $expected, $sut->canShowOnboarding( $context ) );
	}

	public static function provideUserWasEditing(): iterable {
		yield 'no returnTo' => [ '', [ 'action' => 'edit' ], null, false ];
		yield 'action=edit' => [ 'Foo', [ 'action' => 'edit' ], [ true, '' ], true ];
		yield 'veaction=edit' => [ 'Foo', [ 'veaction' => 'edit' ], [ true, '' ], true ];
		yield 'mobile editor fragment' => [ 'Foo', [], [ true, '/editor/all' ], true ];
		yield 'not editing' => [ 'Foo', [ 'action' => 'view' ], [ true, '' ], false ];
		yield 'title cannot exist' => [ 'Special:Foo', [ 'action' => 'edit' ], [ false, '' ], false ];
	}

	/**
	 * @dataProvider provideUserWasEditing
	 */
	public function testUserWasEditing(
		string $returnTo, array $returnToQuery, ?array $titleProps, bool $expected
	): void {
		$titleFactory = $this->createMock( TitleFactory::class );
		if ( $titleProps ) {
			[ $canExist, $fragment ] = $titleProps;
			$title = $this->createMock( Title::class );
			$title->method( 'canExist' )->willReturn( $canExist );
			$title->method( 'getFragment' )->willReturn( $fragment );
			$titleFactory->method( 'newFromText' )->with( $returnTo )->willReturn( $title );
		} else {
			$titleFactory->expects( $this->never() )->method( 'newFromText' );
		}
		$sut = new PostSignupOnboardingEligibility(
			new HashConfig( [] ),
			$titleFactory,
			$this->createNoOpMock( CampaignConfig::class ),
			$this->createNoOpMock( CampaignLoader::class ),
		);

		$this->assertSame( $expected, $sut->userWasEditing( $returnTo, $returnToQuery ) );
	}
}
