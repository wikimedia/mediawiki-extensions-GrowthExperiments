<?php

namespace GrowthExperiments\Mentorship;

use LogFormatter;
use Title;

class MentorChangeLogFormatter extends LogFormatter {

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
				$links[] = Title::makeTitle( NS_USER, $params[3] );
				break;
			case 'setmentor':
				$links[] = Title::makeTitle( NS_USER, $params[3] );
				// no break here
			case 'setmentor-no-previous-mentor':
				$links[] = Title::makeTitle( NS_USER, $params[4] );
				break;
		}
		return $links;
	}

}
