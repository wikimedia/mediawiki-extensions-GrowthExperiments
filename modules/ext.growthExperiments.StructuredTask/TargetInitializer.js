const MachineSuggestionsMode = require( './MachineSuggestionsMode.js' ),
	EditModeMachineSuggestions = require( './EditModeMachineSuggestions.js' ),
	EditModeVisualWithSuggestions = require( './EditModeVisualWithSuggestions.js' ),
	EditModeConfirmationDialog = require( './EditModeConfirmationDialog.js' ),
	StructuredTaskMessageDialog = require( './StructuredTaskMessageDialog.js' ),
	allowedTools = [
		'machineSuggestionsSave',
		'machineSuggestionsPlaceholder',
		'showSave',
		'showMobileSave',
		'editMode',
		'back'
	],
	allowedCommands = [ 'showSave', 'showChanges', 'back', 'cancel' ];

/**
 * Handle registrations and de-registrations of VE classes for structured tasks
 *
 * @class mw.libs.ge.TargetInitializer
 *
 * @constructor
 *
 * @param {Object} config
 * @param {ve.init.mw.ArticleTarget} config.taskArticleTarget ArticleTarget for suggestions mode
 * @param {ve.init.mw.ArticleTarget} config.suggestionsArticleTarget ArticleTarget for regular mode
 * @param {string[]} [config.safeTools] Names of tools to keep
 * @param {string[]} [config.safeCommands] Names of commands to keep
 * @param {ve.dm.Model[]} [config.dataModels] Data models for the task type
 * @param {ve.ce.Annotation[]} [config.annotationViews] Annotation views for the task type
 * @param {OO.ui.Window[]} [config.windows] Windows that can be invoked during the editing flow
 * @param {ve.ui.Command[]} [config.commands] Commands that can be invoked during the editing flow
 * @param {ve.ui.Tool[]} [config.tools] Tools for the task type
 * @param {ve.ce.Node[]} [config.nodes] Nodes for the task type
 * @param {ve.ce.KeyDownHandler} [config.keyDownHandlers] Keydown handlers for the task type
 * @param {boolean} [config.shouldOverrideBackTool] Whether MachineSuggestionsBack tool should be used
 */
function TargetInitializer( config ) {
	const safeTools = config.safeTools || [],
		safeCommands = config.safeCommands || [],
		customWindows = config.windows || [];
	this.taskArticleTarget = config.taskArticleTarget;
	this.suggestionsArticleTarget = config.suggestionsArticleTarget;
	this.safeTools = allowedTools.concat(
		MachineSuggestionsMode.getEditModeToolNames(), safeTools
	);
	this.safeCommands = allowedCommands.concat( safeCommands );
	this.dataModels = config.dataModels || [];
	this.annotationViews = config.annotationViews || [];
	this.windows = [
		EditModeConfirmationDialog,
		StructuredTaskMessageDialog
	].concat( customWindows );
	this.commands = config.commands || [];
	this.tools = config.tools || [];
	this.nodes = config.nodes || [];
	this.keyDownHandlers = config.keyDownHandlers || [];
	this.shouldOverrideBackTool = config.shouldOverrideBackTool;
}

/**
 * Register VE classes needed for structured task editing flow and de-register those that
 * should not be shown during the flow
 */
TargetInitializer.prototype.initialize = function () {
	this.registerEditModeToggle();

	if ( this.shouldShowRegularVeMode() ) {
		this.registerSuggestionsArticleTarget();
		return;
	}

	this.registerDataModels();
	this.registerAnnotationViews();
	this.registerWindows();
	this.registerCommands();
	this.registerTools();
	this.registerNodes();
	this.registerKeyDownHandlers();
	if ( this.shouldOverrideBackTool ) {
		this.overrideBackTool();
	}

	this.disableContextItems();
	this.disableTools( this.getToolsToDisable() );
	this.disableCommands();
	this.disableLinkHighlights();

	this.registerTaskArticleTarget();
};

/**
 * Register custom edit mode tools allowing users to switch to and from machine suggestions and
 * regular VE modes
 */
TargetInitializer.prototype.registerEditModeToggle = function () {
	ve.ui.toolFactory.register( EditModeMachineSuggestions );
	ve.ui.toolFactory.register( EditModeVisualWithSuggestions );
};

/**
 * Whether regular VE mode should be shown during the structured task editing flow
 *
 * @return {boolean}
 */
TargetInitializer.prototype.shouldShowRegularVeMode = function () {
	return new URL( window.location.href ).searchParams.has( 'hideMachineSuggestions' );
};

/**
 * Register the article target for regular VE mode for the structured task
 *
 * This ArticleTarget is the same as regular VE with the exception that the edit mode tools
 * only show machine suggestions and regular VE mode.
 */
TargetInitializer.prototype.registerSuggestionsArticleTarget = function () {
	ve.init.mw.targetFactory.register( this.suggestionsArticleTarget );
};

/**
 * Register the article target for machine suggestions mode for the structured task
 */
TargetInitializer.prototype.registerTaskArticleTarget = function () {
	ve.init.mw.targetFactory.register( this.taskArticleTarget );
};

/**
 * Register data models specific to the structured task
 */
TargetInitializer.prototype.registerDataModels = function () {
	this.dataModels.forEach( ( dataModel ) => {
		ve.dm.modelRegistry.register( dataModel );
	} );
};

/**
 * Register annotation views specific to the structured task
 */
TargetInitializer.prototype.registerAnnotationViews = function () {
	this.annotationViews.forEach( ( annotationView ) => {
		ve.ce.annotationFactory.register( annotationView );
	} );
};

/**
 * Register windows specific to the structured task
 */
TargetInitializer.prototype.registerWindows = function () {
	this.windows.forEach( ( window ) => {
		ve.ui.windowFactory.register( window );
	} );
};

/**
 * Register commands specific to the structured task
 */
TargetInitializer.prototype.registerCommands = function () {
	this.commands.forEach( ( command ) => {
		ve.ui.commandRegistry.register( command );
	} );
};

/**
 * Register tools specific to the structured task
 */
TargetInitializer.prototype.registerTools = function () {
	this.tools.forEach( ( tool ) => {
		ve.ui.toolFactory.register( tool );
	} );
};

/**
 * Override default 'back' tool
 */
TargetInitializer.prototype.overrideBackTool = function () {
	ve.ui.toolFactory.register( require( './MachineSuggestionsBack.js' ) );
};

/**
 * Register nodes specific to the structured task
 */
TargetInitializer.prototype.registerNodes = function () {
	this.nodes.forEach( ( node ) => {
		ve.ce.nodeFactory.register( node );
	} );
};

/**
 * Disable all unnecessary context items
 * See T280129
 */
TargetInitializer.prototype.disableContextItems = function () {
	Object.keys( ve.ui.contextItemFactory.registry ).forEach( ( contextItem ) => {
		ve.ui.contextItemFactory.unregister( contextItem );
	} );
};

/**
 * Get the names of tools that should be disabled
 *
 * @return {string[]}
 */
TargetInitializer.prototype.getToolsToDisable = function () {
	const safeTools = this.safeTools;
	return Object.keys( ve.ui.toolFactory.registry ).filter( ( toolName ) => !safeTools.includes( toolName ) );
};

/**
 * Disable all unnecessary tools
 * See T280129
 *
 * @param {string[]} toolsToDisable Names of tools that should be disabled
 */
TargetInitializer.prototype.disableTools = function ( toolsToDisable ) {
	toolsToDisable.forEach( ( tool ) => {
		ve.ui.toolFactory.unregister( tool );
	} );
};

/**
 * Disable window related commands (including their keyboard shortcuts)
 * See T281434
 */
TargetInitializer.prototype.disableCommands = function () {
	const safeCommands = this.safeCommands;
	Object.keys( ve.ui.commandRegistry.registry ).forEach( ( commandItem ) => {
		if ( !safeCommands.includes( commandItem ) ) {
			ve.ui.commandRegistry.unregister( commandItem );
		}
	} );
};

/**
 * Disable highlight for non-suggested links
 */
TargetInitializer.prototype.disableLinkHighlights = function () {
	ve.ce.MWInternalLinkAnnotation.static.canBeActive = false;
};

/**
 * If regular VE mode should be shown, disable default edit mode tools.
 *
 * This is used on mobile, when custom edit mode tools are used instead of the default.
 * On desktop, the editMode toolgroup is replaced by suggestionsEditMode toolgroup. On mobile, the
 * toolgroup can't be replaced by the subclass (see setupToolbar in ve.init.mw.MobileArticleTarget),
 * so the tools are removed instead.
 */
TargetInitializer.prototype.disableDefaultEditModeToolsForRegularVeMode = function () {
	if ( this.shouldShowRegularVeMode() ) {
		this.disableTools( [ 'editModeVisual', 'editModeSource' ] );
	}
};

/**
 * Register custom keydown handlers
 */
TargetInitializer.prototype.registerKeyDownHandlers = function () {
	this.keyDownHandlers.forEach( ( handler ) => {
		ve.ce.keyDownHandlerFactory.register( handler );
	} );
};

module.exports = TargetInitializer;
