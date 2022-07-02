<?php

namespace GrowthExperiments\HelpPanel;

use JsonSerializable;

class QuestionRecord implements JsonSerializable {
	private $questionText;
	private $sectionHeader;
	private $revId;
	private $resultUrl;
	private $archiveUrl;
	private $timestamp;
	private $isArchived;
	private $isVisible;
	private $contentModel;

	/**
	 * @param string $questionText
	 * @param string $sectionHeader
	 * @param mixed $revId
	 * @param int $timestamp
	 * @param string $resultUrl
	 * @param string $contentModel
	 * @param string $archiveUrl
	 * @param bool $isArchived
	 * @param bool $isVisible
	 */
	public function __construct(
		$questionText,
		$sectionHeader,
		$revId,
		$timestamp,
		$resultUrl,
		$contentModel,
		$archiveUrl = '',
		$isArchived = false,
		$isVisible = true
	) {
		$this->questionText = $questionText;
		$this->sectionHeader = $sectionHeader;
		$this->revId = $revId;
		$this->resultUrl = $resultUrl;
		$this->contentModel = $contentModel;
		$this->timestamp = $timestamp;
		$this->isArchived = $isArchived;
		$this->isVisible = $isVisible;
		$this->archiveUrl = $archiveUrl;
	}

	/**
	 * @return bool
	 */
	public function isArchived() {
		return $this->isArchived;
	}

	/**
	 * @param bool $isArchived
	 */
	public function setArchived( $isArchived ) {
		$this->isArchived = $isArchived;
	}

	/**
	 * @return string
	 */
	public function getQuestionText() {
		return $this->questionText;
	}

	/**
	 * @return string
	 */
	public function getSectionHeader() {
		return $this->sectionHeader;
	}

	/**
	 * @return mixed
	 */
	public function getRevId() {
		return $this->revId;
	}

	/**
	 * @return string
	 */
	public function getResultUrl() {
		return $this->resultUrl;
	}

	/**
	 * @return int
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}

	public function jsonSerialize(): array {
		return [
			'questionText' => $this->getQuestionText(),
			'sectionHeader' => $this->getSectionHeader(),
			'revId' => $this->getRevId(),
			'resultUrl' => $this->getResultUrl(),
			'contentModel' => $this->getContentModel(),
			'archiveUrl' => $this->getArchiveUrl(),
			'timestamp' => $this->getTimestamp(),
			'isArchived' => $this->isArchived(),
			'isVisible' => $this->isVisible(),
		];
	}

	/**
	 * @param array $content
	 * @return QuestionRecord
	 */
	public static function newFromArray( array $content ) {
		return new self(
			$content['questionText'] ?? '',
			$content['sectionHeader'] ?? '',
			$content['revId'] ?? 0,
			$content['timestamp'] ?? wfTimestamp(),
			$content['resultUrl'] ?? '',
			$content['contentModel'] ?? CONTENT_MODEL_WIKITEXT,
			$content['archiveUrl'] ?? '',
			$content['isArchived'] ?? false,
			$content['isVisible'] ?? true
		);
	}

	/**
	 * @return string
	 */
	public function getArchiveUrl() {
		return $this->archiveUrl;
	}

	/**
	 * @param string $archiveUrl
	 */
	public function setArchiveUrl( $archiveUrl ) {
		$this->archiveUrl = $archiveUrl;
	}

	/**
	 * @param string $questionText
	 */
	public function setQuestionText( $questionText ) {
		$this->questionText = $questionText;
	}

	/**
	 * @return bool
	 */
	public function isVisible() {
		return $this->isVisible;
	}

	/**
	 * @param bool $isVisible
	 */
	public function setVisible( $isVisible ) {
		$this->isVisible = $isVisible;
	}

	/**
	 * @param int $timestamp
	 */
	public function setTimestamp( $timestamp ) {
		$this->timestamp = $timestamp;
	}

	/**
	 * @return string
	 */
	public function getContentModel() {
		return $this->contentModel;
	}

}
