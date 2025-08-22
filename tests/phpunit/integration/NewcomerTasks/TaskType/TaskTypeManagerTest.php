<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\ErrorException;
use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TaskTypeManager
 * @group Database
 */
class TaskTypeManagerTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'GEImproveToneSuggestedEditEnabled', false );
	}

	public function testFiltersTaskWhenLimitNotEnabledByFeatureFlagDefault() {
		$user = $this->createUserWithEdits( 1000 );
		$this->setMaxEditsTaskIsAvailableInConfig( '20' );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getTaskTypeManager();
		$result = $sut->getTaskTypesForUser( $user );
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $result );
	}

	public function testFiltersTaskWhenLimitNotEnabledByFeatureFlag() {
		$user = $this->createUserWithEdits( 1000 );
		$this->setMaxEditsTaskIsAvailableInConfig( '20' );
		$this->overrideConfigValues( [
			'GENewcomerTasksStarterDifficultyEnabled' => false,
		] );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getTaskTypeManager();
		$result = $sut->getTaskTypesForUser( $user );
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $result );
	}

	public function testFiltersTaskWhenLimitNotEnabledByConfigDefault() {
		$user = $this->createUserWithEdits( 1000 );
		$this->overrideConfigValues( [
			'GENewcomerTasksStarterDifficultyEnabled' => true,
		] );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getTaskTypeManager();
		$result = $sut->getTaskTypesForUser( $user );
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $result );
	}

	public function testFiltersTaskWhenLimitNotReached() {
		$user = $this->createUserWithEdits( 0 );
		$this->overrideConfigValues( [
			'GENewcomerTasksStarterDifficultyEnabled' => true,
		] );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getTaskTypeManager();
		$this->setMaxEditsTaskIsAvailableInConfig( '150' );
		$result = $sut->getTaskTypesForUser( $user );
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $result );
	}

	public function testFiltersTaskWhenLimitReached() {
		$user = $this->createUserWithEdits( 21 );
		$this->overrideConfigValues( [
			'GENewcomerTasksStarterDifficultyEnabled' => true,
		] );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getTaskTypeManager();
		$this->setMaxEditsTaskIsAvailableInConfig( '20' );
		$result = $sut->getTaskTypesForUser( $user );
		$this->assertSame( [ 'copyedit' ], $result );
	}

	public function testFiltersTaskWhenLimitReachedImproveToneEnabled() {
		$user = $this->createUserWithEdits( 21 );
		$this->overrideConfigValues( [
			'GENewcomerTasksStarterDifficultyEnabled' => true,
			'GEImproveToneSuggestedEditEnabled' => true,
		] );
		$sut = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getTaskTypeManager();
		$this->setMaxEditsTaskIsAvailableInConfig( '20' );
		$result = $sut->getTaskTypesForUser( $user );
		$this->assertSame( [ 'copyedit', 'improve-tone' ], $result );
	}

	private function createUserWithEdits( ?int $editCount = 10 ): User {
		$user = $this->getMutableTestUser( [
			'interface-admin',
		] )->getUser();
		$this->setUserEditCount( $user, $editCount );
		$ctx = RequestContext::getMain();
		$ctx->setUser( $user );
		return $user;
	}

	private function setUserEditCount( User $user, int $editCount ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_editcount' => $editCount ] )
			->where( [ 'user_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @throws ErrorException
	 */
	private function setMaxEditsTaskIsAvailableInConfig( string $selectedEnumValue = 'no' ): void {
		$communityConfigServices = CommunityConfigurationServices::wrap( $this->getServiceContainer() );
		$suggestedEditsProvider = $communityConfigServices
			->getConfigurationProviderFactory()->newProvider( 'GrowthSuggestedEdits' );
		$status = $suggestedEditsProvider->loadValidConfiguration();
		$config = null;
		if ( $status->isOK() ) {
			$config = $status->getValue();
			$config->{'link_recommendation'}->{'maximumEditsTaskIsAvailable'} = $selectedEnumValue;
		}
		$status = $suggestedEditsProvider->storeValidConfiguration(
			$config, $this->getTestUser( [ 'interface-admin' ] )->getUser()
		);
		if ( !$status->isOK() ) {
			throw new ErrorException( $status );
		}
	}
}
