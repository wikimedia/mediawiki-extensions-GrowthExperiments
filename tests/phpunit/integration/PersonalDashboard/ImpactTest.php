<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\PersonalDashboard\Impact;
use GrowthExperiments\UserDatabaseHelper;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use ReflectionMethod;

/**
 * @group medium
 * @covers \GrowthExperiments\PersonalDashboard\Impact
 */
class ImpactTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'PersonalDashboard' );
	}

	/**
	 * A mocked User, not a persisted test user: nothing under test here
	 * touches the database (UserDatabaseHelper and UserOptionsLookup are
	 * mocked in newImpactModule()), so a real account would only add an
	 * unnecessary @group Database dependency.
	 */
	private function newTestUser(): User {
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		$user->method( 'getName' )->willReturn( 'TestUser' );
		return $user;
	}

	private function newImpactModule(
		DerivativeContext $context,
		?bool $hasMainspaceEdits = false,
		bool $userOptionsLookupActivated = false
	): Impact {
		$userDatabaseHelper = $this->createMock( UserDatabaseHelper::class );
		$userDatabaseHelper->method( 'hasMainspaceEdits' )->willReturn( $hasMainspaceEdits );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getBoolOption' )->willReturn( $userOptionsLookupActivated );

		return new Impact(
			$context,
			$userDatabaseHelper,
			$userOptionsLookup
		);
	}

	public function testCanRender() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->newTestUser() );
		$impactModule = $this->newImpactModule( $context );

		$reflectionMethod = new ReflectionMethod( Impact::class, 'canRender' );
		$this->assertTrue( $reflectionMethod->invoke( $impactModule ) );
	}

	public function testServerRendered() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->newTestUser() );
		$impactModule = $this->newImpactModule( $context );

		$reflectionMethod = new ReflectionMethod( Impact::class, 'serverRendered' );
		$this->assertTrue( $reflectionMethod->invoke( $impactModule ) );
	}

	public function testGetModules() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->newTestUser() );
		$impactModule = $this->newImpactModule( $context );

		$reflectionMethod = new ReflectionMethod( Impact::class, 'getModules' );
		$this->assertSame(
			[ 'ext.growthExperiments.Homepage.Impact' ],
			$reflectionMethod->invoke( $impactModule )
		);
	}

	public function testGetBodyContainsMountDiv() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->newTestUser() );
		$impactModule = $this->newImpactModule( $context );

		$reflectionMethod = new ReflectionMethod( Impact::class, 'getBody' );
		$body = $reflectionMethod->invoke( $impactModule );

		$this->assertIsString( $body );
		$this->assertStringContainsString( 'id="impact-vue-root"', $body );
		$this->assertStringContainsString( 'growthexperiments-homepage-impact-no-js-fallback', $body );
	}

	public function testGetModuleStyles() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->newTestUser() );
		$impactModule = $this->newImpactModule( $context );

		$reflectionMethod = new ReflectionMethod( Impact::class, 'getModuleStyles' );
		$this->assertSame(
			[ 'ext.growthExperiments.PersonalDashboard.Impact.styles' ],
			$reflectionMethod->invoke( $impactModule )
		);
	}

	public function testGetJsConfigVarsForUnactivatedUser() {
		$user = $this->newTestUser();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$impactModule = $this->newImpactModule( $context, false, false );

		$configVars = $impactModule->getJsConfigVars();

		$this->assertSame( $user->getName(), $configVars['GEImpactRelevantUserName'] );
		$this->assertSame( $user->getId(), $configVars['GEImpactRelevantUserId'] );
		$this->assertTrue( $configVars['GEImpactRelevantUserUnactivated'] );
		$this->assertFalse( $configVars['GEImpactIsSuggestedEditsActivatedForUser'] );
		$this->assertArrayHasKey( 'GEImpactIsSuggestedEditsEnabledForUser', $configVars );
		$this->assertArrayHasKey( 'GEImpactMaxEdits', $configVars );
		$this->assertArrayHasKey( 'GEImpactMaxThanks', $configVars );
	}

	public function testGetJsConfigVarsForActivatedUser() {
		$user = $this->newTestUser();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$impactModule = $this->newImpactModule( $context, true, true );

		$configVars = $impactModule->getJsConfigVars();

		$this->assertFalse( $configVars['GEImpactRelevantUserUnactivated'] );
		$this->assertTrue( $configVars['GEImpactIsSuggestedEditsActivatedForUser'] );
	}

	/**
	 * A null hasMainspaceEdits() (first 1000 edits are all non-mainspace) is
	 * treated as unactivated, same as the Homepage module's getState().
	 */
	public function testGetJsConfigVarsForNullMainspaceEdits() {
		$user = $this->newTestUser();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$impactModule = $this->newImpactModule( $context, null, false );

		$configVars = $impactModule->getJsConfigVars();

		$this->assertTrue( $configVars['GEImpactRelevantUserUnactivated'] );
	}
}
