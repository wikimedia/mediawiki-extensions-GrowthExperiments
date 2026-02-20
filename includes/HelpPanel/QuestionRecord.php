<?php

declare( strict_types = 1 );

namespace GrowthExperiments\HelpPanel;

use JsonSerializable;

class QuestionRecord implements JsonSerializable {

	private string $questionText;
	private string $sectionHeader;
	/** @var string|int|null Either a revision id or the UUID of a Flow topic */
	private $revId;
	private string $resultUrl;
	private string $archiveUrl;
	private int $timestamp;
	private bool $isArchived;
	private bool $isVisible;
	private string $contentModel;

	/**
	 * @param string $questionText
	 * @param string $sectionHeader
	 * @param string|int|null $revId Either a revision id or the UUID of a Flow topic
	 * @param int $timestamp
	 * @param string $resultUrl
	 * @param string $contentModel
	 * @param string $archiveUrl
	 * @param bool $isArchived
	 * @param bool $isVisible
	 */
	public function __construct(
		string $questionText,
		string $sectionHeader,
		$revId,
		int $timestamp,
		string $resultUrl,
		string $contentModel,
		string $archiveUrl = '',
		bool $isArchived = false,
		bool $isVisible = true
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

	public function isArchived(): bool {
		return $this->isArchived;
	}

	public function setArchived( bool $isArchived ): void {
		$this->isArchived = $isArchived;
	}

	public function getQuestionText(): string {
		return $this->questionText;
	}

	public function getSectionHeader(): string {
		return $this->sectionHeader;
	}

	/**
	 * @return string|int|null
	 */
	public function getRevId() {
		return $this->revId;
	}

	public function getResultUrl(): string {
		return $this->resultUrl;
	}

	public function getTimestamp(): int {
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

	public static function newFromArray( array $content ): self {
		return new self(
			is_string( $content['questionText'] ?? null ) ? $content['questionText'] : '',
			is_string( $content['sectionHeader'] ?? null ) ? $content['sectionHeader'] : '',
			$content['revId'] ?? 0,
			self::ensureValidTimestamp( $content['timestamp'] ?? null ),
			is_string( $content['resultUrl'] ?? null ) ? $content['resultUrl'] : '',
			is_string( $content['contentModel'] ?? null ) ? $content['contentModel'] : CONTENT_MODEL_WIKITEXT,
			is_string( $content['archiveUrl'] ?? null ) ? $content['archiveUrl'] : '',
			self::ensureValidBoolean( $content['isArchived'] ?? null, false ),
			self::ensureValidBoolean( $content['isVisible'] ?? null, true ),
		);
	}

	/**
	 * Returns its first argument as a bool if reasonably possible, returns the provided default argument otherwise.
	 *
	 * @param mixed $value
	 * @param bool $default
	 */
	private static function ensureValidBoolean( $value, bool $default ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (bool)$value;
		}
		return $default;
	}

	/**
	 * Returns its argument as an int if reasonably possible, assuming it to be a unix timestamp.
	 * Returns the current unix timestamp otherwise.
	 *
	 * @param mixed $timestamp
	 */
	private static function ensureValidTimestamp( $timestamp ): int {
		if ( is_int( $timestamp ) ) {
			return $timestamp;
		}
		if ( is_numeric( $timestamp ) ) {
			return (int)$timestamp;
		}
		return (int)wfTimestamp();
	}

	public function getArchiveUrl(): string {
		return $this->archiveUrl;
	}

	public function setArchiveUrl( string $archiveUrl ): void {
		$this->archiveUrl = $archiveUrl;
	}

	public function setQuestionText( string $questionText ): void {
		$this->questionText = $questionText;
	}

	public function isVisible(): bool {
		return $this->isVisible;
	}

	public function setVisible( bool $isVisible ): void {
		$this->isVisible = $isVisible;
	}

	public function setTimestamp( int $timestamp ): void {
		$this->timestamp = $timestamp;
	}

	public function getContentModel(): string {
		return $this->contentModel;
	}

}
