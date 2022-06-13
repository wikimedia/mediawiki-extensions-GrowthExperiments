'use strict';

const SelectWithTextInputWidget = require( '../../../modules/ui-components/SelectWithTextInputWidget.js' );
const OptionWithTextInputWidget = require( '../../../modules/ui-components/OptionWithTextInputWidget.js' );

QUnit.module( 'ui-components/SelectWithTextInputWidget.js', QUnit.newMwEnvironment() );

QUnit.test( 'constructor based on isMultiSelect option ', function ( assert ) {
	const options = [ {
		data: 'option1',
		label: 'Option 1'
	}, {
		data: 'option2',
		label: 'Option 2'
	} ];
	const selectWithTextInput = new SelectWithTextInputWidget( { options } );
	const selectWithTextInputMultiSelect = new SelectWithTextInputWidget( { options, isMultiSelect: true } );
	assert.true( selectWithTextInput.widget instanceof OO.ui.RadioSelectWidget );
	assert.true( selectWithTextInputMultiSelect.widget instanceof OO.ui.CheckboxMultiselectWidget );
} );

QUnit.test( 'should return the selected options for multi-select widget', function ( assert ) {
	const options = [ {
		data: 'option1',
		label: 'Option 1'
	}, {
		data: 'option2',
		label: 'Option 2'
	}, {
		data: 'option3',
		label: 'Option 3'
	} ];
	const multiSelectWidget = new SelectWithTextInputWidget( { options, isMultiSelect: true } );
	assert.deepEqual( multiSelectWidget.findSelection(), [] );
	const selection = [ 'option1', 'option3' ];
	multiSelectWidget.updateSelection( selection );
	assert.deepEqual( multiSelectWidget.findSelection(), selection );
} );

QUnit.test( 'should return the selected option for single-select widget', function ( assert ) {
	const options = [ {
		data: 'option1',
		label: 'Option 1'
	}, {
		data: 'option2',
		label: 'Option 2'
	} ];
	const selectWidget = new SelectWithTextInputWidget( { options } );
	assert.deepEqual( selectWidget.findSelection(), [] );
	// Support both array and string for single-select widget
	selectWidget.updateSelection( 'option1' );
	assert.deepEqual( selectWidget.findSelection(), [ 'option1' ] );
	selectWidget.updateSelection( [ 'option2' ] );
	assert.deepEqual( selectWidget.findSelection(), [ 'option2' ] );
} );

QUnit.test( 'should support options with text input if hasTextInput is set for options in a multi-select widget', function ( assert ) {
	const options = [ {
		data: 'option1',
		label: 'Option 1'
	}, {
		data: 'option2',
		label: 'Option 2',
		hasTextInput: true
	}, {
		data: 'option3',
		label: 'Option 3',
		hasTextInput: true
	} ];
	const multiSelectWidget = new SelectWithTextInputWidget( { options, isMultiSelect: true } );
	const optionsWithTextInput = [ 'option2', 'option3' ];
	assert.true( typeof multiSelectWidget.optionWithTextInputWidgets.option1 === 'undefined' );
	optionsWithTextInput.forEach( function ( data ) {
		assert.true( multiSelectWidget.optionWithTextInputWidgets[ data ] instanceof OptionWithTextInputWidget );
	} );
} );

QUnit.test( 'should return the text input value if the option is selected', function ( assert ) {
	const options = [ {
		data: 'option1',
		label: 'Option 1'
	}, {
		data: 'option2',
		label: 'Option 2',
		hasTextInput: true
	}, {
		data: 'option3',
		label: 'Option 3',
		hasTextInput: true
	} ];
	const multiSelectWidget = new SelectWithTextInputWidget( { options, isMultiSelect: true } );
	const textInput = 'text input';
	multiSelectWidget.updateSelection( 'option2' );
	multiSelectWidget.updateTextInputValueForData( 'option2', textInput );
	multiSelectWidget.updateTextInputValueForData( 'option3', textInput );
	assert.strictEqual( multiSelectWidget.getTextInputValueForData( 'option2' ), textInput );
	assert.strictEqual( multiSelectWidget.getTextInputValueForData( 'option3' ), '' );
	multiSelectWidget.updateSelection( [ 'option2', 'option3' ] );
	assert.strictEqual( multiSelectWidget.getTextInputValueForData( 'option2' ), textInput );
	assert.strictEqual( multiSelectWidget.getTextInputValueForData( 'option3' ), textInput );
} );

QUnit.test( 'should disable the text input until the option is selected', function ( assert ) {
	const options = [ {
		data: 'option1',
		label: 'Option 1'
	}, {
		data: 'option2',
		label: 'Option 2',
		hasTextInput: true
	} ];
	const multiSelectWidget = new SelectWithTextInputWidget( { options, isMultiSelect: true } );
	const optionWithTextInputWidget = multiSelectWidget.optionWithTextInputWidgets.option2;
	assert.true( optionWithTextInputWidget.textInputWidget.isDisabled() );
	multiSelectWidget.updateSelection( 'option2' );
	assert.false( optionWithTextInputWidget.textInputWidget.isDisabled() );
} );
