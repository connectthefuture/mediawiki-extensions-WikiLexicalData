<?php
/** @file
 */
require_once( 'OmegaWikiAttributes.php' );
require_once( 'Record.php' );
require_once( 'RecordSet.php' );
require_once( 'RecordSetQueries.php' );
require_once( 'ViewInformation.php' );
require_once( 'Wikidata.php' );
require_once( 'WikiDataGlobals.php' );
require_once( 'OmegaWikiDatabaseAPI.php' );

class OmegaWikiRecordSets {

	static function getSynonymForLanguage( $languageId, array &$definedMeaningIds ) {
		$dc = wdGetDataSetContext();

		$sql['table'] = array(
			'synt' => "{$dc}_syntrans",
			'exp' => "{$dc}_expression"
		);
		$sql['vars'] = array(
			'defined_meaning_id' => 'synt.defined_meaning_id',
			'label' => 'exp.spelling'
		);
		$sql['conds'] = array(
			'synt.defined_meaning_id' => $definedMeaningIds,
			'exp.language_id' => $languageId,
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null,
			'exp.expression_id = synt.expression_id',
		);

		return $sql;

	}

	static function getSynonymForAnyLanguage( array &$definedMeaningIds ) {
		$dc = wdGetDataSetContext();

		$sql['table'] = array(
			'synt' => "{$dc}_syntrans",
			'exp' => "{$dc}_expression"
		);
		$sql['vars'] = array(
			'defined_meaning_id' => 'synt.defined_meaning_id',
			'label' => 'exp.spelling'
		);
		$sql['conds'] = array(
			'synt.defined_meaning_id' => $definedMeaningIds,
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null,
			'exp.expression_id = synt.expression_id',
		);

		return $sql;

	}

	static function fetchDefinedMeaningReferenceRecords( $sql, array &$definedMeaningIds, array &$definedMeaningReferenceRecords, $usedAs = '' ) {
		if ( $usedAs == '' ) $usedAs = WLD_DEFINED_MEANING ;
		$dc = wdGetDataSetContext();
		$o = OmegaWikiAttributes::getInstance();

		$foundDefinedMeaningIds = array();

		$dbr = wfGetDB( DB_SLAVE );
		$queryResult = $dbr->select(
			$sql['table'], $sql['vars'], $sql['conds'], __METHOD__
		);

		foreach ( $queryResult as $row ) {
			$definedMeaningId = $row->defined_meaning_id;

			$specificStructure = clone $o->definedMeaningReferenceStructure;
			$specificStructure->setStructureType( $usedAs );
			$record = new ArrayRecord( $specificStructure );
			$record->definedMeaningId = $definedMeaningId;
			$record->definedMeaningLabel = $row->label;

			$definedMeaningReferenceRecords[$definedMeaningId] = $record;
			$foundDefinedMeaningIds[] = $definedMeaningId;
		}

		$definedMeaningIds = array_diff( $definedMeaningIds, $foundDefinedMeaningIds );
	}

}

/**
 * returns the id and spelling of the "Defining expression"s,
 * corresponding to the dm_id in $definedMeaningIds
 * the defining expression is {$dc}_defined_meaning.expression_id
 * @note Unused.
 */
function getDefiningSQLForLanguage( $languageId, array &$definedMeaningIds ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$sqlQuery = $dbr->selectSQLText(
		array(
			'synt' => "{$dc}_syntrans",
			'exp' => "{$dc}_expression"
		), array( /* fields to select */
			'defined_meaning_id' => 'synt.defined_meaning_id',
			'label' => 'exp.spelling'
		), array( /* where */
			'synt.defined_meaning_id' => $definedMeaningIds,
			'exp.language_id' => $languageId,
			'exp.expression_id = dm.expression_id', // getting defining expression
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null,
			'exp.expression_id = synt.expression_id',
		), __METHOD__
	);

	return $sqlQuery;
}

function fetchDefinedMeaningDefiningExpressions( array &$definedMeaningIds, array &$definedMeaningReferenceRecords ) {
	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$queryResult = $dbr->select(
		array(
			'dm' => "{$dc}_defined_meaning",
			'exp' => "{$dc}_expression"
		), array( /* fields to select */
			'defined_meaning_id' => "dm.defined_meaning_id",
			'spelling' => "exp.spelling"
		), array( /* where */
			'exp.expression_id = dm.expression_id', // getting defining expression
			'dm.defined_meaning_id' => $definedMeaningIds,
			'exp.remove_transaction_id' => null,
			'dm.remove_transaction_id' => null
		), __METHOD__
	);

	foreach ( $queryResult as $row ) {
		if ( isset( $definedMeaningReferenceRecords[$row->defined_meaning_id] ) ) {
			$definedMeaningReferenceRecord = $definedMeaningReferenceRecords[$row->defined_meaning_id];
		} else {
			$definedMeaningReferenceRecord = null;
		}
		if ( $definedMeaningReferenceRecord == null ) {
			$definedMeaningReferenceRecord = new ArrayRecord( $o->definedMeaningReferenceStructure );
			$definedMeaningReferenceRecord->definedMeaningId = $row->defined_meaning_id;
			$definedMeaningReferenceRecord->definedMeaningLabel = $row->spelling;
			$definedMeaningReferenceRecords[$row->defined_meaning_id] = $definedMeaningReferenceRecord;
		}

		$definedMeaningReferenceRecord->definedMeaningDefiningExpression = $row->spelling;
	}
}

function getNullDefinedMeaningReferenceRecord() {

	$o = OmegaWikiAttributes::getInstance();

	$record = new ArrayRecord( $o->definedMeaningReferenceStructure );
	$record->definedMeaningId = 0;
	$record->definedMeaningLabel = "";
	$record->definedMeaningDefiningExpression = "";

	return $record;
}

function getDefinedMeaningReferenceRecords( array $definedMeaningIds, $usedAs ) {
	$userLanguageId = OwDatabaseAPI::getUserLanguageId();

//	$startTime = microtime(true);

	$result = array();
	$definedMeaningIdsForExpressions = $definedMeaningIds;

	if ( count( $definedMeaningIds ) > 0 ) {
		if ( $userLanguageId > 0 ) {
			OmegaWikiRecordSets::fetchDefinedMeaningReferenceRecords(
				OmegaWikiRecordSets::getSynonymForLanguage( $userLanguageId, $definedMeaningIds ),
				$definedMeaningIds,
				$result,
				$usedAs
			);
		}

		if ( count( $definedMeaningIds ) > 0 ) {
			OmegaWikiRecordSets::fetchDefinedMeaningReferenceRecords(
				OmegaWikiRecordSets::getSynonymForLanguage( WLD_ENGLISH_LANG_ID, $definedMeaningIds ),
				$definedMeaningIds,
				$result,
				$usedAs
			);

			if ( count( $definedMeaningIds ) > 0 ) {
				OmegaWikiRecordSets::fetchDefinedMeaningReferenceRecords(
					OmegaWikiRecordSets::getSynonymForAnyLanguage( $definedMeaningIds ),
					$definedMeaningIds,
					$result,
					$usedAs
				);
			}
		}

		fetchDefinedMeaningDefiningExpressions( $definedMeaningIdsForExpressions, $result );
		$result[0] = getNullDefinedMeaningReferenceRecord();

	} // if ( count( $definedMeaningIds ) > 0 )

//	$queriesTime = microtime(true) - $startTime;
//	echo "<!-- Defined meaning reference queries: " . $queriesTime . " -->\n";

	return $result;
}

function expandDefinedMeaningReferencesInRecordSet( RecordSet $recordSet, array $definedMeaningAttributes ) {
	$definedMeaningReferenceRecords = array();

	foreach ( $definedMeaningAttributes as $dmatt ) {
		$tmpArray = getDefinedMeaningReferenceRecords( getUniqueIdsInRecordSet( $recordSet, array( $dmatt ) ), $dmatt->id );
		$definedMeaningReferenceRecords += $tmpArray;
	}

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		foreach ( $definedMeaningAttributes as $att ) {
			$record->setAttributeValue(
				$att,
				$definedMeaningReferenceRecords[$record->getAttributeValue( $att )]
			);
		}
	}
}

function getSyntransReferenceRecords( array $syntransIds, $usedAs ) {
	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	// an array of records
	$result = array();

	$structure = new Structure( WLD_SYNONYMS_TRANSLATIONS, $o->syntransId, $o->spelling );
	$structure->setStructureType( $usedAs );

	$queryResult = $dbr->select(
		array(
			'synt' => "{$dc}_syntrans",
			'exp' => "{$dc}_expression"
		), array (
			'syntrans_sid',
			'spelling'
		), array (
			'syntrans_sid' => $syntransIds,
			'exp.expression_id = synt.expression_id'
		), __METHOD__
	);

	foreach ( $queryResult as $row ) {
		$record = new ArrayRecord( $structure );
		$syntransId = $row->syntrans_sid;
		$record->syntransId = $syntransId;
		$record->spelling = $row->spelling;
		$result[$syntransId] = $record;
	}
	return $result;
}

function expandSyntransReferencesInRecordSet( RecordSet $recordSet, array $syntransAttributes ) {
	$syntransReferenceRecords = array();

	foreach ( $syntransAttributes as $att ) {
		$listIds = getUniqueIdsInRecordSet( $recordSet, array( $att ) );
		if ( $listIds ) {
			// can be empty... why?
			$syntransReferenceRecords += getSyntransReferenceRecords( $listIds, $att->id );
		}
	}

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		foreach ( $syntransAttributes as $att ) {
			$record->setAttributeValue(
				$att,
				$syntransReferenceRecords[$record->getAttributeValue( $att )]
			);
		}
	}
}

function expandTranslatedContentInRecord( Record $record, Attribute $idAttribute, Attribute $translatedContentAttribute, ViewInformation $viewInformation ) {
	$record->setAttributeValue(
		$translatedContentAttribute,
		getTranslatedContentRecordSet( $record->getAttributeValue( $idAttribute ), $viewInformation )
	);
}

function expandTranslatedContentsInRecordSet( RecordSet $recordSet, Attribute $idAttribute, Attribute $translatedContentAttribute, ViewInformation $viewInformation ) {
	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		expandTranslatedContentInRecord( $recordSet->getRecord( $i ), $idAttribute, $translatedContentAttribute, $viewInformation );
	}
}

function getExpressionSpellings( array $expressionIds ) {
	$dc = wdGetDataSetContext();

	if ( count( $expressionIds ) > 0 ) {
		$dbr = wfGetDB( DB_SLAVE );

		$queryResult = $dbr->select(
			"{$dc}_expression",
			array( 'expression_id', 'spelling' ),
			array( /* where */
				'expression_id' => $expressionIds,
				'remove_transaction_id' => null
			), __METHOD__
		);

		foreach ( $queryResult as $row ) {
			$result[$row->expression_id] = $row->spelling;
		}
		return $result;
	} else {
		return array();
	}
}

function expandExpressionSpellingsInRecordSet( RecordSet $recordSet, array $expressionAttributes ) {
	$expressionSpellings = getExpressionSpellings( getUniqueIdsInRecordSet( $recordSet, $expressionAttributes ) );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );

		foreach ( $expressionAttributes as $expressionAttribute ) {
			$record->setAttributeValue(
				$expressionAttribute,
				$expressionSpellings[$record->getAttributeValue( $expressionAttribute )]
			);
		}
	}
}

function getTextReferences( array $textIds ) {
	$dc = wdGetDataSetContext();
	if ( count( $textIds ) > 0 ) {
		$dbr = wfGetDB( DB_SLAVE );

		$queryResult = $dbr->select(
			"{$dc}_text",
			array( 'text_id', 'text_text' ),
			array( /* where */
				'text_id' => $textIds
			), __METHOD__
		);

		foreach ( $queryResult as $row ) {
			$result[$row->text_id] = $row->text_text;
		}
		return $result;
	} else {
		return array();
	}
}

function expandTextReferencesInRecordSet( RecordSet $recordSet, array $textAttributes ) {
	$textReferences = getTextReferences( getUniqueIdsInRecordSet( $recordSet, $textAttributes ) );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );

		foreach ( $textAttributes as $textAttribute ) {
			$textId = $record->getAttributeValue( $textAttribute );

			if ( isset( $textReferences[$textId] ) ) {
				$textValue = $textReferences[$textId];
			} else {
				$textValue = "";
			}
			$record->setAttributeValue( $textAttribute, $textValue );
		}
	}
}

/**
* The corresponding Editor function is getExpressionMeaningsEditor
* $exactMeaning is a boolean
*/
function getExpressionMeaningsRecordSet( $expressionId, $exactMeaning, ViewInformation $viewInformation ) {
	global $wgWldSortingAnnotationDM;

	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$identicalMeaning = $exactMeaning ? 1 : 0;
	$recordSet = new ArrayRecordSet( $o->expressionMeaningStructure, new Structure( $o->definedMeaningId ) );

	// returns all syntrans-dm corresponding to a given expressionId
	// which are one for each meaning of a word in a language.
	$queryResult = $dbr->select(
		array( 'synt' => "{$dc}_syntrans" ),
		array( 'defined_meaning_id', 'syntrans_sid' ),
		array(
			'expression_id' => $expressionId,
			'identical_meaning' => $identicalMeaning,
			'synt.remove_transaction_id' => null
		), __METHOD__
	);

	if ( ! is_null ( $wgWldSortingAnnotationDM ) ) {
		$sortArray = array();
	}
	foreach ( $queryResult as $syntrans ) {
		$definedMeaningId = $syntrans->defined_meaning_id;
		$syntransId = $syntrans->syntrans_sid;
		$dmModelParams = array( "viewinformation" => $viewInformation, "syntransid" => $syntransId );
		$dmModel = new DefinedMeaningModel( $definedMeaningId, $dmModelParams );
		$dmModelRecord = $dmModel->getRecord();
		$recordSet->addRecord(
			array(
				$definedMeaningId,
				getDefinedMeaningDefinition( $definedMeaningId ),
				$dmModelRecord
			)
		);

		if ( ! is_null ( $wgWldSortingAnnotationDM ) ) {
			// create the sortArray for sorting the records according to some annotations
			// we sort according to syntrans_attributes (lexical annotations) and among
			// them we use only the option attributes (i.e. annotations from a closed list)
			// We could sort with other attributes, or semantic annotations, which would require a more complex
			// system if we want something that is user-configurable or modular.
			$synTransAttributesRecord = $dmModelRecord->syntransAttributes; // getObjectAttributesRecord
			$optionAttributeValues = $synTransAttributesRecord->optionAttributeValues; // getOptionAttributeValuesRecordSet

			// undefined annotations to be sorted at the end.
			$sortingValue = 'zzz';

			if ( $optionAttributeValues->getRecordCount() > 0 ) {
				// there are option attributes in there (i.e. annotations from a closed list)

				// we have to go through all optionAttributeValues and find the one which has
				// the correct option_mid that is needed for sort (such as option_mid = dm of partofspeech)
				for ( $i = 0; $i < $optionAttributeValues->getRecordCount(); $i++ ) {
					$record = $optionAttributeValues->getRecord( $i );
					// record->optionAttribute->definedMeaningId is the DM id of the attribute ("part of speech")
					// record->optionAttributeOption->definedMeaningId is the DM id of the value ("noun", "verb", etc.)
					// record->optionAttributeOption->definedMeaningLabel is its label, string ("noun", "verb", etc.)
					if ( $record->optionAttribute->definedMeaningId == $wgWldSortingAnnotationDM ) {
						$sortingValue = ucfirst( $record->optionAttributeOption->definedMeaningLabel );
					}
				}
			}
			$sortArray[] = $sortingValue;
		} // if not null wgWldSortingAnnotationDM
	} // foreachs $syntrans

	if ( ! is_null ( $wgWldSortingAnnotationDM ) ) {
		// here we can sort the recordSet according to annotations
		$recordSet->sortRecord( $sortArray );
	}
	return $recordSet;
}

/**
* The corresponding Editor function is getExpressionsEditor
*/
function getExpressionMeaningsRecord( $expressionId, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$record = new ArrayRecord( $o->expressionMeaningsStructure );
	$exactMeaning = true;
	$record->expressionExactMeanings = getExpressionMeaningsRecordSet( $expressionId, $exactMeaning, $viewInformation );
	$exactMeaning = false;
	$record->expressionApproximateMeanings = getExpressionMeaningsRecordSet( $expressionId, $exactMeaning, $viewInformation );

	return $record;
}

/**
 * Returns an arrayRecord of one expression (since we have only one language per page)
 * and the corresponding language_id, for a given $spelling
 * The language returned depends on several criteria
 */
function getExpressionsRecordSet( $spelling, ViewInformation $viewInformation, $dc = null ) {
	wfProfileIn( __METHOD__ );
	$o = OmegaWikiAttributes::getInstance();

	$queryResult = null;
	$expressionLang = WLD_ENGLISH_LANG_ID ; // default english
	$result = new ArrayRecordSet( $o->expressionsStructure, new Structure( "expression-id", $o->expressionId ) );

	if ( $viewInformation->expressionLanguageId != 0 ) {
		// display the expression in the requested language (url &explang=...)
		// if there is nothing, display nothing (doesn't try to find another language)
		$expressionLang = $viewInformation->expressionLanguageId;
		$expressionId = getExpressionId( $spelling, $expressionLang );

	} else {
		// default: is there an expression in the user language?
		if ( $userLanguageId = OwDatabaseAPI::getUserLanguageId() ) {
			$expressionLang = $userLanguageId;
		}
		// else expressionLang is WLD_ENGLISH_LANG_ID , as defined above
		$expressionId = getExpressionId( $spelling, $expressionLang );

		if ( ! $expressionId ) {
			// nothing in the user language (or English). Find anything
			$expression = getExpressionIdAnyLanguage( $spelling );
			if ( is_null ( $expression ) ) {
				// nothing at all, return the empty arrayrecord
				return $result;
			}
			$expressionId = $expression->expression_id;
			$expressionLang = $expression->language_id;
		}
	}

	// filling ArrayRecord with what was found.
	$languageStructure = new Structure( "language", $o->language );

	$expressionRecord = new ArrayRecord( $languageStructure );
	$expressionRecord->language = $expressionLang;

	$result->addRecord( array(
		$expressionId,
		$expressionRecord,
		getExpressionMeaningsRecord( $expressionId, $viewInformation )
	) );

	wfProfileOut( __METHOD__ );

	return $result;
}

function getClassAttributesRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->classAttributesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->classAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'object_id' ), $o->classAttributeId ),
			new TableColumnsToAttribute( array( 'level_mid' ), $o->classAttributeLevel ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->classAttributeAttribute ),
			new TableColumnsToAttribute( array( 'attribute_type' ), $o->classAttributeType )
		),
		$wgWikidataDataSet->classAttributes,
		array( "class_mid=$definedMeaningId" )
	);

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->classAttributeLevel , $o->classAttributeAttribute ) );
	expandOptionAttributeOptionsInRecordSet( $recordSet, $o->classAttributeId, $viewInformation );

	return $recordSet;
}

function expandOptionAttributeOptionsInRecordSet( RecordSet $recordSet, Attribute $attributeIdAttribute, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();
	$recordSet->getStructure()->addAttribute( $o->optionAttributeOptions );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );

		$record->optionAttributeOptions = getOptionAttributeOptionsRecordSet( $record->getAttributeValue( $attributeIdAttribute ), $viewInformation );
	}
}

function getAlternativeDefinitionsRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->alternativeDefinitionsStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->definitionId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'meaning_text_tcid' ), $o->definitionId ),
			new TableColumnsToAttribute( array( 'source_id' ), $o->source )
		),
		$wgWikidataDataSet->alternativeDefinitions,
		array( "meaning_mid=$definedMeaningId" )
	);

	$recordSet->getStructure()->addAttribute( $o->alternativeDefinition );

	expandTranslatedContentsInRecordSet( $recordSet, $o->definitionId, $o->alternativeDefinition, $viewInformation );
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->source ) );

	return $recordSet;
}

function getDefinedMeaningDefinitionRecord( $definedMeaningId, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$definitionId = getDefinedMeaningDefinitionId( $definedMeaningId );
	$record = new ArrayRecord( $o->definition->type );
	$record->translatedText = getTranslatedContentRecordSet( $definitionId, $viewInformation );

	// (Kip) What is this? There is no attributes to a definition => commented
	// $objectAttributesRecord = getObjectAttributesRecord( $definitionId, $viewInformation, $o->objectAttributes->id );
	// $record->objectAttributes = $objectAttributesRecord;

	// applyPropertyToColumnFiltersToRecord( $record, $objectAttributesRecord, $viewInformation );

	return $record;
}

function applyPropertyToColumnFiltersToRecord( Record $destinationRecord, Record $sourceRecord, ViewInformation $viewInformation ) {
	foreach ( $viewInformation->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
		$destinationRecord->setAttributeValue(
			$propertyToColumnFilter->getAttribute(),
			filterObjectAttributesRecord( $sourceRecord, $propertyToColumnFilter->attributeIDs )
		);
	}
}

function applyPropertyToColumnFiltersToRecordSet( RecordSet $recordSet, Attribute $sourceAttribute, ViewInformation $viewInformation ) {
	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$attributeValuesRecord = $recordSet->getAttributeValue( $sourceAttribute );

		applyPropertyToColumnFiltersToRecord( $record, $attributeValuesRecord, $viewInformation );
	}
}

function getObjectAttributesRecord( $objectId, ViewInformation $viewInformation, $structuralOverride = null, $level = "" ) {
	$o = OmegaWikiAttributes::getInstance();

	if ( $structuralOverride ) {
		$record = new ArrayRecord( new Structure( $structuralOverride, $o->definedMeaningAttributes->type->getAttributes() ) );
	} else {
		$record = new ArrayRecord( $o->definedMeaningAttributes->type );
	}

	$record->objectId = $objectId;
	$record->relations = getRelationsRecordSet( array( $objectId ), $viewInformation, $level );
	$record->textAttributeValues = getTextAttributesValuesRecordSet( array( $objectId ), $viewInformation );
	$record->translatedTextAttributeValues = getTranslatedTextAttributeValuesRecordSet( array( $objectId ), $viewInformation );
	$record->linkAttributeValues = getLinkAttributeValuesRecordSet( array( $objectId ), $viewInformation );
	$record->optionAttributeValues = getOptionAttributeValuesRecordSet( array( $objectId ), $viewInformation );

	return $record;
}

function filterAttributeValues( RecordSet $sourceRecordSet, Attribute $attributeAttribute, array &$attributeIds ) {
	$result = new ArrayRecordSet( $sourceRecordSet->getStructure(), $sourceRecordSet->getKey() );
	$i = 0;

	while ( $i < $sourceRecordSet->getRecordCount() ) {
		$record = $sourceRecordSet->getRecord( $i );

		if ( in_array( $record->getAttributeValue( $attributeAttribute )->definedMeaningId, $attributeIds ) ) {
			$result->add( $record );
			$sourceRecordSet->remove( $i );
		} else {
			$i++;
		}
	}

	return $result;
}

function filterObjectAttributesRecord( Record $sourceRecord, array &$attributeIds ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = new ArrayRecord( $sourceRecord->getStructure() );
	$result->objectId = $sourceRecord->objectId;

	$result->setAttributeValue( $o->relations, filterAttributeValues(
		$sourceRecord->relations,
		$o->relationType,
		$attributeIds
	) );

	$result->setAttributeValue( $o->textAttributeValues, filterAttributeValues(
		$sourceRecord->textAttributeValues,
		$o->textAttribute,
		$attributeIds
	) );

	$result->setAttributeValue( $o->translatedTextAttributeValues, filterAttributeValues(
		$sourceRecord->translatedTextAttributeValues,
		$o->translatedTextAttribute,
		$attributeIds
	) );

	$result->setAttributeValue( $o->linkAttributeValues, filterAttributeValues(
		$sourceRecord->linkAttributeValues,
		$o->linkAttribute,
		$attributeIds
	) );

	$result->setAttributeValue( $o->optionAttributeValues, filterAttributeValues(
		$sourceRecord->optionAttributeValues,
		$o->optionAttribute,
		$attributeIds
	) );

	return $result;
}

function getTranslatedContentRecordSet( $translatedContentId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$vars = array( 'language_id', 'text_id' );
	$cond = array( 'translated_content_id' => $translatedContentId );

	if ( ! $viewInformation->showRecordLifeSpan ) {
		// not in history view: don't show deleted content
		$cond['remove_transaction_id'] = null;
	} else {
		// in history view: retrieve history information
		$vars[] = 'add_transaction_id';
		$vars[] = 'remove_transaction_id';
	}

	// filter on languages, if activated by the user
	$langsubset = $viewInformation->getFilterLanguageList();
	if ( ! empty( $langsubset ) ) {
		$cond['language_id'] = $langsubset;
	}

	$queryResult = $dbr->select(
		"{$dc}_translated_content",
		$vars,
		$cond,
		__METHOD__
	);

	// putting the sql result first in an array for sorting
	// at the same time, an array of language names, used for sorting, is created.
	$queryResultArray = array();
	$sortOrderArray = array();
	$languageNames = getOwLanguageNames();
	foreach ( $queryResult as $row ) {
		$queryResultArray[] = $row;
		$sortOrderArray[] = $languageNames[$row->language_id];
	}
	// magic sort $queryResultArray on language names
	array_multisort(
		$sortOrderArray, SORT_ASC, SORT_LOCALE_STRING | SORT_FLAG_CASE,
		$queryResultArray // sorted like the one above
	);

	$structure = $o->translatedTextStructure ;
	if ( $viewInformation->showRecordLifeSpan ) {
		// additional attributes for history view
		$structure->addAttribute( $o->recordLifeSpan );
	}
	// keyAttribute is stored in the $keyPath and used by the controller
	$keyAttribute = $o->language ;
	$recordSet = new ArrayRecordSet( $structure, new Structure( $keyAttribute ) );

	foreach ( $queryResultArray as $row ) {
		$record = new ArrayRecord( $structure );
		$record->language = $row->language_id;
		$record->text = $row->text_id; // expanded below

		// adds transaction details for history view
		if ( $viewInformation->showRecordLifeSpan ) {
			$record->recordLifeSpan = getRecordLifeSpanTuple ( $row->add_transaction_id, $row->remove_transaction_id ) ;
		}
		$recordSet->add( $record );
	}

	expandTextReferencesInRecordSet( $recordSet, array( $o->text ) );

	return $recordSet;
}

/**
* the corresponding Editor is getSynonymsAndTranslationsEditor
*/
function getSynonymAndTranslationRecordSet( $definedMeaningId, ViewInformation $viewInformation, $excludeSyntransId = null ) {
	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );


	$vars = array(
		'syntrans_sid',
		'expression_id' => 'synt.expression_id',
		'identical_meaning',
		'language_id',
		'spelling'
	);
	$cond = array(
		'defined_meaning_id' => $definedMeaningId,
		'exp.expression_id = synt.expression_id'
	);

	if ( ! $viewInformation->showRecordLifeSpan ) {
		// not in history view: don't show deleted content
		$cond['synt.remove_transaction_id'] = null;
	} else {
		// in history view: retrieve history information
		$vars['add_transaction_id'] = 'synt.add_transaction_id';
		$vars['remove_transaction_id'] = 'synt.remove_transaction_id';
	}

	// filter on languages, if activated by the user
	$langsubset = $viewInformation->getFilterLanguageList() ;
	if ( ! empty( $langsubset ) ) {
		$cond['language_id'] = $langsubset;
	}

	// the order-by is used to get the identical translations on top
	$queryResult = $dbr->select(
		array( 'synt' => "{$dc}_syntrans", 'exp' => "{$dc}_expression" ),
		$vars,
		$cond,
		__METHOD__,
		array( 'ORDER BY' => 'identical_meaning DESC' )
	);

	// putting the sql result first in an array for sorting
	// at the same time, an array of language names, used for sorting, is created.
	$queryResultArray = array();
	$sortOrderArray = array();
	$languageNames = getOwLanguageNames();
	foreach ( $queryResult as $row ) {
		$queryResultArray[] = $row;
		// since we want the inexact translations below the exact ones
		// we add a "0" for exact trans, "1" for inexact trans (this is reverse compared to the value of the db)
		$sortSuffix = ( $row->identical_meaning == 1 ) ? "0" : "1";
		$sortOrderArray[] = $languageNames[$row->language_id] . $sortSuffix . $row->spelling;
	}
	// magic sort $queryResultArray on language names - then inexact flag - then orthography
	array_multisort(
		$sortOrderArray, SORT_ASC, SORT_LOCALE_STRING | SORT_FLAG_CASE,
		$queryResultArray // sorted like the one above
	);


	// TODO; try with synTransExpressionStructure instead of synonymsTranslationsStructure
	// so that expression is not a sublevel of the hierarchy, but on the same level
	//	$structure = $o->synTransExpressionStructure ;
	$structure = $o->synonymsTranslationsStructure ;
	$structure->addAttribute( $o->objectAttributes );

	// adds additional attributes for history view if needed
	if ( $viewInformation->showRecordLifeSpan ) {
		$structure->addAttribute( $o->recordLifeSpan );
	}

	$keyAttribute = $o->syntransId ;
	$recordSet = new ArrayRecordSet( $structure, new Structure( $keyAttribute ) );

	foreach ( $queryResultArray as $row ) {
		$syntransId = $row->syntrans_sid;
		if ( $syntransId == $excludeSyntransId ) {
			continue;
		}

		$record = new ArrayRecord( $structure );
		$record->syntransId = $syntransId;
		$record->identicalMeaning = $row->identical_meaning;

		// adds the expression structure
		$expressionRecord = new ArrayRecord( $o->expressionStructure );
		$expressionRecord->language = $row->language_id;
		$expressionRecord->spelling = $row->spelling;
		$record->expression = $expressionRecord;

		// adds the annotations (if any)
		// don't load the annotations, they are loaded dynamically when clicked, with ajax.
		// $record->objectAttributes = getObjectAttributesRecord( $syntransId, $viewInformation, null, "SYNT" );

		// adds transaction details for history view
		if ( $viewInformation->showRecordLifeSpan ) {
			$record->recordLifeSpan = getRecordLifeSpanTuple ( $row->add_transaction_id, $row->remove_transaction_id ) ;
		}

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getDefinedMeaningReferenceRecord( $definedMeaningId ) {
	$o = OmegaWikiAttributes::getInstance();

	$record = new ArrayRecord( $o->definedMeaningReferenceStructure );
	$record->definedMeaningId = $definedMeaningId;
	$record->definedMeaningLabel = OwDatabaseAPI::getDefinedMeaningExpression( $definedMeaningId );
	$record->definedMeaningDefiningExpression = OwDatabaseAPI::definingExpression( $definedMeaningId );

	return $record;
}

function getRelationsRecordSet( array $objectIds, ViewInformation $viewInformation, $level = "" ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->relationStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->relationId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'relation_id' ), $o->relationId ),
			new TableColumnsToAttribute( array( 'relationtype_mid' ), $o->relationType ),
			new TableColumnsToAttribute( array( 'meaning2_mid' ), $o->otherObject )
		),
		$wgWikidataDataSet->meaningRelations,
		array( "meaning1_mid IN (" . implode( ", ", $objectIds ) . ")" ),
		array( 'relationtype_mid' )
	);

	if ( $level == "SYNT" ) {
		expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->relationType ) );
		expandSyntransReferencesInRecordSet( $recordSet, array( $o->otherObject ) );
	} else {
		// assuming DM relations
		expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->relationType, $o->otherObject ) );
	}

	return $recordSet;
}

function getDefinedMeaningReciprocalRelationsRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		$o->reciprocalRelations->id,
		$viewInformation->queryTransactionInformation,
		$o->relationId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'relation_id' ), $o->relationId ),
			new TableColumnsToAttribute( array( 'relationtype_mid' ), $o->relationType ),
			new TableColumnsToAttribute( array( 'meaning1_mid' ), $o->otherObject )
		),
		$wgWikidataDataSet->meaningRelations,
		array( "meaning2_mid=$definedMeaningId" ),
		array( 'relationtype_mid' )
	);

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->relationType, $o->otherObject ) );

	return $recordSet;
}

function getGotoSourceRecord( $record ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = new ArrayRecord( $o->gotoSourceStructure );
	$result->collectionId = $record->collectionId;
	$result->sourceIdentifier = $record->sourceIdentifier;

	return $result;
}

function getDefinedMeaningCollectionMembershipRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->collectionMembershipStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->collectionId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'collection_id' ), $o->collectionId ),
			new TableColumnsToAttribute( array( 'internal_member_id' ), $o->sourceIdentifier )
		),
		$wgWikidataDataSet->collectionMemberships,
		array( "member_mid=$definedMeaningId" )
	);

	$structure = $recordSet->getStructure();
	$structure->addAttribute( $o->collectionMeaning );
	$structure->addAttribute( $o->gotoSource );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$record->collectionMeaning = getCollectionMeaningId( $record->collectionId );
		$record->gotoSource = getGotoSourceRecord( $record );
	}

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->collectionMeaning ) );

	return $recordSet;
}

function getTextAttributesValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->textAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->textAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->textAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->textAttributeObject ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->textAttribute ),
			new TableColumnsToAttribute( array( 'text' ), $o->text )
		),
		$wgWikidataDataSet->textAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->textAttribute ) );

	return $recordSet;
}

function getLinkAttributeValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		$o->linkAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->linkAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->linkAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->linkAttributeObject ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->linkAttribute ),
			new TableColumnsToAttribute( array( 'label', 'url' ), $o->link )
		),
		$wgWikidataDataSet->linkAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->linkAttribute ) );

	return $recordSet;
}

function getTranslatedTextAttributeValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		 $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->translatedTextAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->translatedTextAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->translatedTextAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->attributeObjectId ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->translatedTextAttribute ),
			new TableColumnsToAttribute( array( 'value_tcid' ), $o->translatedTextValueId )
		),
		$wgWikidataDataSet->translatedContentAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);

	$recordSet->getStructure()->addAttribute( $o->translatedTextValue );

	expandTranslatedContentsInRecordSet( $recordSet, $o->translatedTextValueId, $o->translatedTextValue, $viewInformation );
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->translatedTextAttribute ) );

	return $recordSet;
}

function getOptionAttributeOptionsRecordSet( $attributeId, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		null,
		$viewInformation->queryTransactionInformation,
		$o->optionAttributeOptionId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'option_id' ), $o->optionAttributeOptionId ),
			new TableColumnsToAttribute( array( 'attribute_id' ), $o->optionAttribute ),
			new TableColumnsToAttribute( array( 'option_mid' ), $o->optionAttributeOption ),
			new TableColumnsToAttribute( array( 'language_id' ), $o->language )
		),
		$wgWikidataDataSet->optionAttributeOptions,
		array( 'attribute_id = ' . $attributeId )
	);

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->optionAttributeOption ) );

	return $recordSet;
}

function getOptionAttributeValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		$o->optionAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->optionAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->optionAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->optionAttributeObject ),
			new TableColumnsToAttribute( array( 'option_id' ), $o->optionAttributeOptionId )
		),
		$wgWikidataDataSet->optionAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);

	expandOptionsInRecordSet( $recordSet, $viewInformation );
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->optionAttribute, $o->optionAttributeOption ) );

	return $recordSet;
}

/* XXX: This can probably be combined with other functions. In fact, it probably should be. Do it. */
function expandOptionsInRecordSet( RecordSet $recordSet, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet->getStructure()->addAttribute( $o->optionAttributeOption );
	$recordSet->getStructure()->addAttribute( $o->optionAttribute );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );

		$optionRecordSet = queryRecordSet(
			null,
			$viewInformation->queryTransactionInformation,
			$o->optionAttributeOptionId,
			new TableColumnsToAttributesMapping(
				new TableColumnsToAttribute( array( 'attribute_id' ), $o->optionAttributeId ),
				new TableColumnsToAttribute( array( 'option_mid' ), $o->optionAttributeOption )
			),
			$wgWikidataDataSet->optionAttributeOptions,
			array( 'option_id = ' . $record->optionAttributeOptionId )
		);

		$optionRecord = $optionRecordSet->getRecord( 0 );
		$record->optionAttributeOption = $optionRecord->optionAttributeOption;

		$optionRecordSet = queryRecordSet(
			null,
			$viewInformation->queryTransactionInformation,
			$o->optionAttributeId,
			new TableColumnsToAttributesMapping( new TableColumnsToAttribute( array( 'attribute_mid' ), $o->optionAttribute ) ),
			$wgWikidataDataSet->classAttributes,
			array( 'object_id = ' . $optionRecord->optionAttributeId )
		);

		$optionRecord = $optionRecordSet->getRecord( 0 );
		$record->optionAttribute = $optionRecord->optionAttribute;
	}
}

function getDefinedMeaningClassMembershipRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->classMembershipStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->classMembershipId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'class_membership_id' ), $o->classMembershipId ),
			new TableColumnsToAttribute( array( 'class_mid' ), $o->class )
		),
		$wgWikidataDataSet->classMemberships,
		array( "class_member_mid=$definedMeaningId" )
	);

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->class ) );

	return $recordSet;
}

function getDefiningExpressionRecord( $definedMeaningId ) {

		$o = OmegaWikiAttributes::getInstance();

		$definingExpression = definingExpressionRow( $definedMeaningId );
		$definingExpressionRecord = new ArrayRecord( $o->definedMeaningCompleteDefiningExpression->type );
		$definingExpressionRecord->expressionId = $definingExpression[0];
		$definingExpressionRecord->definedMeaningDefiningExpression = $definingExpression[1];
		$definingExpressionRecord->language = $definingExpression[2];
		return $definingExpressionRecord;
}
