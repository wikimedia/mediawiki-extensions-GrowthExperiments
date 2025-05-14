<?php

namespace GrowthExperiments\Config\Validation;

use Iterator;
use LogicException;
use MediaWiki\Extension\CommunityConfiguration\Schema\SchemaBuilder;
use MediaWiki\Extension\CommunityConfiguration\Validation\IValidator;
use MediaWiki\Extension\CommunityConfiguration\Validation\ValidationStatus;

/**
 * Ensure StructuredMentorListValidator is used with CommunityConfiguration
 *
 * @todo Migrate the provider to JSON schema and remove
 */
class CommunityStructuredMentorListValidator implements IValidator {

	private StructuredMentorListValidator $validator;

	public function __construct() {
		$this->validator = new StructuredMentorListValidator();
	}

	/**
	 * @param mixed $config
	 * @return ValidationStatus
	 */
	private function validate( $config ): ValidationStatus {
		// HACK: Convert into arrays
		$config = json_decode( json_encode( $config ), true );
		$resp = new ValidationStatus();
		$resp->merge( $this->validator->validate( $config ) );
		return $resp;
	}

	/** @inheritDoc */
	public function validateStrictly( $config, ?string $version = null ): ValidationStatus {
		return $this->validate( $config );
	}

	/** @inheritDoc */
	public function validatePermissively( $config, ?string $version = null ): ValidationStatus {
		return $this->validate( $config );
	}

	/** @inheritDoc */
	public function areSchemasSupported(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 * @return never
	 */
	public function getSchemaBuilder(): SchemaBuilder {
		throw new LogicException( __METHOD__ . ' is not supported' );
	}

	/**
	 * @inheritDoc
	 * @return never
	 */
	public function getSchemaVersion(): ?string {
		throw new LogicException( __METHOD__ . ' is not supported' );
	}

	/**
	 * @inheritDoc
	 * @return never
	 */
	public function getSchemaIterator(): Iterator {
		throw new LogicException( __METHOD__ . ' is not supported' );
	}
}
