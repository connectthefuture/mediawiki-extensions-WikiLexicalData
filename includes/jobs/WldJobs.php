<?php
/**
 * WikiLexicalData Job Class
 *
 */

class WldJobs {

	public function __construct() {
	}

	/**
	 * Check if Download Job Exists
	 * Scans job.job_title like 'JobQuery/nameOfDownloadFileWithExtension'
	 *
	 * @return bool
	 */
	public function downloadJobExist( $jobTitle ) {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );

		$title = $dbr->selectField(
			'job',
			'job_title',
			array(
				'job_title' => $jobTitle
			), __METHOD__
		);

		if ( $title ) {
			return true;
		}
		return false;
	}

}

