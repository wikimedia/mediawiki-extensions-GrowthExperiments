<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\Logging\LogEntry;
use MediaWiki\Logging\LogFormatter;
use MediaWiki\Title\TitleParser;

class MentorChangeLogFormatter extends LogFormatter {
	private TitleParser $titleParser;

	public function __construct(
		LogEntry $entry,
		TitleParser $titleParser
	) {
		parent::__construct( $entry );
		$this->titleParser = $titleParser;
	}

	/**
	 * @inheritDoc
	 */
	protected function extractParameters() {
		$params = parent::extractParameters();
		switch ( $this->entry->getSubtype() ) {
			case 'claimmentee':
				// logentry-growthexperiments-claimmentee
				// @phan-suppress-next-line SecurityCheck-XSS
				$params[4] = $this->formatParameterValue( 'user-link', $params[3] );
				// no break here
			case 'claimmentee-no-previous-mentor':
				// logentry-growthexperiments-claimmentee-no-previous-mentor
				$params[5] = $this->formatParameterValue( 'user', $this->entry->getTarget()->getText() );
				break;
			case 'setmentor':
				// logentry-growthexperiments-setmentor
				// @phan-suppress-next-line SecurityCheck-XSS
				$params[7] = $this->formatParameterValue( 'user-link', $params[3] );
				// no break here
			case 'setmentor-no-previous-mentor':
				// logentry-growthexperiments-setmentor-no-previous-mentor
				$params[5] = $this->formatParameterValue( 'user', $this->entry->getTarget()->getText() );
				// @phan-suppress-next-line SecurityCheck-XSS
				$params[6] = $this->formatParameterValue( 'user-link', $params[4] );
				break;
		}
		return $params;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		// remove "User:" prefix
		$params[2] = $this->formatParameterValue( 'user-link', $this->entry->getTarget()->getText() );
		return $params;
	}

	/**
	 * @inheritDoc
	 */
	public function getPreloadTitles() {
		// Add the mentee's and mentors' user pages to LinkBatch
		$params = parent::getMessageParameters();
		$links = [];
		switch ( $this->entry->getSubtype() ) {
			case 'claimmentee':
				$title = $this->titleParser->makeTitleValueSafe( NS_USER, $params[3] );
				if ( $title ) {
					$links[] = $title;
				}
				break;
			case 'setmentor':
				$title = $this->titleParser->makeTitleValueSafe( NS_USER, $params[3] );
				if ( $title ) {
					$links[] = $title;
				}
				// no break here
			case 'setmentor-no-previous-mentor':
				$title = $this->titleParser->makeTitleValueSafe( NS_USER, $params[4] );
				if ( $title ) {
					$links[] = $title;
				}
				break;
		}
		return $links;
	}

}
