<?php

namespace GrowthExperiments;

use BagOStuff;
use ConfigException;
use Exception;
use JobQueueGroup;
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
	 * @param bool $allowSelect Enable selecting and saving a mentor for the user
	 * @return bool|Mentor
	 * @throws ConfigException
	 * @throws \MWException
	 */
	public static function newFromMentee( User $mentee, $allowSelect = false ) {
		// todo: split get / getOrSelect into 2 functions
		$mentorUser = self::loadMentor( $mentee );
		if ( $mentorUser ) {
			return new static( $mentorUser );
		}

		if ( $allowSelect ) {
			$mentorUser = self::selectMentor( $mentee );
			if ( $mentorUser ) {
				self::saveMentor( $mentee, $mentorUser );
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
		if ( !$mentorId ) {
			$cache = MediaWikiServices::getInstance()->getMainObjectStash();
			$mentorId = $cache->get( self::makeCacheKey( $cache, $mentee ) );
		}
		return User::whoIs( $mentorId ) ?
			User::newFromId( $mentorId ) :
			false;
	}

	/**
	 * Saves the given $mentor in the $mentee's preferences
	 *
	 * @param User $mentee
	 * @param User $mentor
	 * @throws \MWException
	 */
	private static function saveMentor( User $mentee, User $mentor ) {
		$cache = MediaWikiServices::getInstance()->getMainObjectStash();
		$cache->set( self::makeCacheKey( $cache, $mentee ), $mentor->getId(), $cache::TTL_DAY );
		$job = new MentorSaveJob( [
			'userId' => $mentee->getId(),
			'mentorId' => $mentor->getId()
		] );
		JobQueueGroup::singleton()->lazyPush( $job );
	}

	private static function makeCacheKey( BagOStuff $cache, User $mentee ) {
		return $cache->makeKey( self::MENTOR_PREF, $mentee->getId() );
	}
}
