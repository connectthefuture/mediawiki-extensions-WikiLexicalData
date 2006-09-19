<?php

require_once('Editor.php');
require_once('WiktionaryZAttributes.php');

function getTransactionEditor($attribute) {
	global
		$userAttribute, $timestampAttribute;

	$transactionEditor = new RecordTableCellEditor($attribute);
	$transactionEditor->addEditor(new UserEditor($userAttribute, new SimplePermissionController(false), true));
	$transactionEditor->addEditor(new ShortTextEditor($timestampAttribute, new SimplePermissionController(false), true));

	return $transactionEditor;
}

function addTableLifeSpanEditor($editor) {
	global
		$recordLifeSpanAttribute, $addTransactionAttribute, $removeTransactionAttribute, $wgRequest;

	if ($wgRequest->getText('action') == 'history') {
		$lifeSpanEditor = new RecordTableCellEditor($recordLifeSpanAttribute);
		$lifeSpanEditor->addEditor(getTransactionEditor($addTransactionAttribute));
		$lifeSpanEditor->addEditor(getTransactionEditor($removeTransactionAttribute));

		$editor->addEditor($lifeSpanEditor);
	}
}

function getTranslatedTextEditor($attribute, $controller) {
	global
		$languageAttribute, $textAttribute;

	$editor = new RecordSetTableEditor($attribute, new SimplePermissionController(true), true, true, true, $controller);
	$editor->addEditor(new LanguageEditor($languageAttribute, new SimplePermissionController(false), true));
	$editor->addEditor(new TextEditor($textAttribute, new SimplePermissionController(true), true));

	addTableLifeSpanEditor($editor);

	return $editor;
}

function getAlternativeDefinitionsEditor() {
	global
		$alternativeDefinitionsAttribute, $alternativeDefinitionAttribute, $sourceAttribute;

	$editor = new RecordSetTableEditor($alternativeDefinitionsAttribute, new SimplePermissionController(true), true, true, false, new DefinedMeaningAlternativeDefinitionsController());
//		$editor = new RecordSetTableEditor($alternativeDefinitionsAttribute, new AlternativeDefinitionsPermissionController(), true, true, false, new DefinedMeaningAlternativeDefinitionsController());
	$editor->addEditor(getTranslatedTextEditor($alternativeDefinitionAttribute, new DefinedMeaningAlternativeDefinitionController()));
	$editor->addEditor(new DefinedMeaningReferenceEditor($sourceAttribute, new SimplePermissionController(false), true));

	return $editor;
}

function getSynonymsAndTranslationsEditor() {
	global
		$synonymsAndTranslationsAttribute, $identicalMeaningAttribute, $expressionIdAttribute, $expressionAttribute, $languageAttribute,
		$spellingAttribute;

	$expressionEditor = new RecordTableCellEditor($expressionAttribute);
	$expressionEditor->addEditor(new LanguageEditor($languageAttribute, new SimplePermissionController(false), true));
	$expressionEditor->addEditor(new SpellingEditor($spellingAttribute, new SimplePermissionController(false), true));

	$tableEditor = new RecordSetTableEditor($synonymsAndTranslationsAttribute, new SimplePermissionController(true), true, true, false, new SynonymTranslationController());
	$tableEditor->addEditor($expressionEditor);
	$tableEditor->addEditor(new BooleanEditor($identicalMeaningAttribute, new SimplePermissionController(true), true, true));

	addTableLifeSpanEditor($tableEditor);

	return $tableEditor;
}

function getDefinedMeaningRelationsEditor() {
	global
		$relationsAttribute, $relationTypeAttribute, $otherDefinedMeaningAttribute;

	$editor = new RecordSetTableEditor($relationsAttribute, new SimplePermissionController(true), true, true, false, new DefinedMeaningRelationController());
	$editor->addEditor(new RelationTypeEditor($relationTypeAttribute, new SimplePermissionController(false), true));
	$editor->addEditor(new DefinedMeaningReferenceEditor($otherDefinedMeaningAttribute, new SimplePermissionController(false), true));

	addTableLifeSpanEditor($editor);

	return $editor;
}

function getDefinedMeaningClassMembershipEditor() {
	global
		$classMembershipAttribute, $classAttribute;

	$editor = new RecordSetTableEditor($classMembershipAttribute, new SimplePermissionController(true), true, true, false, new DefinedMeaningClassMembershipController());
	$editor->addEditor(new ClassEditor($classAttribute, new SimplePermissionController(false), true));

	addTableLifeSpanEditor($editor);

	return $editor;
}

function getDefinedMeaningCollectionMembershipEditor() {
	global
		$collectionMembershipAttribute, $collectionAttribute, $sourceIdentifierAttribute;

	$editor = new RecordSetTableEditor($collectionMembershipAttribute, new SimplePermissionController(true), true, true, false, new DefinedMeaningCollectionController());
	$editor->addEditor(new CollectionEditor($collectionAttribute, new SimplePermissionController(false), true));
	$editor->addEditor(new ShortTextEditor($sourceIdentifierAttribute, new SimplePermissionController(true), true));

	addTableLifeSpanEditor($editor);

	return $editor;
}

function getDefinedMeaningTextAttributeValuesEditor() {
	global
		$textAttributeAttribute, $textValueAttribute, $textAttributeValuesAttribute;

	$editor = new RecordSetTableEditor($textAttributeValuesAttribute, new SimplePermissionController(true), true, true, false, new DefinedMeaningTextAttributeValuesController());
	$editor->addEditor(new TextAttributeEditor($textAttributeAttribute, new SimplePermissionController(false), true));
	$editor->addEditor(getTranslatedTextEditor($textValueAttribute, new DefinedMeaningTextAttributeValueController()));

	return $editor;
}

function getExpressionMeaningsEditor($attribute, $allowAdd) {
	global
		$definedMeaningIdAttribute;
	
	$definedMeaningEditor = getDefinedMeaningEditor();

	$definedMeaningCaptionEditor = new DefinedMeaningHeaderEditor($definedMeaningIdAttribute, new SimplePermissionController(false), true, 75);
	$definedMeaningCaptionEditor->setAddText("New exact meaning");

	$expressionMeaningsEditor = new RecordSetListEditor($attribute, new SimplePermissionController(true), $allowAdd, false, $allowAdd, new ExpressionMeaningController(), 3, false);
	$expressionMeaningsEditor->setCaptionEditor($definedMeaningCaptionEditor);
	$expressionMeaningsEditor->setValueEditor($definedMeaningEditor);
	
	return $expressionMeaningsEditor;
}

function getExpressionsEditor($spelling) {
	global
		$expressionMeaningsAttribute, $expressionExactMeaningsAttribute, $expressionApproximateMeaningsAttribute, $expressionAttribute, $languageAttribute, $expressionsAttribute;

	$expressionMeaningsRecordEditor = new RecordListEditor($expressionMeaningsAttribute, 3);
	
	$exactMeaningsEditor = getExpressionMeaningsEditor($expressionExactMeaningsAttribute, true);
	$expressionMeaningsRecordEditor->addEditor($exactMeaningsEditor);
	$expressionMeaningsRecordEditor->addEditor(getExpressionMeaningsEditor($expressionApproximateMeaningsAttribute, false));
	
	$expressionMeaningsRecordEditor->expandEditor($exactMeaningsEditor);
	
	$expressionEditor = new RecordSpanEditor($expressionAttribute, ': ', ' - ');
	$expressionEditor->addEditor(new LanguageEditor($languageAttribute, new SimplePermissionController(false), true));

	$expressionsEditor = new RecordSetListEditor($expressionsAttribute, new SimplePermissionController(true), true, false, false, new ExpressionController($spelling), 2, true);
	$expressionsEditor->setCaptionEditor($expressionEditor);
	$expressionsEditor->setValueEditor($expressionMeaningsRecordEditor);



	return $expressionsEditor;
}

function getDefinedMeaningEditor() {
	global
		$definitionAttribute, $definedMeaningAttribute;
		
	$definitionEditor = getTranslatedTextEditor($definitionAttribute, new DefinedMeaningDefinitionController());
	$synonymsAndTranslationsEditor = getSynonymsAndTranslationsEditor();
	$relationsEditor = getDefinedMeaningRelationsEditor();
	$classMembershipEditor = getDefinedMeaningClassMembershipEditor();
	$collectionMembershipEditor = getDefinedMeaningCollectionMembershipEditor();
	$textAttributeValuesEditor = getDefinedMeaningTextAttributeValuesEditor();

	$definedMeaningEditor = new RecordListEditor($definedMeaningAttribute, 4);
	$definedMeaningEditor->addEditor($definitionEditor);
	$definedMeaningEditor->addEditor(getAlternativeDefinitionsEditor());
	$definedMeaningEditor->addEditor($synonymsAndTranslationsEditor);
	$definedMeaningEditor->addEditor($relationsEditor);
	$definedMeaningEditor->addEditor($classMembershipEditor);
	$definedMeaningEditor->addEditor($collectionMembershipEditor);
	$definedMeaningEditor->addEditor($textAttributeValuesEditor);

	$definedMeaningEditor->expandEditor($definitionEditor);
	$definedMeaningEditor->expandEditor($synonymsAndTranslationsEditor);
//		$definedMeaningEditor->expandEditor($relationsEditor);
//		$definedMeaningEditor->expandEditor($classMembershipEditor);
//		$definedMeaningEditor->expandEditor($collectionMembershipEditor);
//		$definedMeaningEditor->expandEditor($textAttributeValuesEditor);

	return $definedMeaningEditor;
}

?>
