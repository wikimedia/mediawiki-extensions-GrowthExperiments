<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\HelpPanel;
use GrowthExperiments\HelpPanelHooks;
use MediaWiki\Config\HashConfig;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @group medium
 * @covers \GrowthExperiments\HelpPanel
 */
class HelpPanelTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider providerShouldShowHelpPanel
	 */
	public function testShouldShowHelpPanel(
		TitleValue $title,
		string $action,
		bool $gesuggestededit,
		int $userId,
		int $userHelpPanelPref,
		array $excludedNamespaces,
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

		$result = HelpPanel::shouldShowHelpPanel(
			$out, true,
			new HashConfig( [ 'GEHelpPanelExcludedNamespaces' => $excludedNamespaces ] )
		);
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
				// assertion
				true,
				'Normal scenario - edit on a main namespace, suggested edit flag, user has pref enabled',
			],
			[
				new TitleValue( NS_PROJECT, 'Foo' ),
				'edit',
				false,
				1,
				1,
				[ NS_PROJECT ],
				false,
				'Namespace of title is in excluded namespaces, help panel should not show',
			],
			[
				new TitleValue( NS_MAIN, 'Foo' ),
				'blah',
				true,
				1,
				1,
				[],
				false,
				'Action of "blah" should result in help panel not showing',
			],
			[
				new TitleValue( NS_MAIN, 'Foo' ),
				'edit',
				true,
				1,
				0,
				[],
				true,
				'User has help panel disabled, but gesuggestededit is set, so the help panel should show',
			],
			[
				new TitleValue( NS_MAIN, 'Foo' ),
				'edit',
				true,
				0,
				0,
				[],
				false,
				'gesuggestededit is true, but user is anonymous so the help pane should not show',
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
				// assertion
				true,
				'Suggested edits mode and NS_MAIN excluded',
			],
		];
	}
}
