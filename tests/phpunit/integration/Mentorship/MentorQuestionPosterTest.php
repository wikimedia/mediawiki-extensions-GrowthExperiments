<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;

/**
 * @group medium
 * @group Database
 */
class MentorQuestionPosterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::__construct
	 */
	public function testConstruct() {
		$mentorUser = $this->getTestSysop()->getUser();
		$module = $this->getQuestionPosterModule( $mentorUser );
		$spy = TestingAccessWrapper::newFromObject( $module );
		$this->assertTrue( $mentorUser->getTalkPage()->equals( $spy->getTargetTitle() ) );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::__construct
	 */
	public function testCanConstructMentorQuestionPosterWithoutThrowingAnExceptionWhenThereIsNoMentor() {
		$this->getQuestionPosterModule( null );
		$this->assertTrue( true );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\QuestionPoster::__construct
	 * @dataProvider provideTestAwayDisclaimer
	 */
	public function testAwayDisclaimer( bool $mentorIsAway, ?string $expectedResult = null ) {
		$mentorUser = $this->getTestSysop()->getUser();
		$mentorUser->setName( 'Mentor' );
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$tomorrow = $mentorIsAway ? strtotime( '+1 day' ) : null;
		$userOptionsManager->setOption( $mentorUser, MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF, $tomorrow );
		$module = $this->getQuestionPosterModule( $mentorUser );
		$wrappedQuestionPoster = TestingAccessWrapper::newFromObject( $module );
		$wrappedQuestionPoster->sectionHeader = "Foo bar baz";
		$actualResult = $wrappedQuestionPoster->makeWikitextContent();

		$this->assertInstanceOf( WikitextContent::class, $actualResult );
		if ( $expectedResult === null ) {
			$this->assertStringNotContainsString(
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'[[User:Mentee|Mentee]]\'s mentor <span class="plainlinks">[{{fullurl:User:Mentor}} Mentor]</span> is away',
				$actualResult->getText()
			);
		} else {
			$this->assertStringContainsString( $expectedResult, $actualResult->getText() );
		}
	}

	/**
	 * @return MentorQuestionPoster|mixed|MockObject
	 */
	private function getQuestionPosterModule( ?User $mentorUser ) {
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
		$titleFactory = $this->getServiceContainer()->getTitleFactory();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$mentorManager = $this->createMock( IMentorManager::class );
		$mentorStatusManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStatusManager();
		$context = new DerivativeContext( RequestContext::getMain() );
		$testUser = $this->getTestUser()->getUser();
		// If there is a mentor and a mentee, the user in context should always be the mentee
		$testUser->setName( 'Mentee' );
		$context->setUser( $testUser );

		if ( $mentorUser ) {
			$mentor = new Mentor( $mentorUser, '*', '', IMentorWeights::WEIGHT_NORMAL );
			$mentorManager->method( 'getMentorForUserSafe' )->willReturn( $mentor );
			$mentorManager->method( 'getEffectiveMentorForUserSafe' )->willReturn( $mentor );
		}

		return $this->getMockBuilder( MentorQuestionPoster::class )
			->setConstructorArgs( [
				$wikiPageFactory,
				$titleFactory,
				$mentorManager,
				$mentorStatusManager,
				$permissionManager,
				$this->getServiceContainer()->getStatsFactory(),
				ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
				ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
				$context,
				'foo',
			] )
			->getMockForAbstractClass();
	}

	public function provideTestAwayDisclaimer() {
		return [
			'Mentor is away' => [
				true,
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'[[User:Mentee|Mentee]]\'s mentor <span class="plainlinks">[{{fullurl:User:Mentor}} Mentor]</span> is away',
			],
			'Mentor is not away' => [
				false,
				null,
			],
		];
	}

}
