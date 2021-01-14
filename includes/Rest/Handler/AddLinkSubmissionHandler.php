<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationLink;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkSubmissionRecorder;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use MalformedTitleException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use RequestContext;
use Status;
use TitleFormatter;
use TitleParser;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Record the user's decision on the recommendations for a given page.
 */
class AddLinkSubmissionHandler extends SimpleHandler {

	use AddLinkHandlerTrait;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/** @var LinkSubmissionRecorder */
	private $addLinkSubmissionRecorder;

	/** @var TitleParser */
	private $titleParser;

	/** @var TitleFormatter */
	private $titleFormatter;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param LinkRecommendationProvider $linkRecommendationProvider
	 * @param LinkSubmissionRecorder $addLinkSubmissionRecorder
	 * @param TitleParser $titleParser
	 * @param TitleFormatter $titleFormatter
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		LinkRecommendationProvider $linkRecommendationProvider,
		LinkSubmissionRecorder $addLinkSubmissionRecorder,
		TitleParser $titleParser,
		TitleFormatter $titleFormatter
	) {
		$this->configurationLoader = $configurationLoader;
		$this->linkRecommendationProvider = $linkRecommendationProvider;
		$this->titleParser = $titleParser;
		$this->titleFormatter = $titleFormatter;
		$this->addLinkSubmissionRecorder = $addLinkSubmissionRecorder;
	}

	/**
	 * Entry point.
	 * @param LinkTarget $title
	 * @return Response|mixed A Response or a scalar passed to ResponseFactory::createFromReturnValue
	 * @throws HttpException
	 */
	public function run( LinkTarget $title ) {
		$this->assertLinkRecommendationsEnabled( RequestContext::getMain() );
		$user = RequestContext::getMain()->getUser();
		if ( $user->isAnon() ) {
			throw new HttpException( 'Must be logged in', 403 );
		}
		// should we also check the user's Tracker?

		$linkRecommendation = $this->getLinkRecommendation( $title );
		$expectedRevId = $linkRecommendation->getRevisionId();
		$links = $this->normalizeTargets( $linkRecommendation->getLinks() );

		// FIXME fix JsonBodyValidator so it actually validates
		$data = $this->getValidatedBody();
		$baseRevId = (int)$data['baseRevId'];
		$editRevId = (int)$data['editRevId'] ?: null;
		$acceptedTargets = $this->normalizeTargets( $data['acceptedTargets'] ?: [] );
		$rejectedTargets = $this->normalizeTargets( $data['rejectedTargets'] ?: [] );
		$skippedTargets = $this->normalizeTargets( $data['skippedTargets'] ?: [] );

		$allTargets = array_merge( $acceptedTargets, $rejectedTargets, $skippedTargets );
		$unexpectedTargets = array_diff( $allTargets, $links );
		$missingTargets = array_diff( $links, $allTargets );
		if ( $baseRevId !== $expectedRevId ) {
			throw new HttpException( "Invalid revision ID: expected $expectedRevId, got $baseRevId" );
		} elseif ( $unexpectedTargets ) {
			throw new HttpException( 'Unexpected link targets: ' . implode( ', ', $unexpectedTargets ) );
		} elseif ( $missingTargets ) {
			throw new HttpException( 'Missing link targets: ' . implode( ', ', $missingTargets ) );
		}

		$status = $this->addLinkSubmissionRecorder->record( $user, $linkRecommendation, $acceptedTargets,
			$rejectedTargets, $skippedTargets, $editRevId );
		if ( !$status->isOK() ) {
			throw new HttpException( Status::wrap( $status )->getWikiText( null, null, 'en' ) );
		}
		$result = $status->getValue();
		return [ 'success' => true, 'logId' => $result['logId'] ];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'title' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => true,
				TitleDef::PARAM_RETURN_OBJECT => true,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyValidator( $contentType ) {
		return new JsonBodyValidator( [
			'baseRevId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'editRevId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'acceptedTargets' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ALLOW_DUPLICATES => true,
			],
			'rejectedTargets' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ALLOW_DUPLICATES => true,
			],
			'skippedTargets' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ALLOW_DUPLICATES => true,
			],
		] );
	}

	/**
	 * Normalize link targets into prefixed dbkey format
	 * @param array<int,string|LinkTarget|LinkRecommendationLink> $targets
	 * @return string[]
	 * @throws HttpException
	 */
	private function normalizeTargets( array $targets ): array {
		$normalized = [];
		foreach ( $targets as $target ) {
			if ( $target instanceof LinkRecommendationLink ) {
				$target = $target->getLinkTarget();
			}
			if ( !$target instanceof LinkTarget ) {
				try {
					$target = $this->titleParser->parseTitle( $target );
				} catch ( MalformedTitleException $e ) {
					throw new HttpException( 'Could not parse title: ' . $target );
				}
			}
			$normalized[] = $this->titleFormatter->getPrefixedDBkey( $target );
		}
		return $normalized;
	}

}
