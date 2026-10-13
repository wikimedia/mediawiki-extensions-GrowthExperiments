<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\UserNotMentoredException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
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
	public function testConstructMentorQuestionPosterWhenThereIsNoMentorThrowsAnException() {
		try {
			$this->getQuestionPosterModule( null );
		} catch ( UserNotMentoredException $e ) {
			$this->assertSame(
				'User has no mentor, which means they cannot ask their mentor a question',
				$e->getMessage() );
		}
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
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster\MentorQuestionPoster::__construct
	 * @dataProvider provideTestSectionHeader
	 */
	public function testSectionHeader(
		string $contentModel,
		?string $expectedResult,
		bool $translationExists
	) {
		$mentorUser = $this->getTestSysop()->getUser();
		$mentorUser->setName( 'Mentor' );
		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$matcher = $this->exactly( 2 );
		$messageLocalizer->expects( $matcher )
			->method( 'msg' )
			->willReturnCallback( function ( string $key ) use ( $matcher, $translationExists ) {
				return match ( $matcher->getInvocationCount() ) {
					1 => $this->createConfiguredMock( Message::class, [
						'exists' => $translationExists,
					] ),
					2 => new Message( 'growthexperiments-homepage-mentorship-question-subject-with-title' ),
				};
			} );
		$module = $this->getQuestionPosterModule( $mentorUser, $messageLocalizer );
		$wrappedQuestionPoster = TestingAccessWrapper::newFromObject( $module );
		$wrappedQuestionPoster->postedOnTimestamp = 2;
		$wrappedQuestionPoster->existingQuestionsByUser = [];
		$wrappedQuestionPoster->relevantTitleRaw = 'title';
		$wrappedQuestionPoster->relevantTitle = $this->createMock( Title::class );
		$wrappedQuestionPoster->relevantTitle->method( 'getPrefixedText' )->willReturn( 'ArticleTitle' );
		$wrappedQuestionPoster->relevantTitle->method( 'getContentModel' )->willReturn( $contentModel );

		$wrappedQuestionPoster->setSectionHeader();
		$actualResult = $wrappedQuestionPoster->makeWikitextContent();

		$this->assertInstanceOf( WikitextContent::class, $actualResult );
		$this->assertEquals( $expectedResult, $actualResult->getText() );
	}

	/**
	 * @return MentorQuestionPoster|mixed|MockObject
	 */
	private function getQuestionPosterModule( ?User $mentorUser, ?MessageLocalizer $messageLocalizer = null ) {
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
				$this->getServiceContainer()->getRedirectLookup(),
				$mentorManager,
				$mentorStatusManager,
				$permissionManager,
				$this->getServiceContainer()->getStatsFactory(),
				$this->getServiceContainer()->getUserFactory(),
				ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
				ExtensionRegistry::getInstance()->isLoaded( 'Flow' ),
				$context,
				$messageLocalizer ?? $context,
				'foo',
			] )
			->getMockForAbstractClass();
	}

	public static function provideTestAwayDisclaimer() {
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

	public static function provideTestSectionHeader() {
		return [
			'Page is article' => [
				'article',
				'== Question from [[User:Mentee|Mentee]] on [[:ArticleTitle|ArticleTitle]]' .
				" (00:00, 1 January 1970) ==\n" .
				'foo --~~~~',
				true,
			],
			'Page is item' => [
				'wikibase-item',
				'== Question from [[User:Mentee|Mentee]] on [[:ArticleTitle|ArticleTitle]]' .
				" (00:00, 1 January 1970) ==\n" .
				'foo --~~~~',
				true,
			],
			'Page is property' => [
				'wikibase-property',
				'== Question from [[User:Mentee|Mentee]] on [[:ArticleTitle|ArticleTitle]]' .
				" (00:00, 1 January 1970) ==\n" .
				'foo --~~~~',
				true,
			],
			'Page is unknown content model' => [
				'wikibase-unproperty',
				'== Question from [[User:Mentee|Mentee]] on [[:ArticleTitle|ArticleTitle]]' .
				" (00:00, 1 January 1970) ==\n" .
				'foo --~~~~',
				false,
			],
		];
	}

}
