<?php

namespace GrowthExperiments\Tests;

use CachedBagOStuff;
use GrowthExperiments\Mentorship\Store\MentorStore;
use HashBagOStuff;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

abstract class MentorStoreTestCase extends MediaWikiIntegrationTestCase {

	public function testGetSet() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore( true );

		$cache = new HashBagOStuff();
		$store->setCache( $cache, 1000 );
		/** @var CachedBagOStuff $multiCache */
		$multiCache = TestingAccessWrapper::newFromObject( $store )->cache;
		/** @var HashBagOStuff $inProcessCache */
		$inProcessCache = TestingAccessWrapper::newFromObject( $multiCache )->procCache;

		$this->assertNull( $store->loadMentorUser( $mentee ) );
		$store->setMentorForUser( $mentee, $mentor );

		// read from in-process cache
		$actualMentor = $store->loadMentorUser( $mentee );
		$this->assertSameUser( $mentor, $actualMentor );

		// read from external cache
		$inProcessCache->clear();
		$actualMentor = $store->loadMentorUser( $mentee );
		$this->assertSameUser( $mentor, $actualMentor );

		// read from disk
		$inProcessCache->clear();
		$cache->clear();
		$actualMentor = $store->loadMentorUser( $mentee );
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
