<?php

namespace GrowthExperiments\Tests\Helpers;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

trait CreateMenteeHelpers {

	/**
	 * Stub from MediaWikiIntegrationTestCase
	 *
	 * @param string|PageIdentity|LinkTarget|WikiPage $page the page to edit
	 * @param string|Content $content the new content of the page
	 * @param string $summary Optional summary string for the revision
	 * @param int $defaultNs Optional namespace id
	 * @param Authority|null $performer If null, static::getTestUser()->getAuthority() is used.
	 * @return PageUpdateStatus Object as returned by WikiPage::doUserEditContent()
	 * @see MediaWikiIntegrationTestCase::editPage()
	 */
	abstract protected function editPage(
		$page,
		$content,
		$summary = '',
		$defaultNs = NS_MAIN,
		?Authority $performer = null
	);

	/**
	 * Stub from MediaWikiIntegrationTestCase
	 *
	 * @return MediaWikiServices
	 * @see MediaWikiIntegrationTestCase::getServiceContainer()
	 */
	abstract protected function getServiceContainer();

	private function getMentorStore(): MentorStore {
		return GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getMentorStore();
	}

	protected function createMentee(
		User $mentor,
		array $overrides = [],
		?string $namePartial = null
	): User {
		$mentee = $this->getMutableTestUser(
			$overrides[ 'user_groups' ] ?? [],
			isset( $namePartial ) ? ucfirst( $namePartial ) : null,
		)->getUser();

		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $mentee, HomepageHooks::HOMEPAGE_PREF_ENABLE, '1' );
		if ( isset( $overrides['user_options'] ) ) {
			foreach ( $overrides['user_options'] as $key => $value ) {
				$userOptionsManager->setOption( $mentee, $key, $value );
			}
		}
		$userOptionsManager->saveOptions( $mentee );

		if ( array_key_exists( 'registration', $overrides ) ) {
			$this->setMenteeRegistration(
				$mentee,
				$overrides['registration']
			);

			// user_registration was likely read already, recreate the user
			$mentee = $this->getServiceContainer()->getUserFactory()->newFromId( $mentee->getId() );
		}

		if ( isset( $overrides['edit_count'] ) ) {
			$this->setMenteeEditCount( $mentee, $overrides['edit_count'] );
		}

		if ( isset( $overrides['blocked_infinity'] ) ) {
			$sysop = $this->getTestSysop()->getUser();
			$blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
			$blockUserFactory->newBlockUser( $mentee, $sysop, 'infinity' )->placeBlock();
		}

		$this->getMentorStore()->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
		return $mentee;
	}

	protected function setMenteeEditCount( User $mentee, int $editCount ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_editcount' => $editCount - 1 ] )
			->where( [ 'user_id' => $mentee->getId() ] )
			->caller( __METHOD__ )
			->execute();

		// TODO: is there a faster way to do this?
		$this->editPage(
			'TestPage',
			'Test content: ' . microtime( true ),
			'Make edit to ensure there is a last edit timestamp',
			0,
			$mentee
		);
	}

	protected function createMenteeWithEditCount( User $mentor, int $editcount ): User {
		return $this->createMentee(
			$mentor,
			[ 'edit_count' => $editcount ],
			'editcount ' . $editcount
		);
	}

	protected function createMenteeWithBlocks( User $mentor, int $blocks ): User {
		$mentee = $this->createMentee( $mentor, [], 'blocks ' . $blocks );
		$blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
		$unblockUserFactory = $this->getServiceContainer()->getUnblockUserFactory();
		$sysop = $this->getTestSysop()->getUser();
		for ( $i = 0; $i < $blocks; $i++ ) {
			$blockUserFactory->newBlockUser( $mentee, $sysop, '1 second' )->placeBlock();
			$unblockUserFactory->newUnblockUser( $mentee, $sysop, '' )->unblock();
		}
		return $mentee;
	}

	protected function setMenteeRegistration( User $mentee, ?string $registration ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_registration' => $registration ] )
			->where( [ 'user_id' => $mentee->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	protected function createMenteeWithRegistration( User $mentor, ?string $registration ): User {
		return $this->createMentee(
			$mentor,
			[
				'registration' => $registration,
			],
			'registration ' . $registration
		);
	}
}
