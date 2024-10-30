<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\HelpPanel;
use GrowthExperiments\HelpPanelHooks;
use MediaWiki\Config\HashConfig;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group medium
 * @coversDefaultClass \GrowthExperiments\HelpPanel
 */
class HelpPanelTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::getHelpDeskTitle
	 */
	public function testGetHelpDeskTitle() {
		$sitename = $this->getServiceContainer()->getMainConfig()->get( 'Sitename' );
		$config = new HashConfig( [
			'GEHelpPanelHelpDeskTitle' => 'HelpDesk/{{SITENAME}}'
		] );

		$title = HelpPanel::getHelpDeskTitle( $config );
		$title->resetArticleID( 0 );

		$this->assertSame( "HelpDesk/$sitename", $title->getText() );
		$this->assertTrue( $title->isValid(), 'Title is valid' );
	}

	/**
	 * @covers ::shouldShowHelpPanel
	 * @dataProvider providerShouldShowHelpPanel
	 */
	public function testShouldShowHelpPanel(
		TitleValue $title,
		string $action,
		bool $gesuggestededit,
		int $userId,
		int $userHelpPanelPref,
		array $excludedNamespaces,
		bool $GEHelpPanelEnabled,
		bool $expected,
		string $message
	) {
		$out = $this->createMock( OutputPage::class );
		$out->method( 'getTitle' )
			->willReturn( $title );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getVal' )
			->with( 'action' )
			->willReturn( $action );
		$request->method( 'getBool' )
			->with( 'gesuggestededit' )
			->willReturn( $gesuggestededit );
		$user = $this->createPartialMock( User::class, [ 'isNamed' ] );
		$user->method( 'isNamed' )
			->willReturn( (bool)$userId );
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getOption' )
			->with( $this->anything(), HelpPanelHooks::HELP_PANEL_PREFERENCES_TOGGLE )
			->willReturn( $userHelpPanelPref );
		$this->setService( 'UserOptionsLookup', $userOptionsLookupMock );
		$out->method( 'getUser' )
			->willReturn( $user );
		$out->method( 'getRequest' )
			->willReturn( $request );
		$this->overrideConfigValues( [
			'GEHelpPanelExcludedNamespaces' => $excludedNamespaces,
			'GEHelpPanelEnabled' => $GEHelpPanelEnabled
		] );

		$result = HelpPanel::shouldShowHelpPanel( $out );
		$this->assertEquals( $expected, $result, $message );
	}

	public static function providerShouldShowHelpPanel(): array {
		return [
			[
				// title
				new TitleValue( NS_MAIN, 'Foo' ),
				// action=
				'edit',
				// getBool( 'gesuggestededit' )
				true,
				// user ID
				1,
				// user help panel pref
				1,
				// GEHelpPanelExcludedNamespaces,
				[],
				// GEHelpPanelEnabled
				true,
				// assertion
				true,
				'Normal scenario - edit on a main namespace, suggested edit flag, user has pref enabled'
			],
			[
				new TitleValue( NS_PROJECT, 'Foo' ),
				'edit',
				false,
				1,
				1,
				[ NS_PROJECT ],
				true,
				false,
				'Namespace of title is in excluded namespaces, help panel should not show'
			],
			[
				new TitleValue( NS_MAIN, 'Foo' ),
				'blah',
				true,
				1,
				1,
				[],
				true,
				false,
				'Action of "blah" should result in help panel not showing'
			],
			[
				new TitleValue( NS_MAIN, 'Foo' ),
				'edit',
				true,
				1,
				0,
				[],
				true,
				true,
				'User has help panel disabled, but gesuggestededit is set, so the help panel should show'
			],
			[
				new TitleValue( NS_MAIN, 'Foo' ),
				'edit',
				true,
				0,
				0,
				[],
				true,
				false,
				'gesuggestededit is true, but user is anonymous so the help pane should not show'
			],
			[
				// title
				new TitleValue( NS_MAIN, 'Foo' ),
				// action=
				'edit',
				// getBool( 'gesuggestededit' )
				true,
				// user ID
				1,
				// user help panel pref
				1,
				// GEHelpPanelExcludedNamespaces,
				[ NS_MAIN ],
				// GEHelpPanelEnabled
				true,
				// assertion
				true,
				'Suggested edits mode and NS_MAIN excluded'
			],
		];
	}
}
