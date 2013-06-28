<?php
/**
 * Create a list of Expressions with Definitions
 *
 */
global $wgWldOwScriptPath, $wgWldIncludesScriptPath;
require_once( $wgWldOwScriptPath . 'languages.php' );
require_once( $wgWldIncludesScriptPath . 'formatCSV.php' );

Class CreateDefinedExpressionListJob extends Job {

	public function __construct( $title, $params ) {
		parent::__construct( 'CreateDefinedExpressionList', $title, $params );
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		// Load data from $this->params and $this->title
		if ( isset( $this->params['langcode'] ) ) {
			$languageId = $this->params['langcode'];
		}

		if ( isset( $this->params['type'] ) ) {
			$type = $this->params['type'];
		}

		if ( isset( $this->params['format'] ) ) {
			$format = $this->params['format'];
		}

		if ( isset( $this->params['start'] ) ) {
			$start = $this->params['start'];
		}

		if ( $type && $languageId && $format && $start ) {
			$this->createDefinedList( $type, $languageId, $format, $start );
			return true;
		}

		// Perform your updates

		return false;
	}

	protected function createDefinedList( $type, $code, $format, $start ) {
		global $wgWldDownloadScriptPath;
		$csv = new WldFormatCSV();

		// the greater the value of $sqlLimit the faster the download file is
		// finished but the slower each web page loads while the job is being
		// processed.
		$sqlLimit = 100;
		$ctrOver = $sqlLimit + 1;

		$options['OFFSET'] = $start;

		// we need an extra line to determine if a new job is needed.
		$options['LIMIT'] = $sqlLimit + 1;

		// Why order by expression_id? To avoid duplication of words
		// and skipping of some. When a language is constantly edited,
		// order by spelling would not accurately get all unique expressions
		// when job queued.
		$options['ORDER BY'] = 'expression_id';

		// language specifics
		$languageId = getLanguageIdForIso639_3( $code );
		$languageExpressions = $this->getLanguageIdExpressions( $languageId, $options );

		// create File name
		$fileName = $wgWldDownloadScriptPath;
		$fileName .= $type . '_' . $code . '.' . $format;

		// When someone updates the file while someone is
		// downloading the file, the file may ( in my mind ),
		// be corrupted. So process it first as a temporary file,
		// delete the original file, and rename the temporary file ~he
		$tempFileName = $wgWldDownloadScriptPath;
		$tempFileName .= "tmp_$type" . "_$code.tmp";

		if ( $start == 1 ) {
			$fh = fopen( $tempFileName, 'w' );
			fwrite( $fh, '"Expression","Definition"' . "\n" );
		} else {
			$fh = fopen( $tempFileName, 'a' );
		}
		$ctr = 0;
		if ( $start != 0 ) {
			foreach( $languageExpressions as $row ) {
				$spelling = $csv->formatCSVcolumn( $row->spelling );
				// create a function to check if the expression has
				// a dm translated, if so write it to the file
				if ( $ctr != $ctrOver ) {
					$defineList = $this->getDefineList( $row->spelling, $languageId );
					foreach ( $defineList as $text ) {
						fwrite( $fh, $spelling . ',' . $csv->formatCSVcolumn( $text ) . "\n" );
					}
					$defineList = array();
				}
				$ctr ++;
			}
		}
		fclose( $fh );

		// If the number of lines processed is $sqlLimit . we can't be sure
		// if it has a complete job or not. It's safer to assume that
		// it is not. ~he

		// incomplete job
		if( $ctr >= $sqlLimit ) {
			$jobParams = array( 'type' => $type, 'langcode' => $code, 'format' => $format );
			$jobParams['start'] = $start + $sqlLimit;
			$jobName = 'User:JobQuery/' . $type . '_' . $code . '.' . $format;
			$title = Title::newFromText( $jobName );
			$job = new CreateDefinedExpressionListJob( $title, $jobParams );
			JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
		} else { // complete job
			if ( file_exists( $fileName ) ) {
				unlink( $fileName );
			}
			rename( $tempFileName, $fileName );
		}

	}

	function getDefineList( $spelling, $languageId ) {
		$dmlist = getExpressionMeaningIds( $spelling );
		// There are duplicates using getExpressionMeaningIds !!!
		$dmlist = array_unique ( $dmlist );
		$express = array();
		foreach ( $dmlist as $definedMeaningId ) {
			$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
			if ( $text ) {
				$express[] = $text;
			}
		}
		$dmlist = array();
		return $express;
	}

	/**
	 * returns an array of "Expression" objects
	 * for a language
	 *
	 * else returns null
	 */
	function getLanguageIdExpressions( $languageId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY']= $options['ORDER BY'];
		} else {
			$cond['ORDER BY']= 'spelling';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT']= $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET']= $options['OFFSET'];
		}

		$queryResult = $dbr->select(
			"{$dc}_expression",
			'spelling',
			array(
				'language_id' => $languageId,
				'remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$expression = array();
		foreach ( $queryResult as $exp ) {
			$expression[] = $exp;
		}

		if ( $expression ) {
			return $expression;
		}
		return null;
	}
}
