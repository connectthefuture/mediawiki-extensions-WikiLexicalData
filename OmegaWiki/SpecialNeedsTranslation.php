<?php
if ( !defined( 'MEDIAWIKI' ) ) die();

require_once( "Wikidata.php" );

/** @file
 * @brief A special page that returns expressions that are available in the source
 * language but has no equivalent in the destination language. The output can be
 * limited according to the available collections.  The output is randomly generated.
 */
class SpecialNeedsTranslation extends SpecialPage {
	function __construct() {
		parent::__construct( 'NeedsTranslation' );
	}

	function execute( $par ) {
		global $wgOut, $wgRequest;

		require_once( "forms.php" );
		require_once( "type.php" );
		require_once( "OmegaWikiAttributes.php" );
		require_once( "ViewInformation.php" );

		initializeOmegaWikiAttributes( new ViewInformation() );
		$wgOut->setPageTitle( wfMessage( 'ow_needs_xlation_title' )->text() );

		$destinationLanguageId = array_key_exists( 'to-lang', $_GET ) ? $_GET['to-lang']:'';
		$collectionId = array_key_exists( 'collection', $_GET ) ? $_GET['collection'] : '';
		$sourceLanguageId = array_key_exists( 'from-lang', $_GET ) ? $_GET['from-lang'] : '';

		$wgOut->addHTML( getOptionPanel(
			array(
				wfMessage( 'ow_needs_xlation_source_lang' )->text() => getSuggest( 'from-lang', 'language', array(), $sourceLanguageId, languageIdAsText( $sourceLanguageId ) ),
				wfMessage( 'ow_needs_xlation_dest_lang' )->text() => getSuggest( 'to-lang', 'language', array(), $destinationLanguageId, languageIdAsText( $destinationLanguageId ) ),
				wfMessage( 'ow_Collection_colon' )->text() => getSuggest( 'collection', 'collection', array(), $collectionId, collectionIdAsText( $collectionId ) )
			)
		) );

		if ( $destinationLanguageId == '' ) {
			$wgOut->addHTML( '<p>' . wfMessage( 'ow_needs_xlation_no_dest_lang' )->text() . '</p>' );
		} else {
			$this->showExpressionsNeedingTranslation( $sourceLanguageId, $destinationLanguageId, $collectionId );
		}
	}

	protected function showExpressionsNeedingTranslation( $sourceLanguageId, $destinationLanguageId, $collectionId ) {

		$o = OmegaWikiAttributes::getInstance();

		$dc = wdGetDataSetContext();
		require_once( "Transaction.php" );
		require_once( "OmegaWikiAttributes.php" );
		require_once( "RecordSet.php" );
		require_once( "Editor.php" );
		require_once( "WikiDataAPI.php" );

		$dbr = wfGetDB( DB_SLAVE );

		// get total Expressions needing translation
		$table = array(
			'source_syntrans' => "{$dc}_syntrans",
			'source_expression' => "{$dc}_expression"
		);
		$vars[] = "COUNT(*)";

		if ( $collectionId != '' ) {
			$table['colcont'] = "{$dc}_collection_contents";
			$conds[] = 'source_syntrans.defined_meaning_id = colcont.member_mid';
		}

		$conds[] = 'source_syntrans.expression_id = source_expression.expression_id';

		if ( $sourceLanguageId != '' ) {
			$conds['source_expression.language_id'] = $sourceLanguageId;
		}
		if ( $collectionId != '' ) {
			$conds['colcont.collection_id'] = $collectionId;
			$conds['colcont.remove_transaction_id'] = null;
		}

		$destinationSQL = $dbr->selectSQLText(
			array(
				'destination_syntrans' => "{$dc}_syntrans",
				'destination_expression' => "{$dc}_expression"
			),
			'destination_syntrans.defined_meaning_id',
			array(
				'destination_syntrans.expression_id = destination_expression.expression_id',
				'destination_expression.language_id' => $destinationLanguageId,
				'destination_syntrans.remove_transaction_id' => null,
				'destination_expression.remove_transaction_id' => null,
			)
		);
		$conds[] = 'source_syntrans.defined_meaning_id NOT IN ( ' . $destinationSQL . ' )';

		$conds['source_syntrans.remove_transaction_id'] = null;
		$conds['source_expression.remove_transaction_id'] = null;

		$queryResultCount = $dbr->selectField(
			$table, $vars, $conds, __METHOD__
		);
		$nbshown = min ( 100, $queryResultCount ) ;

		// get the actual results
		$table = null;
		$vars = null;
		$conds = null;
		$options = null;

		$table = array(
			'src_synt' => 'uw_syntrans',
			'src_exp' => 'uw_expression',
		);
		$vars = array(
			'source_expression_id' => 'src_exp.expression_id',
			'source_language_id' => 'src_exp.language_id',
			'source_spelling' => ' src_exp.spelling',
			'source_defined_meaning_id' => 'src_synt.defined_meaning_id'
		);

		if ( $collectionId != '' ) {
			$table['colcont'] = 'uw_collection_contents';
			$conds[] = 'src_synt.defined_meaning_id = member_mid';
		}

		$conds[] = 'src_synt.expression_id = src_exp.expression_id ';

		if ( $sourceLanguageId != '' ) {
			$conds['src_exp.language_id'] = $sourceLanguageId;
		}

		if ( $collectionId != '' ) {
			$conds['colcont.collection_id'] = $collectionId;
			$conds['colcont.remove_transaction_id'] = null;
		}

		$destinationSQL = $dbr->selectSQLText(
			array(
				'dest_synt' => "{$dc}_syntrans",
				'dest_exp' => "{$dc}_expression"
			),
			'dest_synt.defined_meaning_id',
			array(
				'dest_synt.expression_id = dest_exp.expression_id',
				'dest_exp.language_id' => $destinationLanguageId,
				'dest_synt.remove_transaction_id' => null,
				'dest_exp.remove_transaction_id' => null,
			)
		);
		$conds[] = 'src_synt.defined_meaning_id NOT IN ( ' . $destinationSQL . ' )';

		$conds['src_synt.remove_transaction_id'] = null;
		$conds['src_exp.remove_transaction_id'] = null;

		// limit output and randomize
		$options['LIMIT'] = 100;
		if ( $queryResultCount > 100 ) {
			$startnumber = rand ( 0 , $queryResultCount - 100 ) ;
			$options['OFFSET'] = $startnumber;
		}

		$queryResult = $dbr->select(
			$table, $vars, $conds, __METHOD__, $options
		);

		$definitionAttribute = new Attribute( "definition", wfMessage( "ow_Definition" )->text(), "definition" );

		$recordSet = new ArrayRecordSet( new Structure( $o->definedMeaningId, $o->expressionId, $o->expression, $definitionAttribute ), new Structure( $o->definedMeaningId, $o->expressionId ) );

		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$expressionRecord = new ArrayRecord( $o->expressionStructure );
			$expressionRecord->language = $row->source_language_id;
			$spellingAsLink = definedMeaningReferenceAsLink( $row->source_defined_meaning_id, $row->source_spelling, $row->source_spelling );
			$expressionRecord->spelling = $spellingAsLink ;

			$definition = getDefinedMeaningDefinitionForLanguage( $row->source_defined_meaning_id, $row->source_language_id ) ;
			if ( $definition == "" ) {
				$definition = getDefinedMeaningDefinition( $row->source_defined_meaning_id ) ;
			}

			$recordSet->addRecord( array( $row->source_defined_meaning_id, $row->source_expression_id, $expressionRecord, $definition ) );
		}

		$expressionEditor = new RecordTableCellEditor( $o->expression );
		$expressionEditor->addEditor( new LanguageEditor( $o->language, new SimplePermissionController( false ), false ) );
		$expressionEditor->addEditor( new ShortTextNoEscapeEditor( $o->spelling, new SimplePermissionController( false ), false ) );

		$editor = new RecordSetTableEditor( null, new SimplePermissionController( false ), new ShowEditFieldChecker( true ), new AllowAddController( false ), false, false, null );
		$editor->addEditor( $expressionEditor );
		$editor->addEditor( new TextEditor( $definitionAttribute, new SimplePermissionController( false ), false, true, 75 ) );

		global $wgOut;

		$wgOut->addHTML( "Showing $nbshown out of $queryResultCount" ) ;
		$wgOut->addHTML( $editor->view( new IdStack( "expression" ), $recordSet ) );
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
