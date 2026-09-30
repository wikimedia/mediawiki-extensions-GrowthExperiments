<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Mentorship\Hooks\MentorUserOptionsHooks;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\PersonalDashboard\Mentorship;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\PersonalDashboard\Mentorship
 */
class PersonalDashboardMentorshipTest extends MediaWikiIntegrationTestCase {

	private const string NAME = 'ext.growthExperiments.mentorship';

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'PersonalDashboard' );
		$this->setUserLang( 'qqx' );
	}

	private function newModule(
		int $state,
		?UserIdentity $mentorUser,
		?string $introText = null,
		string|false|null $registration = '20200101000000',
		?UserIdentity $primaryUser = null,
		bool $preintroDismissed = false
	): Mentorship {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->getTestUser()->getUser() );

		$mentorManager = $this->createMock( IMentorManager::class );
		$mentorManager->method( 'getMentorshipStateForUser' )->willReturn( $state );
		$mentorManager->method( 'getEffectiveMentorForUserSafe' )->willReturn(
			$mentorUser ? new Mentor( $mentorUser, $introText, 'default intro', 1 ) : null
		);
		// The usual mentor is the shown mentor unless the test says otherwise.
		$primaryUser ??= $mentorUser;
		$mentorManager->method( 'getMentorForUserIfExists' )->willReturn(
			$primaryUser ? new Mentor( $primaryUser, null, 'default intro', 1 ) : null
		);

		$registrationLookup = $this->createMock( UserRegistrationLookup::class );
		$registrationLookup->method( 'getRegistration' )->willReturn( $registration );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )
			->with( $this->anything(), MentorUserOptionsHooks::PREINTRO_DISMISSED_PREF )
			->willReturn( $preintroDismissed );

		$module = new Mentorship(
			$context,
			$mentorManager,
			$registrationLookup,
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getGenderCache(),
			$userOptionsLookup
		);
		$module->setName( self::NAME );
		return $module;
	}

	private function newMentor(): UserIdentity {
		return new UserIdentityValue( 2, 'Panini' );
	}

	public function testMentorCard() {
		$html = $this->newModule( IMentorManager::MENTORSHIP_ENABLED, $this->newMentor(), 'Hello!' )
			->render();

		$this->assertStringContainsString( 'id="pd-header-slot-' . self::NAME . '"', $html );
		$this->assertStringContainsString( 'personal-dashboard-mentorship__preintro', $html );
		$this->assertStringContainsString( 'personal-dashboard-mentorship__message-dismiss', $html );
		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__away', $html );
		$this->assertStringContainsString( 'personal-dashboard-mentorship__avatar', $html );
		$this->assertStringContainsString( 'data-initial="P"', $html );
		// Only the name is a link element; CSS stretches it over the card.
		$this->assertStringContainsString(
			'<span class="cdx-card cdx-card--is-link personal-dashboard-mentorship__card">', $html
		);
		$this->assertMatchesRegularExpression(
			'/<span class="cdx-card__text__title"><a class="personal-dashboard-mentorship__card-link" '
				. 'href="[^"]*User:Panini"><bdi>Panini<\\/bdi><\\/a><\\/span>/',
			$html
		);
		$this->assertStringContainsString( '<blockquote class="personal-dashboard-mentorship__quote"', $html );
		$this->assertStringContainsString( 'personal-dashboard-mentorship__quote', $html );
		$this->assertStringContainsString( 'Hello!', $html );
		$this->assertStringContainsString(
			'(growthexperiments-homepage-mentorship-mentor-editing-since: 2020, Panini)', $html
		);
		$this->assertStringContainsString(
			'class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled '
				. 'personal-dashboard-mentorship__ask"',
			$html
		);
		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__conversations', $html );
		// Opt out and About moved into the header menu.
		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__optout', $html );
		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__about', $html );
	}

	public function testAvatarInitialIsGrapheme() {
		// The first code point alone, क, is not a visible character.
		$html = $this->newModule( IMentorManager::MENTORSHIP_ENABLED, new UserIdentityValue( 2, 'किरण' ) )
			->render();

		$this->assertStringContainsString( 'data-initial="कि"', $html );

		$html = $this->newModule( IMentorManager::MENTORSHIP_ENABLED, new UserIdentityValue( 2, '0xFoo' ) )
			->render();
		$this->assertStringContainsString( 'data-initial="0"', $html );
	}

	public function testMentorCardWithoutBio() {
		$html = $this->newModule( IMentorManager::MENTORSHIP_ENABLED, $this->newMentor() )->render();

		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__quote', $html );
		$this->assertStringContainsString( '(growthexperiments-homepage-mentorship-intro: Panini,', $html );
	}

	public function testMentorCardWithoutRegistration() {
		$html = $this->newModule( IMentorManager::MENTORSHIP_ENABLED, $this->newMentor(), null, null )
			->render();

		$this->assertStringNotContainsString( 'mentor-editing-since', $html );
	}

	public function testPreintroDismissed() {
		$html = $this->newModule(
			IMentorManager::MENTORSHIP_ENABLED, $this->newMentor(), null, '20200101000000', null, true
		)->render();

		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__message', $html );
		$this->assertStringContainsString( 'personal-dashboard-mentorship__card', $html );
	}

	public function testAwayNotice() {
		// Shows even after the intro was dismissed, in place of the intro.
		$html = $this->newModule(
			IMentorManager::MENTORSHIP_ENABLED, $this->newMentor(), null, '20200101000000',
			new UserIdentityValue( 3, 'Pesto' ), true
		)->render();

		$this->assertStringContainsString( 'personal-dashboard-mentorship__away', $html );
		$this->assertMatchesRegularExpression(
			'/\\(growthexperiments-homepage-mentorship-away-notice: '
				. '<a href="[^"]*User:Panini"><bdi>Panini<\\/bdi><\\/a>, '
				. '<a href="[^"]*User:Pesto"><bdi>Pesto<\\/bdi><\\/a>, Pesto\\)/',
			$html
		);
		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__preintro', $html );
		$this->assertStringContainsString( '<bdi>Panini</bdi>', $html );
	}

	public function testOptedOutCard() {
		$module = $this->newModule( IMentorManager::MENTORSHIP_OPTED_OUT, null );
		$html = $module->render();

		$this->assertStringContainsString( 'id="pd-header-slot-' . self::NAME . '"', $html );
		$this->assertStringContainsString( 'personal-dashboard-mentorship__optin-text', $html );
		$this->assertStringContainsString( 'class="cdx-button personal-dashboard-mentorship__optin"', $html );
		$this->assertStringNotContainsString( 'personal-dashboard-mentorship__card', $html );

		$vars = $module->getJsConfigVars();
		$this->assertSame(
			[
				'headerSlot' => 'pd-header-slot-' . self::NAME,
				'optedOut' => true,
				'preintroDismissedPref' => MentorUserOptionsHooks::PREINTRO_DISMISSED_PREF,
			],
			$vars['wgGrowthExperimentsMentorship']
		);
		$this->assertArrayNotHasKey( 'wgGrowthExperimentsMentorshipPoster', $vars );
	}

	public function testNoCardWithoutMentor() {
		$module = $this->newModule( IMentorManager::MENTORSHIP_ENABLED, null );

		$this->assertSame( '', $module->render() );
		$this->assertSame( [], $module->getJsConfigVars() );
	}

	public function testJsConfigVarsWithMentor() {
		$vars = $this->newModule( IMentorManager::MENTORSHIP_ENABLED, $this->newMentor() )
			->getJsConfigVars();

		$this->assertFalse( $vars['wgGrowthExperimentsMentorship']['optedOut'] );
		$this->assertStringContainsString(
			'id="editpage-copywarn"', $vars['wgGrowthExperimentsMentorshipPoster']['copyrightWarning']
		);
		$this->assertArrayHasKey( 'wgGrowthExperimentsMentorshipMentorGender', $vars );
	}
}
