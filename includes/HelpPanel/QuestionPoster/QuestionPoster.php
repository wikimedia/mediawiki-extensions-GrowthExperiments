<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use Flow\Container;
use GrowthExperiments\HelpPanel\QuestionRecord;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use GrowthExperiments\Hooks\HookRunner;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
use RuntimeException;
use StatusValue;
use Wikimedia\Stats\StatsFactory;

/**
 * Base class for sending messages containing user questions to some target page.
 */
abstract class QuestionPoster {

	private WikiPageFactory $wikiPageFactory;
	private TitleFactory $titleFactory;
	private PermissionManager $permissionManager;
	private bool $postOnTop = false;
	private IContextSource $context;
	private bool $isFirstEdit;
	private Title $targetTitle;
	private string $resultUrl;
	protected PageUpdater $pageUpdater;

	/**
	 * @var mixed
	 */
	private $revisionId;

	private string $relevantTitleRaw;
	private ?Title $relevantTitle = null;

	private string $postedOnTimestamp;

	/** @var QuestionRecord[] */
	private array $existingQuestionsByUser;

	private string $body;
	private string $sectionHeader;
	private StatsFactory $statsFactory;

	private bool $confirmEditInstalled = false;
	private bool $flowInstalled = false;

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		PermissionManager $permissionManager,
		StatsFactory $statsFactory,
		bool $confirmEditInstalled,
		bool $flowInstalled,
		IContextSource $context,
		string $body,
		string $relevantTitleRaw = ''
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->permissionManager = $permissionManager;
		$this->context = $context;
		$this->relevantTitleRaw = $relevantTitleRaw;
		if ( !$this->getContext()->getUser()->isNamed() ) {
			throw new UserNotLoggedIn();
		}
		$this->isFirstEdit = ( $this->getContext()->getUser()->getEditCount() === 0 );
		$this->targetTitle = $this->getTargetTitle();
		$page = $wikiPageFactory->newFromTitle( $this->targetTitle );
		$this->pageUpdater = $page->newPageUpdater( $this->getContext()->getUser() );
		$this->body = trim( $body );
		$this->statsFactory = $statsFactory;
		$this->confirmEditInstalled = $confirmEditInstalled;
		$this->flowInstalled = $flowInstalled;
	}

	/**
	 * Whether to post on top of the help desk (as opposed to the bottom). Defaults to false.
	 * Only affects wikitext pages.
	 */
	public function setPostOnTop( bool $postOnTop ): void {
		$this->postOnTop = $postOnTop;
	}

	/**
	 * Return relevant title, if it exists.
	 * @return Title|null
	 */
	public function getRelevantTitle(): ?Title {
		if ( !$this->relevantTitleRaw ) {
			return null;
		}

		if ( $this->relevantTitle === null ) {
			$this->relevantTitle = $this->titleFactory->newFromText(
				$this->relevantTitleRaw
			);
		}
		return $this->relevantTitle;
	}

	/**
	 * Return wikitext link target suitable for usage in [[internal linking]]
	 *
	 * This returns [[$title->getPrefixedText()]] for most pages, and
	 * [[:$title->getPrefixedText()]] for files and categories.
	 */
	public function getWikitextLinkTarget(): string {
		$title = $this->getRelevantTitle();
		if ( !$title ) {
			return '';
		}

		if ( in_array( $title->getNamespace(), [ NS_FILE, NS_CATEGORY ], true ) ) {
			return ':' . $title->getPrefixedText();
		} else {
			return $title->getPrefixedText();
		}
	}

	/**
	 * Load the current user's existing questions.
	 */
	protected function loadExistingQuestions() {
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			$this->getQuestionStoragePref()
		);
		$this->existingQuestionsByUser = $questionStore->loadQuestions();
	}

	public function submit(): StatusValue {
		$this->loadExistingQuestions();

		// Do not let captcha to stop us
		if ( $this->confirmEditInstalled ) {
			$scope = $this->permissionManager->addTemporaryUserRights(
				$this->getContext()->getUser(),
				'skipcaptcha'
			);
		}

		$this->postedOnTimestamp = wfTimestamp();
		$this->setSectionHeader();

		$contentModel = $this->getTargetContentModel();
		if ( $contentModel === CONTENT_MODEL_WIKITEXT ) {
			$status = $this->submitWikitext();
		} elseif ( $this->flowInstalled && $contentModel === CONTENT_MODEL_FLOW_BOARD ) {
			$status = $this->submitStructuredDiscussions();
		} else {
			throw new RuntimeException( "Content model $contentModel is not supported." );
		}

		if ( $status->isGood() ) {
			$this->saveNewQuestion();
		}

		return $status;
	}

	/**
	 * @return string Content model of the target page. One of the CONTENT_MODEL_* constants.
	 */
	protected function getTargetContentModel(): string {
		return $this->targetTitle->getContentModel();
	}

	private function submitWikitext(): StatusValue {
		$content = $this->makeWikitextContent();

		$contentStatus = $this->checkContent( $content );
		if ( !$contentStatus->isGood() ) {
			return $contentStatus;
		}
		$permissionStatus = $this->checkPermissions( $content );
		if ( !$permissionStatus->isGood() ) {
			return $permissionStatus;
		}

		$tag = $this->getTag();
		$this->getPageUpdater()->addTag( $tag );
		$this->getPageUpdater()->setContent( SlotRecord::MAIN, $content );
		if ( $this->getContext()->getAuthority()->authorizeWrite( 'autopatrol', $this->targetTitle ) ) {
			$this->getPageUpdater()->setRcPatrolStatus( RecentChange::PRC_AUTOPATROLLED );
		}
		$newRev = $this->getPageUpdater()->saveRevision(
			CommentStoreComment::newUnsavedComment(
				$this->getContext()
				->msg( 'newsectionsummary' )
				->params(
					MediaWikiServices::getInstance()
					->getParserFactory()
					->create()
					->stripSectionName( $this->getSectionHeader() )
				)
				->text()
			)
		);
		if ( !$this->getPageUpdater()->getStatus()->isGood() ) {
			return $this->getPageUpdater()->getStatus();
		}

		$this->revisionId = $newRev->getId();
		$fragment = MediaWikiServices::getInstance()
				->getParser()
				->guessSectionNameFromWikiText( $this->getSectionHeader() );

		// NOTE: Don't call setFragment() on the original Title, that may corrupt the internal
		//       cache of Title objects.
		$target = Title::makeTitle(
			$this->targetTitle->getNamespace(),
			$this->targetTitle->getDBkey(),
			$fragment
		);
		$this->setResultUrl( $target->getLinkURL() );
		$tagId = str_replace( ' ', '_', $tag );
		$wiki = WikiMap::getCurrentWikiId();
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'question_poster_edits_total' )
			->setLabel( 'tag', $tagId )
			->setLabel( 'wiki', $wiki )
			->increment();

		return StatusValue::newGood();
	}

	private function submitStructuredDiscussions(): StatusValue {
		$workflowLoaderFactory = Container::get( 'factory.loader.workflow' );
		// TODO: Add statsd instrumentation after T297709 is done.
		$loader = $workflowLoaderFactory->createWorkflowLoader( $this->targetTitle );
		$blocksToCommit = $loader->handleSubmit(
			$this->getContext(),
			'new-topic',
			[
				'topiclist' => [
					'topic' => $this->getSectionHeader(),
					'content' => $this->getBody(),
					'format' => 'wikitext',
				],
			]
		);

		$status = StatusValue::newGood();
		foreach ( $loader->getBlocks() as $block ) {
			if ( $block->hasErrors() ) {
				$errors = $block->getErrors();
				foreach ( $errors as $errorKey ) {
					$status->fatal( $block->getErrorMessage( $errorKey ) );
				}
			}
		}
		if ( !$status->isOK() ) {
			return $status;
		}

		$commitMetadata = $loader->commit( $blocksToCommit );

		$topicTitle = Title::newFromText( $commitMetadata['topiclist']['topic-page'] );
		$this->setResultUrl( $topicTitle->getLinkURL() );
		$this->revisionId = $commitMetadata['topiclist']['topic-id']->getAlphadecimal();

		return StatusValue::newGood();
	}

	private function getNumberedSectionHeaderIfDuplicatesExist( string $sectionHeader ): string {
		$sectionHeaders = array_map(
			static fn ( QuestionRecord $record ) => $record->getSectionHeader(),
			$this->existingQuestionsByUser
		);
		$counter = 1;
		while ( in_array( $counter === 1 ? $sectionHeader : "$sectionHeader ($counter)",
			$sectionHeaders ) ) {
			$counter++;
		}
		return $counter === 1 ? $sectionHeader : $sectionHeader . ' (' . $counter . ')';
	}

	/**
	 * @param Content $content
	 * @return StatusValue
	 */
	protected function checkPermissions( Content $content ): StatusValue {
		$userPermissionStatus = $this->checkUserPermissions();
		if ( !$userPermissionStatus->isGood() ) {
			return $userPermissionStatus;
		}
		$editFilterMergedContentHookStatus = $this->runEditFilterMergedContentHook(
			$content,
			$this->getSectionHeaderTemplate()
		);
		if ( !$editFilterMergedContentHookStatus->isGood() ) {
			return $editFilterMergedContentHookStatus;
		}
		return StatusValue::newGood();
	}

	/**
	 * The tag to add to the edit.
	 */
	abstract protected function getTag();

	/**
	 * Create a Content object with the header and question text provided by the user.
	 *
	 * @return Content|null
	 */
	protected function makeWikitextContent() {
		$wikitextContent = new WikitextContent(
			$this->prependContent( $this->addSignature( $this->getBody() ) )
		);
		$header = $this->getSectionHeader();
		$parent = $this->getPageUpdater()->grabParentRevision();
		if ( !$parent ) {
			return $wikitextContent->addSectionHeader( $header );
		}
		$existingContent = $parent->getContent( SlotRecord::MAIN );
		if ( !$existingContent ) {
			return null;
		}

		if ( $this->postOnTop ) {
			$section1 = $existingContent->getSection( 1 );
			if ( $section1 ) {
				// Prepend to section 1 to post on top without disturbing top-of-the-page templates
				return $existingContent->replaceSection( 1,
					$wikitextContent->replaceSection( 'new', $section1 )->addSectionHeader( $header ) );
			}
			// No sections on the page - just post on bottom.
		}
		return $existingContent->replaceSection(
			'new',
			$wikitextContent,
			$header
		);
	}

	/**
	 * @return PageUpdater
	 */
	protected function getPageUpdater() {
		return $this->pageUpdater;
	}

	/**
	 * Add signature unless already set.
	 */
	private function addSignature( string $body ): string {
		return str_contains( $body, '~~~~' ) ? $body : $body . ' --~~~~';
	}

	public function validateRelevantTitle(): StatusValue {
		$title = $this->getRelevantTitle();
		return $title && $title->isValid() ?
			StatusValue::newGood() :
			StatusValue::newFatal( 'growthexperiments-help-panel-questionposter-invalid-title' );
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
	public function getRevisionId() {
		return $this->revisionId;
	}

	/**
	 * @return bool
	 */
	public function isFirstEdit() {
		return $this->isFirstEdit;
	}

	/**
	 * Get the section header template for the question posted by the user.
	 *
	 * This method is used for generating the comment summary as well as the
	 * section header in the edit.
	 *
	 * @return string
	 */
	abstract protected function getSectionHeaderTemplate();

	/**
	 * Set the result URL to go directly to the newly created question.
	 *
	 * @param string $resultUrl
	 */
	private function setResultUrl( $resultUrl ) {
		$this->resultUrl = $resultUrl;
	}

	/**
	 * Set the section header with a timestamp (wikitext only) and number.
	 *
	 * THe number is appended for flow posts. For wikitext posts, a number is appended
	 * only if duplicate headers exist, which can happen when questions
	 * are posted within the same minute.
	 */
	protected function setSectionHeader() {
		$this->sectionHeader = $this->getSectionHeaderTemplate();
		// If wikitext, override the section header to include the timestamp.
		if ( $this->getTargetContentModel() === CONTENT_MODEL_WIKITEXT ) {
			$this->sectionHeader .= ' ' . $this->getContext()
					->msg( 'parentheses' )
					->plaintextParams( $this->getFormattedPostedOnTimestamp() )
					->inContentLanguage()
					->escaped();
		}
		$this->sectionHeader = $this->getNumberedSectionHeaderIfDuplicatesExist(
			$this->sectionHeader
		);
	}

	/**
	 * @return string
	 */
	private function getSectionHeader() {
		return $this->sectionHeader;
	}

	/**
	 * @return string
	 */
	private function getPostedOnTimestamp() {
		return $this->postedOnTimestamp;
	}

	/**
	 * Timezone adjustment, site default format, and site default time zone are used for formatting.
	 * @return string
	 */
	private function getFormattedPostedOnTimestamp() {
		return MediaWikiServices::getInstance()->getContentLanguage()
			->timeanddate( $this->getPostedOnTimestamp(), true, false, '' );
	}

	/**
	 * @return Title The page where the question should be posted.
	 */
	protected function getTargetTitle(): Title {
		$title = $this->getDirectTargetTitle();
		if ( $title->isRedirect() ) {
			$page = $this->wikiPageFactory->newFromTitle( $title );
			return $page->getRedirectTarget();
		}
		return $title;
	}

	/**
	 * @return Title The page where the question should be posted (barring redirects).
	 */
	abstract protected function getDirectTargetTitle();

	/**
	 * @return IContextSource
	 */
	final protected function getContext() {
		return $this->context;
	}

	/**
	 * The preference name where the posted question will be stored.
	 *
	 * @return string
	 */
	abstract protected function getQuestionStoragePref();

	/**
	 * @return StatusValue
	 * @throws \Exception
	 */
	protected function checkUserPermissions(): StatusValue {
		return $this->permissionManager->getPermissionStatus(
			'edit',
			$this->getContext()->getUser(),
			$this->targetTitle
		);
	}

	protected function runEditFilterMergedContentHook( Content $content, string $summary ): Status {
		$derivativeContext = new DerivativeContext( $this->getContext() );
		$services = MediaWikiServices::getInstance();
		$derivativeContext->setConfig( $services->getMainConfig() );
		$derivativeContext->setTitle( $this->targetTitle );
		$status = new Status();
		$hookRunner = new HookRunner( $services->getHookContainer() );
		if ( !$hookRunner->onEditFilterMergedContent(
			$derivativeContext,
			$content,
			$status,
			$summary,
			$derivativeContext->getUser(),
			false
		) ) {
			if ( $status->isGood() ) {
				$status->fatal( 'hookaborted' );
			}
			return $status;
		}
		return $status;
	}

	private function getBody(): string {
		return $this->body;
	}

	/**
	 * Validate that $content is an instance of Content
	 */
	protected function checkContent( ?Content $content ): StatusValue {
		return $content instanceof Content ?
			StatusValue::newGood() :
			StatusValue::newFatal(
				'apierror-missingcontent-revid',
				$this->getPageUpdater()->grabParentRevision()->getId()
			);
	}

	private function saveNewQuestion() {
		$question = new QuestionRecord(
			$this->getBody(),
			$this->getSectionHeader(),
			$this->revisionId,
			(int)$this->getPostedOnTimestamp(),
			$this->getResultUrl(),
			$this->getTargetContentModel()
		);
		QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			$this->getQuestionStoragePref()
		)->add( $question );
	}

	/**
	 * Prepend wikitext to the question message body
	 */
	protected function prependContent( string $body ): string {
		return $body;
	}

}
