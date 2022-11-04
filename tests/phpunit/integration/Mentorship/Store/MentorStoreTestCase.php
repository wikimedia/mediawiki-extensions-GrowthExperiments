<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\Store\MentorStore;
use HashBagOStuff;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use WANObjectCache;
use Wikimedia\TestingAccessWrapper;

abstract class MentorStoreTestCase extends MediaWikiIntegrationTestCase {

	/** @var WANObjectCache */
	protected $wanCache;

	protected function setUp(): void {
		parent::setUp();

		$this->wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	abstract protected function getStore( bool $wasPosted ): MentorStore;

	abstract protected function getJobType(): string;

	protected function assertSameUser( UserIdentity $expectedUser, $actualUser ) {
		$this->assertInstanceOf( UserIdentity::class, $actualUser );
		$this->assertSame( $expectedUser->getId(), $actualUser->getId() );
		$this->assertSame( $expectedUser->getName(), $actualUser->getName() );
	}

	public function testGetSet() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore( true );

		$this->assertNull( $store->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
		$store->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );

		// read from cache
		$actualMentor = $store->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY );
		$this->assertSameUser( $mentor, $actualMentor );

		// delete and calculate again
		TestingAccessWrapper::newFromObject( $store )->invalidateMentorCache(
			$mentee,
			MentorStore::ROLE_PRIMARY
		);
		$actualMentor = $store->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY );
		$this->assertSameUser( $mentor, $actualMentor );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Store\MentorStore::hasAnyMentees
	 */
	public function testHasAnyMentees() {
		$menteePrimary = $this->getMutableTestUser()->getUser();
		$menteeBackup = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore( true );

		$this->assertFalse( $store->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		$this->assertFalse( $store->hasAnyMentees( $mentor, MentorStore::ROLE_BACKUP ) );

		$store->setMentorForUser( $menteePrimary, $mentor, MentorStore::ROLE_PRIMARY );
		$this->assertTrue( $store->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		$this->assertFalse( $store->hasAnyMentees( $mentor, MentorStore::ROLE_BACKUP ) );

		$store->setMentorForUser( $menteeBackup, $mentor, MentorStore::ROLE_BACKUP );
		$this->assertTrue( $store->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		$this->assertTrue( $store->hasAnyMentees( $mentor, MentorStore::ROLE_BACKUP ) );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Store\MentorStore::isMentee
	 */
	public function testIsMentee() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore( true );

		$this->assertFalse( $store->isMentee( $mentee ) );
		$store->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
		$this->assertTrue( $store->isMentee( $mentee ) );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Store\MentorStore::isMenteeActive
	 * @covers \GrowthExperiments\Mentorship\Store\MentorStore::isMenteeActiveUncached
	 */
	public function testIsMenteeActive() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore( true );

		// not a mentee yet
		$this->assertNull( $store->isMenteeActive( $mentee ) );

		// active by default
		$store->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
		$this->assertTrue( $store->isMenteeActive( $mentee ) );
	}
}
