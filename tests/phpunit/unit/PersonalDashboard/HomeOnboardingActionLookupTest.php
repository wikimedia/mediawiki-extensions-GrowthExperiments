<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit\PersonalDashboard;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\InterestValidator;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\PersonalDashboard\HomeOnboardingActionLookup;
use MediaWiki\Config\HashConfig;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\PersonalDashboard\HomeOnboardingActionLookup
 */
class HomeOnboardingActionLookupTest extends MediaWikiUnitTestCase {

	public static function provideGetAction(): iterable {
		yield 'has interests' => [
			[
				HomepageHooks::HOMEPAGE_PREF_ENABLE => '1',
				AccountSetupHooks::INTEREST_ARTICLES_PROP => '["Foo"]',
			],
			null,
		];
		yield 'no interests, interacted with the notice' => [
			[
				HomepageHooks::HOMEPAGE_PREF_ENABLE => '1',
				HomeOnboardingActionLookup::INTERESTS_NOTICE_INTERACTED_PREF => '1',
			],
			null,
		];
		yield 'Account Setup saved no interests' => [
			[
				HomepageHooks::HOMEPAGE_PREF_ENABLE => '1',
				AccountSetupHooks::INTEREST_ARTICLES_PROP => '[]',
				HomeOnboardingActionLookup::INTRO_SEEN_PREF => '1',
			],
			HomeOnboardingActionLookup::ACTION_INTERESTS_NOTICE,
		];
		yield 'no interests, Homepage preference off' => [
			[],
			HomeOnboardingActionLookup::ACTION_INTERESTS_NOTICE,
		];
		yield 'no interests, intro seen' => [
			[
				HomepageHooks::HOMEPAGE_PREF_ENABLE => '1',
				HomeOnboardingActionLookup::INTRO_SEEN_PREF => '1',
			],
			HomeOnboardingActionLookup::ACTION_INTERESTS_NOTICE,
		];
		yield 'no interests, intro not seen' => [
			[ HomepageHooks::HOMEPAGE_PREF_ENABLE => '1' ],
			HomeOnboardingActionLookup::ACTION_WELCOME_BACK,
		];
	}

	/**
	 * @dataProvider provideGetAction
	 */
	public function testGetAction( array $options, ?string $expected ): void {
		$user = new UserIdentityValue( 1, 'User1' );
		$userOptionsLookup = new StaticUserOptionsLookup( [ 'User1' => $options ] );
		$interestValidator = $this->createMock( InterestValidator::class );
		$interestValidator->method( 'validate' )->willReturnCallback(
			static fn ( $interests ) => [ 'valid' => $interests, 'invalid' => [] ],
		);
		$lookup = new HomeOnboardingActionLookup(
			$userOptionsLookup,
			new NewcomerTasksUserOptionsLookup(
				$userOptionsLookup,
				new HashConfig(),
				$this->createNoOpMock( ConfigurationLoader::class ),
				$interestValidator,
			)
		);

		$this->assertSame( $expected, $lookup->getAction( $user ) );
	}
}
