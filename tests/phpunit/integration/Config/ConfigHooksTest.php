<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiQueryTokens;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Content\JsonContent;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Session\SessionManager;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Config\ConfigHooks
 * @covers \GrowthExperiments\Config\LegacyConfigHooks
 * @group Database
 * @group medium
 *
 * Verify that config validators are triggered when an user triggers
 * an edit.
 */
class ConfigHooksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'GEUseCommunityConfigurationExtension', false );
	}

	/**
	 * Attempt to save an edit to the config page
	 *
	 * This uses action=edit to mimic an edit triggered by an user.
	 *
	 * @param Title $configPage Page to edit
	 * @param array $content
	 * @return array Result of action=edit API call
	 */
	private function saveConfigPage( Title $configPage, array $content ): array {
		$performer = $this->getTestSysop()->getUser();
		$sessionObj = SessionManager::singleton()->getEmptySession();
		$sessionObj->setUser( $performer );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $performer );
		$context->setRequest( new FauxRequest(
			[
				'action' => 'edit',
				'format' => 'json',
				'title' => $configPage->getPrefixedText(),
				'text' => FormatJson::encode( $content ),
				'token' => ApiQueryTokens::getToken(
					$performer,
					$sessionObj,
					ApiQueryTokens::getTokenTypeSalts()['csrf']
				)->toString()
			],
			true,
			$sessionObj
		) );

		$api = new ApiMain( $context, true );
		$api->execute();
		return $api->getResult()->getResultData();
	}

	/**
	 * Read content of $configPage
	 *
	 * @param LinkTarget $configPage
	 * @return array
	 */
	private function getConfigPageContent( LinkTarget $configPage ): array {
		$rev = $this->getServiceContainer()->getRevisionLookup()
			->getRevisionByTitle( $configPage );
		$content = $rev->getContent( SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC );
		if ( !$content instanceof JsonContent ) {
			return [];
		}
		return FormatJson::decode( $content->getText(), FormatJson::FORCE_ASSOC );
	}

	/**
	 * @dataProvider provideApiEdit
	 * @param string|null $expectedError
	 * @param array $content
	 */
	public function testApiEdit( ?string $expectedError, array $content ) {
		if ( $expectedError !== null ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expectedError );
		}

		$title = Title::newFromText( 'MediaWiki:GrowthMentors.json' );
		$this->saveConfigPage( $title, $content );

		if ( $expectedError === null ) {
			$this->assertTrue( $title->exists() );
			$this->assertArrayEquals( $content, $this->getConfigPageContent( $title ) );
		} else {
			$this->assertFalse( $title->exists() );
		}
	}

	public static function provideApiEdit() {
		return [
			'success' => [
				'expectedError' => null,
				'content' => [ 'Mentors' => [] ],
			],
			'fatalError' => [
				'expectedError' => 'Key "Mentors" is missing',
				'content' => [ 'Mentors123' => [] ],
			],
			'warning' => [
				'expectedError' => 'Introduction message is too long (affected user ID: 123). Maximum length is 240.',
				'content' => [ 'Mentors' => [
					123 => [
						'message' => str_repeat( 'a', MentorProvider::INTRO_TEXT_LENGTH + 1 ),
						'weight' => 2,
						'automaticallyAssigned' => false,
					]
				] ],
			]
		];
	}
}
