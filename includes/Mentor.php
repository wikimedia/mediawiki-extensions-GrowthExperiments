<?php

namespace GrowthExperiments;

use ConfigException;
use DeferredUpdates;
use Exception;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use Title;
use User;
use WikiPage;

class Mentor {

	const MENTOR_PREF = 'growthexperiments-mentor-id';

	/**
	 * @var User
	 */
	private $mentorUser;

	private function __construct( $mentorUser ) {
		$this->mentorUser = $mentorUser;
	}

	/**
	 * @param User $mentee
	 * @param bool $allowSelect Enable selecting a mentor for the user
	 * @return bool|Mentor
	 * @throws ConfigException
	 */
	public static function newFromMentee( User $mentee, $allowSelect = false ) {
		$mentorUser = self::loadMentor( $mentee );
		if ( $mentorUser ) {
			return new static( $mentorUser );
		}

		if ( $allowSelect ) {
			$mentorUser = self::selectMentor( $mentee );
			if ( $mentorUser ) {
				return new static( $mentorUser );
			}
		}

		return false;
	}

	/**
	 * @return User
	 */
	public function getMentorUser() {
		return $this->mentorUser;
	}

	/**
	 * Randomly selects a mentor from a list on a wiki page.
	 *
	 * @param User $mentee
	 * @return bool|User The selected mentor or false if none are available
	 * @throws ConfigException
	 * @throws Exception
	 */
	private static function selectMentor( User $mentee ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$mentorsPageName = $config->get( 'GEHomepageMentorsList' );
		$title = Title::newFromText( $mentorsPageName );
		if ( !$title || !$title->exists() ) {
			throw new Exception( 'wgGEHomepageMentorsList is invalid: ' . $mentorsPageName );
		}
		$page = WikiPage::factory( $title );
		$links = $page->getParserOutput( ParserOptions::newCanonical() )->getLinks();
		if ( !isset( $links[ NS_USER ] ) ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				'Homepage Mentorship module: no mentor available for user {user}',
				[ 'user' => $mentee->getName() ]
			);
			return false;
		}
		$possibleMentors = array_keys( $links[ NS_USER ] );
		$selectedMentorName = $possibleMentors[ rand( 0, count( $possibleMentors ) - 1 ) ];
		$selectedMentor = User::newFromName( $selectedMentorName );
		if ( !$selectedMentor || !$selectedMentor->getId() ) {
			throw new Exception( 'Invalid mentor: ' . $selectedMentorName );
		}
		return $selectedMentor;
	}

	/**
	 * @param User $mentee
	 * @return bool|User The current user's mentor or false if they don't have one
	 */
	private static function loadMentor( User $mentee ) {
		$mentorId = $mentee->getIntOption( self::MENTOR_PREF );
		return User::whoIs( $mentorId ) ?
			User::newFromId( $mentorId ) :
			false;
	}

	/**
	 * Saves the given $mentor in the $mentee's preferences
	 *
	 * @param User $mentee
	 * @param User $mentor
	 */
	public static function saveMentor( User $mentee, User $mentor ) {
		DeferredUpdates::addCallableUpdate( function () use ( $mentee, $mentor ) {
			$user = User::newFromId( $mentee->getId() );
			$user->setOption( Mentor::MENTOR_PREF, $mentor->getId() );
			$user->saveSettings();
		} );
	}

}
