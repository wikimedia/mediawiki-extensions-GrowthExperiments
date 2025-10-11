<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Tests\Helpers\HomepageHooksHelpers;
use MediaWiki\Config\HashConfig;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MessageLocalizer;

/**
 * @coversDefaultClass \GrowthExperiments\HomepageHooks
 */
class HomepageHooksTest extends MediaWikiUnitTestCase {
	use HomepageHooksHelpers;

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( HomepageHooks::class, $this->getHomepageHooksMock() );
	}

	/**
	 * @covers ::onContributeCards
	 */
	public function testOnContributeCards() {
		$titleFactoryMock = $this->createMock( TitleFactory::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getLocalNameFor' )
			->willReturn( 'Homepage' );
		$homepageTitleMock = $this->createMock( Title::class );
		$homepageTitleMock->method( 'getLinkURL' )->willReturn( '/foo/bar/' );
		$titleFactoryMock->method( 'newFromLinkTarget' )
			->willReturn( $homepageTitleMock );

		$userIdentity = new UserIdentityValue( 1, 'Foo' );

		$messageLocalizerMock = $this->createMock( MessageLocalizer::class );
		$messageMock = $this->createMock( Message::class );
		$messageMock->method( 'text' )->willReturn( 'Foo' );
		$messageLocalizerMock->method( 'msg' )->willReturn( $messageMock );
		$outputPageMock = $this->createMock( OutputPage::class );

		// Scenario 1: Homepage enabled AND SuggestedEdits enabled - card should be shown
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getBoolOption' )
			->with( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE )
			->willReturn( true );

		$configWithSEEnabled = new HashConfig( [
			'GEHomepageSuggestedEditsEnabled' => true,
		] );

		$homepageHooks = $this->getHomepageHooksMock(
			$configWithSEEnabled,
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMock
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );

		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [ [
			'title' => 'Foo',
			'icon' => 'lightbulb',
			'description' => 'Foo',
			'action' => [
				'action' => '/foo/bar/',
				'actionText' => 'Foo',
				'actionType' => 'link',
			] ],
		], $cards, false, true, 'Card should be shown when Homepage and SE are enabled' );

		// Scenario 2: Homepage preference disabled - no card
		$userOptionsLookupMockDisabled = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMockDisabled->method( 'getBoolOption' )
			->with( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE )
			->willReturn( false );

		$homepageHooks = $this->getHomepageHooksMock(
			$configWithSEEnabled,
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMockDisabled
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );

		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [], $cards, false, true,
			'No card should be shown when Homepage preference is disabled' );

		// Scenario 3: Homepage enabled but SuggestedEdits disabled - no card
		$configWithSEDisabled = new HashConfig( [
			'GEHomepageSuggestedEditsEnabled' => false,
		] );

		$homepageHooks = $this->getHomepageHooksMock(
			$configWithSEDisabled,
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMock
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );

		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [], $cards, false, true,
			'No card should be shown when SuggestedEdits is disabled even if Homepage is enabled' );
	}
}
