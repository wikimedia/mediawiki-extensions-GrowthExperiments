<?php

namespace GrowthExperiments\HelpPanel\Tips;

use Config;
use GrowthExperiments\HelpPanel\Tips\Renderer\TipRendererFactory;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use IContextSource;
use Message;
use MessageLocalizer;
use OutputPage;

/**
 * Builder for quick start tips.
 *
 * The quick start tips are composed of:
 *   * TipSets, which are numerically indexed, and are composed of Tips.
 *   * Tips have different render functions depending on their type, and can be
 *     passed parameters for rendering - usually message parameters but in the
 *     case of graphical tips, the parameters are the image source of the image.
 *
 * The tips are returned as an array of rendered HTML.
 *
 * @package GrowthExperiments\HelpPanel\Tips
 */
class TipsBuilder {

	private const TIP_TYPES = [ 'main', 'example', 'graphic', 'text' ];

	// When the requirement arises, this value could be made configurable
	// via NewcomerTasks.json, so it can vary by skin/editor/tasktype, i.e.
	// the `references` task type in visualeditor could have 4 steps while
	// the wikitext editor might have 6 steps. This would allow for completely
	// overriding the set of tip steps in en.json/qqq.json for a particular
	// variant. For now, while messages can be varied by skin/editor, the number
	// of tip steps is fixed across all variants.
	private const TIP_SET_NAMES = [
		'value', 'calm', 'rules1', 'rules2', 'step1', 'step2', 'step3', 'publish'
	];

	private const DEFAULT_EDITOR = 'visualeditor';

	private const DEFAULT_SKIN = 'vector';

	/**
	 * @var IContextSource
	 */
	private $messageLocalizer;
	/**
	 * @var Config
	 */
	private $config;
	/**
	 * @var ConfigurationLoader
	 */
	private $configurationLoader;

	/**
	 * @var ParameterMapper
	 */
	private $parameterMapper;

	/**
	 * @param Config $config
	 * @param ConfigurationLoader $configurationLoader
	 */
	public function __construct( Config $config, ConfigurationLoader $configurationLoader ) {
		$this->config = $config;
		$this->configurationLoader = $configurationLoader;
	}

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param string $skin
	 */
	public function setMessageLocalizerAndInitParameterMapper(
		MessageLocalizer $messageLocalizer, string $skin
	) :void {
		$this->messageLocalizer = $messageLocalizer;
		$this->parameterMapper = new ParameterMapper( $messageLocalizer, $skin );
	}

	/**
	 * @param string $skinName
	 * @param string $editor
	 * @param string $taskTypeId
	 * @param string $dir
	 * @return array
	 */
	public function getTips(
		string $skinName, string $editor, string $taskTypeId, string $dir
	): array {
		OutputPage::setupOOUI( $skinName, $dir );
		$result = [];
		$i = 1;
		/** @var TipSet $tipSet */
		foreach ( $this->getTipSets( $skinName, $editor, $taskTypeId ) as $tipSet ) {
			$result[$i] = $tipSet->render( $this->parameterMapper );
			$i++;
		}
		return $result;
	}

	/**
	 * @param string $skinName
	 * @param string $editor
	 * @param string $taskTypeId
	 * @return array
	 */
	private function getTipSets( string $skinName, string $editor, string $taskTypeId ) :array {
		$tipSets = [];
		foreach ( self::TIP_SET_NAMES as $stepName ) {
			$tips = array_filter( array_map( function ( $tipTypeId ) use ( $skinName, $editor,
				$taskTypeId, $stepName ) {
				$message = $this->getMessageWithFallback( $skinName, $editor, $taskTypeId,
					$tipTypeId, $stepName );
				return !$message->isDisabled() ?
					$this->getTip( $message, $tipTypeId, $taskTypeId, $skinName ) :
					[];
			}, self::TIP_TYPES ) );
			if ( count( $tips ) ) {
				$tipSets[] = new TipSet( $stepName, $tips );
			}
		}
		return $tipSets;
	}

	/**
	 * Get a Message for a particular skin, editor, task type, tip type, and step.
	 *
	 * If the message doesn't exist, check variants in the following order:
	 * - Default editor with requested skin
	 * - Default skin with requested editor
	 * - Default skin and default editor
	 *
	 * @param string $skinName
	 * @param string $editor
	 * @param string $taskTypeId
	 * @param string $tipTypeId
	 * @param string $step
	 * @return Message
	 */
	private function getMessageWithFallback(
		string $skinName,
		string $editor,
		string $taskTypeId,
		string $tipTypeId,
		string $step
	) :Message {
		$msg = $this->messageLocalizer->msg( $this->buildMessageKey(
			$skinName, $editor, $taskTypeId, $tipTypeId, $step )
		);
		if ( !$msg->exists() ) {
			$msg = $this->messageLocalizer->msg(
				$this->buildMessageKey( $skinName, self::DEFAULT_EDITOR, $taskTypeId, $tipTypeId, $step )
			);
		}
		if ( !$msg->exists() ) {
			$msg = $this->messageLocalizer->msg(
				$this->buildMessageKey( self::DEFAULT_SKIN, $editor, $taskTypeId, $tipTypeId, $step )
			);
		}
		if ( !$msg->exists() ) {
			$msg = $this->messageLocalizer->msg(
				$this->buildMessageKey(
					self::DEFAULT_SKIN,
					self::DEFAULT_EDITOR,
					$taskTypeId,
					$tipTypeId,
					$step
				)
			);
		}
		return $msg;
	}

	/**
	 * @return ConfigurationLoader
	 */
	public function getConfigurationLoader() :ConfigurationLoader {
		return $this->configurationLoader;
	}

	/**
	 * @param string $skinName
	 * @param string $editor
	 * @param string $taskTypeId
	 * @param string $tipTypeId
	 * @param string $step
	 * @return string
	 */
	private function buildMessageKey(
		string $skinName, string $editor, string $taskTypeId, string $tipTypeId, string $step
	) :string {
		return sprintf(
			'%s-%s-%s-%s-%s-%s',
			'growthexperiments-help-panel-suggestededits-tips',
			$skinName,
			$editor,
			$taskTypeId,
			$tipTypeId,
			$step
		);
	}

	/**
	 * @param Message $msg
	 * @param string $tipTypeId
	 * @param string $taskTypeId
	 * @param string $skin
	 * @return TipInterface
	 */
	private function getTip(
		Message $msg, string $tipTypeId, string $taskTypeId, string $skin
	): TipInterface {
		$tipConfig = $this->buildTipConfig( $msg, $tipTypeId, $taskTypeId, $skin );
		return Tip::factory(
			$tipConfig,
			TipRendererFactory::newFromTipConfigAndContext( $tipConfig, $this->messageLocalizer )
		);
	}

	/**
	 * @param Message $msg
	 * @param string $tipTypeId
	 * @param string $taskTypeId
	 * @param string $skin
	 * @return TipConfig
	 */
	private function buildTipConfig(
		Message $msg, string $tipTypeId, string $taskTypeId, string $skin
	) :TipConfig {
		$taskType = $this->getConfigurationLoader()->getTaskTypes()[$taskTypeId] ?? null;
		return new TipConfig(
			$tipTypeId,
			$msg->getKey(),
			$taskType ? $taskType->getLearnMoreLink() ?? '' : '',
			$skin,
			$taskTypeId,
			[ 'ExtensionAssetsPath' =>
				$this->config->get( 'ExtensionAssetsPath' ) ]
		);
	}

}
