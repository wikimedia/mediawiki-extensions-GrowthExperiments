<?php

namespace GrowthExperiments\Rest\Handler;

use ApiBase;
use DerivativeContext;
use GrowthExperiments\HelpPanel\Tips\TipsBuilder;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use RequestContext;

class TipsHandler extends SimpleHandler {

	/**
	 * @var TipsBuilder
	 */
	private $tipsBuilder;

	/**
	 * @param TipsBuilder $tipsBuilder
	 */
	public function __construct( TipsBuilder $tipsBuilder ) {
		$this->tipsBuilder = $tipsBuilder;
	}

	/**
	 * @param string $skin
	 * @param string $editor
	 * @param string $tasktypeid
	 * @param string $uselang
	 * @return Response
	 * @throws \MWException
	 */
	public function run( string $skin, string $editor, string $tasktypeid, string $uselang
	) {
		$context = new DerivativeContext( RequestContext::getMain() );
		if ( $uselang ) {
			$context->setLanguage( $uselang );
		}
		$this->tipsBuilder->setMessageLocalizerAndInitParameterMapper( $context, $skin );
		$this->tipsBuilder->getConfigurationLoader()
			->setMessageLocalizer( $context );
		return $this->getResponseFactory()->createJson(
			$this->tipsBuilder->getTips( $skin, $editor, $tasktypeid, $context->getLanguage()->getDir() )
		);
	}

	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'skin' => [
				self::PARAM_SOURCE => 'path',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string',
			],
			'editor' => [
				self::PARAM_SOURCE => 'path',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string',
			],
			'tasktypeid' => [
				self::PARAM_SOURCE => 'path',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array_keys(
					$this->tipsBuilder->getConfigurationLoader()->getTaskTypes()
				)
			],
			'uselang' => [
				self::PARAM_SOURCE => 'path',
				ApiBase::PARAM_REQUIRED => true,
			]
		];
	}
}
