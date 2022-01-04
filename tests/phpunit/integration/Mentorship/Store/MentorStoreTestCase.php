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

	abstract protected function getStore( bool $wasPosted ): MentorStore;

	abstract protected function getJobType(): string;

	protected function assertSameUser( UserIdentity $expectedUser, $actualUser ) {
		$this->assertInstanceOf( UserIdentity::class, $actualUser );
		$this->assertSame( $expectedUser->getId(), $actualUser->getId() );
		$this->assertSame( $expectedUser->getName(), $actualUser->getName() );
	}

}
