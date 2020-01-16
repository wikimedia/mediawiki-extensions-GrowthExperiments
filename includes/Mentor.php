<?php

namespace GrowthExperiments;

use ConfigException;
use DeferredUpdates;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MWException;
use ParserOptions;
use Title;
use User;
use WikiPage;

class Mentor {

	const MENTOR_PREF = 'growthexperiments-mentor-id';
	const INTRO_TEXT_LENGTH = 240;

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
	 * @param User[] $excluded
	 * @return User The selected mentor
	 * @throws ConfigException
	 * @throws WikiConfigException
	 * @throws MWException
	 */
	private static function selectMentor( User $mentee, array $excluded = [] ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$mentorsPageName = $config->get( 'GEHomepageMentorsList' );
		$title = Title::newFromText( $mentorsPageName );
		if ( !$title || !$title->exists() ) {
			throw new WikiConfigException( 'wgGEHomepageMentorsList is invalid: ' . $mentorsPageName );
		}
		$page = WikiPage::factory( $title );
		$links = $page->getParserOutput( ParserOptions::newCanonical() )->getLinks();
		if ( !isset( $links[ NS_USER ] ) ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available for user ' . $mentee->getName()
			);
		}
		$possibleMentors = array_keys( $links[ NS_USER ] );
		$possibleMentors = array_values( array_diff( $possibleMentors, [ $mentee->getTitleKey() ] ) );
		if ( count( $possibleMentors ) === 0 ) {
			throw new WikiConfigException(
				'Homepage Mentorship module: no mentor available for ' .
				$mentee->getName() .
				' but themselves'
			);
		}
		$titleKeys = [];
		foreach ( $excluded as $user ) {
			$titleKeys[] = $user->getTitleKey();
		}
		$possibleMentors = array_values( array_diff( $possibleMentors, $titleKeys ) );
		if ( count( $possibleMentors ) === 0 ) {
			throw new MWException(
				'Homepage Mentorship module: no mentor available for ' .
				$mentee->getName() .
				' but excluded users'
			);
		}

		$selectedMentorName = $possibleMentors[ rand( 0, count( $possibleMentors ) - 1 ) ];
		$selectedMentor = User::newFromName( $selectedMentorName );
		if ( !$selectedMentor || !$selectedMentor->getId() ) {
			throw new WikiConfigException( 'Invalid mentor: ' . $selectedMentorName );
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

	/**
	 * Returns the custom introduction text for a mentor or falls back to a default text
	 * @param IContextSource $context
	 * @return mixed|string
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function getIntroText( IContextSource $context ) {
		$introText = $this->getCustomMentorIntroText( $context );
		if ( $introText === '' ) {
			$introText = $this->getDefaultMentorIntroText( $context );
		}
		return $introText;
	}

	/**
	 * @param IContextSource $context
	 * @return string
	 * @throws ConfigException
	 * @throws MWException
	 */
	private function getCustomMentorIntroText( IContextSource $context ) {
		// Use \h (horizontal whitespace) instead of \s (whitespace) to avoid matching newlines (T227535)
		preg_match(
			sprintf( '/:%s]]\h*\|\h*(.*)/', preg_quote( $this->getMentorUser()->getName(), '/' ) ),
			$this->getMentorsPageContent( $context ),
			$matches
		);
		$introText = $matches[1] ?? '';
		if ( $introText === '' ) {
			return '';
		}

		return $context->msg( 'quotation-marks' )
			->rawParams( $context->getLanguage()->truncateForVisual( $introText, self::INTRO_TEXT_LENGTH ) )
			->text();
	}

	/**
	 * @param IContextSource $context
	 * @return string
	 */
	private function getDefaultMentorIntroText( IContextSource $context ) {
		return $context
			->msg( 'growthexperiments-homepage-mentorship-intro' )
			->params( $this->getMentorUser()->getName() )
			->params( $context->getUser()->getName() )
			->text();
	}

	/**
	 * @param IContextSource $context
	 * @return string
	 * @throws ConfigException
	 * @throws MWException
	 */
	private function getMentorsPageContent( IContextSource $context ) {
		$config = $context->getConfig();
		$mentorsPageName = $config->get( 'GEHomepageMentorsList' );
		$title = Title::newFromText( $mentorsPageName );
		$page = WikiPage::factory( $title );

		/** @var $content \WikitextContent */
		$content = $page->getContent();
		// @phan-suppress-next-line PhanUndeclaredMethod
		return $content->getText();
	}

}
