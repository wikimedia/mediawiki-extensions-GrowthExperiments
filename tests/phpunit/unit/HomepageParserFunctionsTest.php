<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\HomepageParserFunctions;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Parser\Parser;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\HomepageParserFunctions
 */
class HomepageParserFunctionsTest extends MediaWikiUnitTestCase {

	public function testMentorRenderInvalidUsername(): void {
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )->willReturn( null );

		$this->assertSame( '', HomepageParserFunctions::mentorRender(
			$userFactory,
			$this->createNoOpMock( IMentorManager::class ),
			$this->createNoOpMock( Parser::class ),
			'127.0.0.1'
		) );
	}

	public function testMentorRenderNonNamedUser(): void {
		$menteeUser = $this->createMock( User::class );
		$menteeUser->method( 'isNamed' )->willReturn( false );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )->willReturn( $menteeUser );

		$this->assertSame( '', HomepageParserFunctions::mentorRender(
			$userFactory,
			$this->createNoOpMock( IMentorManager::class ),
			$this->createNoOpMock( Parser::class ),
			'~2026-1'
		) );
	}

	public function testMentorRenderNamedUserWithoutMentor(): void {
		$mentorManager = $this->createMock( IMentorManager::class );
		$mentorManager->method( 'getMentorForUserIfExists' )->willReturn( null );

		$this->assertSame( '', HomepageParserFunctions::mentorRender(
			$this->getUserFactoryWithNamedUser(),
			$mentorManager,
			$this->createNoOpMock( Parser::class ),
			'Mentee'
		) );
	}

	public function testMentorRenderNamedUserWithMentor(): void {
		$mentor = new Mentor(
			new UserIdentityValue( 12, 'FooMentor' ),
			'text',
			'',
			IMentorWeights::WEIGHT_NORMAL
		);
		$mentorManager = $this->createMock( IMentorManager::class );
		$mentorManager->method( 'getMentorForUserIfExists' )->willReturn( $mentor );

		$this->assertSame( 'FooMentor', HomepageParserFunctions::mentorRender(
			$this->getUserFactoryWithNamedUser(),
			$mentorManager,
			$this->createNoOpMock( Parser::class ),
			'Mentee'
		) );
	}

	private function getUserFactoryWithNamedUser(): UserFactory {
		$menteeUser = $this->createMock( User::class );
		$menteeUser->method( 'isNamed' )->willReturn( true );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )->willReturn( $menteeUser );
		return $userFactory;
	}
}
