'use strict';

QUnit.dump.maxDepth = 999;

require( './utils/Utils.test.js' );
require( './ext.growthExperiments.Homepage.Logger/index.test.js' );
require( './ext.growthExperiments.Homepage.mobile/SuggestedEditsMobileSummary.test.js' );
require( './ext.growthExperiments.Help/HelpPanelLogger.test.js' );
require( './ext.growthExperiments.Help/HelpPanelProcessDialog.test.js' );
require( './ext.growthExperiments.Help/HelpPanelProcessDialog.SwitchEditorPanel.test.js' );
require( './ext.growthExperiments.Help/AskHelpPanel.test.js' );
require( './ext.growthExperiments.Homepage.SuggestedEdits/PagerWidget.test.js' );
require( './ext.growthExperiments.Homepage.SuggestedEdits/ErrorCardWidget.test.js' );
require( './ext.growthExperiments.Homepage.SuggestedEdits/NewcomerTaskLogger.test.js' );
require( './ext.growthExperiments.Homepage.SuggestedEdits/FiltersButtonGroupWidget.test.js' );
require( './ext.growthExperiments.Homepage.SuggestedEdits/StartEditingDialog.test.js' );
require( './ext.growthExperiments.StructuredTask/StructuredTaskLogger.test.js' );
require( './ext.growthExperiments.StructuredTask/addlink/AddLinkArticleTarget.test.js' );
require( './ext.growthExperiments.StructuredTask/addimage/AddImageUtils.test.js' );
require( './ext.growthExperiments.PostEdit/PostEditPanel.test.js' );
require( './ext.growthExperiments.PostEdit/PostEditToastMessage.test.js' );
require( './ext.growthExperiments.PostEdit/PostEditDrawer.test.js' );
require( './ext.growthExperiments.DataStore/GrowthTasksApi.test.js' );
require( './ext.growthExperiments.DataStore/FiltersStore.test.js' );
require( './ext.growthExperiments.DataStore/NewcomerTasksStore.test.js' );
require( './ui-components/CollapsibleDrawer.test.js' );
require( './ui-components/AdaptiveSelectWidget.test.js' );
