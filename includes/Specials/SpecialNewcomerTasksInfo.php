<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\NewcomerTasks\NewcomerTasksInfo;
use GrowthExperiments\Util;
use MediaWiki\SpecialPage\SpecialPage;
use OOUI\Tag;

class SpecialNewcomerTasksInfo extends SpecialPage {

	private NewcomerTasksInfo $cachedSuggestionsInfo;

	public function __construct( NewcomerTasksInfo $cachedSuggestionsInfo ) {
		parent::__construct( 'NewcomerTasksInfo' );
		$this->cachedSuggestionsInfo = $cachedSuggestionsInfo;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		$this->addHelpLink( 'mw:Growth/Personalized_first_day/Newcomer_tasks' );
		$out = $this->getOutput();
		if ( !Util::isNewcomerTasksAvailable() ) {
			$out->addWikiMsg( 'newcomertasksinfo-not-available' );
			return;
		}
		$info = $this->cachedSuggestionsInfo->getInfo();
		$out->addWikiMsg( 'newcomertasksinfo-config-form-info' );
		if ( !isset( $info['tasks'] ) || !isset( $info[ 'topics' ] ) ) {
			$out->addHTML(
				( new Tag( 'p' ) )
					->addClasses( [ 'error' ] )
					->appendContent( $out->msg( 'newcomertasksinfo-no-data' )->text() )
			);
			return;
		}
		$taskTypeTableRows = [];
		foreach ( $info['tasks'] as $taskTypeId => $taskTypeData ) {
			$taskTypeTableRows[] = ( new Tag( 'tr' ) )->appendContent(
				( new Tag( 'td' ) )->appendContent(
					$out->msg( 'growthexperiments-homepage-suggestededits-tasktype-name-' . $taskTypeId )->text()
				),
				( new Tag( 'td' ) )->appendContent( ( new Tag( 'code' ) )->appendContent( $taskTypeId ) ),
				( new Tag( 'td' ) )->appendContent(
					$out->msg( 'newcomertasksinfo-task-count' )
						->numParams( $taskTypeData['totalCount'] )
						->text() )
			);
		}
		$taskTypeTableRows[] = ( new Tag( 'tr' ) )->appendContent(
			( new Tag( 'td' ) ),
			( new Tag( 'td' ) ),
			( new Tag( 'td' ) )->appendContent(
				$out->msg( 'newcomertasksinfo-task-count' )
					->numParams( $info['totalCount'] )
					->text()
			)
		);
		$taskTypeTable = ( new Tag( 'table' ) )->addClasses( [ 'wikitable' ] )
			->appendContent( ( new Tag( 'thead' ) )->appendContent(
				( new Tag( 'th' ) )->appendContent( $out->msg( 'newcomertasksinfo-table-header-task-type' )->text() ),
				( new Tag( 'th' ) )->appendContent(
					$out->msg( 'newcomertasksinfo-table-header-task-type-id' )->text()
				),
				( new Tag( 'th' ) )->appendContent(
					$out->msg( 'newcomertasksinfo-table-header-task-count' )->text()
				)
			) )
			->appendContent( ( new Tag( 'tbody' ) )->appendContent( $taskTypeTableRows ) );

		$out->addHTML( $taskTypeTable );

		$topicsTableHeaders = [
			( new Tag( 'th' ) )->appendContent( $out->msg( 'newcomertasksinfo-table-header-topic-type' )->text() ),
		];
		foreach ( array_keys( $info['tasks'] ) as $taskTypeId ) {
			$topicsTableHeaders[] = ( new Tag( 'th' ) )->appendContent( $taskTypeId );
		}
		$topicsTableHeaders[] = ( new Tag( 'th' ) )->appendContent(
			$out->msg( 'newcomertasksinfo-table-header-task-count' )->text()
		);

		$topicsTableRows = [];
		foreach ( $info['topics'] as $topicId => $topicData ) {
			$topicsTableRowData = [
				( new Tag( 'td' ) )->appendContent( ( new Tag( 'code' ) )->appendContent( $topicId ) ),
			];
			foreach ( array_keys( $info['tasks'] ) as $taskTypeId ) {
				$topicsTableRowData[] = ( new Tag( 'td' ) )->appendContent(
					$out->msg( 'newcomertasksinfo-task-count' )
					->numParams( $topicData['tasks'][$taskTypeId] )
					->text()
				);
			}
			$topicsTableRowData[] = ( new Tag( 'td' ) )->appendContent(
				$out->msg( 'newcomertasksinfo-task-count' )
				->numParams( $topicData['totalCount'] )
				->text()
			);
			$topicsTableRows[] = ( new Tag( 'tr' ) )->appendContent(
				$topicsTableRowData
			);
		}
		$topicsTable = ( new Tag( 'table' ) )->addClasses( [ 'wikitable' ] )
			->appendContent( ( new Tag( 'thead' ) )->appendContent(
				$topicsTableHeaders
			) )
			->appendContent( ( new Tag( 'tbody' ) )->appendContent( $topicsTableRows ) );

		$out->addHTML( $topicsTable );
	}
}
