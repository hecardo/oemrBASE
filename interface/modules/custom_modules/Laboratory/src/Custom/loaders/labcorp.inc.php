<?php
// -----------------------------------------------------------------------------------------------------------------
// Vendor = LabCorp Laboratories
// -----------------------------------------------------------------------------------------------------------------
if ($form_action == 1) { // load compendium
                         // Get the compendium server parameters
                         // 0: server address
                         // 1: user name
                         // 2: password
                         // 3: filename
	$params = array ();
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv );
		$params [] = trim ( $acsv [0] );
	}
	
	// verify directory
	$server = $params[0];
	$login = $params[1];
	$password = $params[2];
	$file = $params[3];
	if (empty($file)) $file = 'labcorp_compendium.csv';
	
	echo "<br/>LOADING FROM: " . $server . " <br/><br/>";
	
	$cdcdir = $GLOBALS ['temporary_files_dir'] . "/labcorp";
	if (! file_exists ( $cdcdir )) {
		if (! mkdir ( $cdcdir, 0700 )) {
			die ( '<br/><br/>Unable to create directory for CDC files (' . $cdcdir . ')' );
		}
	}
	
	unlink ( $cdcdir . "/" . $file ); // remove old file if there is one
	if (($fp = fopen ( $cdcdir . "/" . $file, "w+" )) == false) {
		die ( '<br/><br/>Could not create local CDC file (' . $cdcdir . "/" . $file . ')' );
	}
	
	$ch = curl_init ();
	$credit = ($login . ':' . $password);
	curl_setopt ( $ch, CURLOPT_URL, $server . "/compendium/" . $file );
	curl_setopt ( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
	curl_setopt ( $ch, CURLOPT_USERPWD, $credit );
	curl_setopt ( $ch, CURLOPT_TIMEOUT, 480 );
	curl_setopt ( $ch, CURLOPT_FILE, $fp );
	
	curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
	curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
	
	curl_setopt ( $ch, CURLOPT_VERBOSE, 1 );
	
	if (($xml = curl_exec ( $ch )) === false) {
		curl_close ( $ch );
		fclose ( $fp );
		unlink ( $cdcdir . "/" . $file );
		die ( "<br/><br/>READ ERROR: " . $server . "/compendium/" . $file . " resulted in error: " . curl_error ( $ch ) . " QUITING..." );
	}
	
	curl_close ( $ch );
	fclose ( $fp );
	
	// verify required files
	if (! file_exists ( $cdcdir . "/" . $file ))
		die ( "<br/><br/>LabCorp compendium file [$file] not accessable!!" );
/* UPDATE ----		
	// Delete the detail records for this lab.
	sqlStatement ( "DELETE FROM procedure_type WHERE lab_id = ? AND (procedure_type = 'det' OR procedure_type = 'res') ", array (
			$lab_id 
	) );
	
	// Mark procedures for the indicated lab as inactive.
	sqlStatement ( "UPDATE procedure_type SET activity = 0, seq = 999999, related_code = '' WHERE lab_id = ? AND procedure_type != 'grp' ", array (
			$lab_id 
	) );
*/
	// Load category group ids (procedure and profile)
	$result = sqlStatement ( "SELECT procedure_type_id, name FROM procedure_type WHERE parent = ? AND procedure_type = 'grp'", array (
			$form_group 
	) );
	while ( $record = sqlFetchArray ( $result ) )
		$groups [$record ['name']] = $record [procedure_type_id];
	if (! $groups ['Profiles'] || ! $groups ['Procedures'])
		die ( "<br/><br/>Missing required compendium groups [Profiles, Procedures]" );
		
	// open the order code file for processing
	$fhcsv = fopen ( $cdcdir . "/" . $file, 'r' );
	if (! $fhcsv) {
		die ( "<br/><br/>LabCorp compendium file [$file] could not be openned!!" );
	}
	
	// What should be uploaded is the Order Compendium spreadsheet provided
	// by LabCorp, saved in "Text CSV" format from OpenOffice, using its
	// default settings. Sort table by Order Code!!!
	//
	// Values for each row are:
	// 0: Line Number
	// 1: Order code : mapped as procedure_code
	// 2: Order Name : mapped as procedure name
	// 3: Orderable
	// 4: Published
	// 5: DOS
	// 6: AOE Segment
	// 7: CPT4 Codes
	// 8: Proc Class
	// 9: Result Code : mapped as discrete result code
	// 10: Result Name : mapped as discrete result name
	// 11: Result UofM
	// 12: Result Type
	// 13: Result LOINC : mapped as identification number
	// 14: Result Proc Class
	// 15: Reflex (65 fields) -- ignored
	// 80: Special Instructions
	// 81: Specimen Type
	// 82: Specimen Volume
	// 83: Minimum Volume
	// 84: Specimen Container
	// 85: Specimen Collection
	// 86: Specimen Storage
	// 87: Testing Frequency
	// 88: Testing Method
	// 89: Volume
	// 90: Profile Flag
	// 91: Changed Date
	
	$lastcode = '';
	$pseq = 1;
	$rseq = 1;
	$dseq = 100;
	
	echo "<pre style='font-size:10px'>";
	
	$groupid = $groups ['Procedures'];
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv );
		
		if (trim ( $acsv [3] ) != 'Y')
			continue; // not orderable
//		if (trim ( $acsv [4] ) != 'P')
//			continue; // not published
		if (trim ( $acsv [90] ) == 'P')
			continue; // not test
				                                      
		// store the order
		$ordercode = trim ( $acsv [1] );
		if (strtolower ( $ordercode ) == "order code")
			continue;
		
		if ($lastcode != $ordercode) { // new code (store only once)
			$stdcode = '';
			if (trim ( $acsv [7] ) != '') {
				$cpts = str_replace ( ' 001', '', trim ( $acsv [7] ) );
				$stdcode = "CPT4:" . str_replace ( ' ', ', ', $cpts );
			}
			
			$trow = sqlQuery ( "SELECT * FROM procedure_type WHERE parent = ? AND procedure_code = ? AND lab_id = ? AND procedure_type = 'ord' ORDER BY procedure_type_id DESC LIMIT 1", array (
					$groupid,
					$ordercode,
					$lab_id 
			) );
			
			$name = trim ( $acsv [2] );
			$zseg = trim ( $acsv [6] );
			$pclass = trim ( $acsv [8] );
			$notes = trim ( $acsv [80] );
			$specimen = trim ( $acsv [81] );
			
			echo "PROCEDURE: $ordercode, $name, $stdcode, $specimen\n";
			flush ();
			
			if (empty ( $trow ['procedure_type_id'] )) {
				$orderid = sqlInsert ( "INSERT INTO procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, standard_code = ?, notes = ?, body_site = ?, transport = ?, procedure_type = ?, seq = ?, activity = 1", array (
						$groupid,
						$name,
						$specimen,
						$lab_id,
						$ordercode,
						$stdcode,
						$notes,
						$pclass,
						$zseg,
						'ord',
						$pseq ++ 
				) );
			} else {
				$orderid = $trow ['procedure_type_id'];
				sqlStatement ( "UPDATE procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, notes = ?, body_site = ?, transport = ?, procedure_type = ?, seq = ?, activity = 1 WHERE procedure_type_id = ?", array (
						$groupid,
						$name,
						$specimen,
						$lab_id,
						$ordercode,
						$notes,
						$pclass,
						$zseg,
						'ord',
						$pseq ++,
						$orderid 
				) );
			}
			
			// store detail records (one record per detail)
			if (trim ( $acsv [85] )) { // specimen collection
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMEN COLLECTION',
						'Preferred specimen collection method',
						$lab_id,
						$ordercode,
						trim ( $acsv [85] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [84] )) { // container
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'CONTAINER TYPE',
						'Specimen container type',
						$lab_id,
						$ordercode,
						trim ( $acsv [84] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [82] )) { // volume
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMUN VOLUME',
						'Specimen volume requirement',
						$lab_id,
						$ordercode,
						trim ( $acsv [82] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [86] )) { // storage
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMEN STORAGE',
						'Method of specimen storage',
						$lab_id,
						$ordercode,
						trim ( $acsv [86] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [88] )) { // method
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'TESTING METHOD',
						'Method of performing test',
						$lab_id,
						$ordercode,
						trim ( $acsv [88] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [87] )) { // frequency
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'TESTING FREQUENCY',
						'How frequently tests a processed',
						$lab_id,
						$ordercode,
						trim ( $acsv [87] ),
						'det',
						$dseq ++ 
				) );
			}
			
			// reset counters for new procedure
			$lastcode = $ordercode;
			$rseq = 1;
			$dseq = 100;
		}
		
		// store the results
		$stdcode = '';
		$resultcode = trim ( $acsv [9] );
		if ($resultcode) {
			$stdcode = "LabCorp: " . $resultcode;

			$loinc = trim ( $acsv[13] );
			if ($loinc && $loinc != 'UNLOINC') $resultcode = $loinc; 
		
			$name = trim ( $acsv [10] );
			$units = trim ( $acsv [11] );
			$range = ''; // not available from LABCORP
		
			echo "RESULT: $resultcode, $name, $stdcode, $units\n";
			flush ();
		
			sqlStatement ( "INSERT INTO procedure_type SET parent = ?, name = ?, lab_id = ?, procedure_code = ?, standard_code = ?, units = ?, `range` = ?, seq = ?, procedure_type = ?, activity = 1 ", array (
					$orderid,
					$name,
					$lab_id,
					$resultcode,
					$stdcode,
					$units,
					$range,
					$rseq ++,
					'res' 
			) );
		}
	}
	echo "</pre>";
} 

else if ($form_action == 2) { // load questions
                              // Get the compendium server parameters
                              // 0: server address
                              // 1: user name
                              // 2: password
	$params = array ();
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv );
		$params [] = trim ( $acsv [0] );
	}
	
	// verify directory
	$server = $params [0];
	$login = $params [1];
	$password = $params [2];
	
	$cdcdir = $GLOBALS ['temporary_files_dir'] . "/labcorp";
	if (! file_exists ( $cdcdir )) {
		if (! mkdir ( $cdcdir, 0700 )) {
			die ( '<br/><br/>Unable to create directory for CDC files (' . $cdcdir . ')' );
		}
	}
	
	$file = 'labcorp_questions.csv';
	
	// if a local file exists then use it
	if (! file_exists ( $cdcdir . "/" . $file )) {
		if (($fp = fopen ( $cdcdir . "/" . $file, "w+" )) == false) {
			die ( '<br/><br/>Could not create local CDC file (' . $cdcdir . "/" . $file . ')' );
		}
		
		echo "<br/>LOADING FROM: " . $server . "<br/><br/>";
		
		$ch = curl_init ();
		$credit = ($login . ':' . $password);
		curl_setopt ( $ch, CURLOPT_URL, $server . "/compendium/" . $file );
		curl_setopt ( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt ( $ch, CURLOPT_USERPWD, $credit );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, 90 );
		curl_setopt ( $ch, CURLOPT_FILE, $fp );
		
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		
		curl_setopt ( $ch, CURLOPT_VERBOSE, 1 );
		
		if (($xml = curl_exec ( $ch )) === false) {
			curl_close ( $ch );
			fclose ( $fp );
			unlink ( $cdcdir . "/" . $file );
			die ( "<br/><br/>READ ERROR: " . $server . "/compendium/" . $file . " resulted in error: " . curl_error ( $ch ) . " QUITING..." );
		}
		
		curl_close ( $ch );
		fclose ( $fp );
	} else {
		echo "<br/>LOADING FROM: " . $cdcdir . "/" . $file . "<br/><br/>";
	}
	
	// open the order code file for processing
	$fhcsv = fopen ( $cdcdir . "/" . $file, 'r' );
	if (! $fhcsv) {
		die ( "<br/><br/>LabCorp compendium file [$file] could not be openned!!" );
	}
	
	// Mark the vendor's current questions inactive.
	sqlStatement ( "UPDATE procedure_questions SET activity = 0 WHERE lab_id = ?", array (
			$lab_id 
	) );
	
	// LabCorp does their questions by order type, not individual test so there
	// is a separate labcorp_aoe table with the questions. This table is used
	// to map procedure codes to question types.
	// 0: Field Code
	// 1: Question Segment (Zseg)
	// 2: Section
	// 3: Sequence
	// 4: Active
	// 5: Question Text
	// 6: Field Type ('Text', 'List', 'Date', 'Mask')
	// 7: Options (list name or mask)
	// 8: Max Size
	// 9: Tips
	//
	$seq = 1;
	
	echo "<pre style='font-size:10px'>";
	
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv );
		if (strtolower ( $acsv [1] ) == "field")
			continue; // header
		if (strtolower ( $acsv [4] ) != "y")
			continue; // not active
		
		$field = trim ( $acsv [0] );
		$zseg = trim ( $acsv [1] );
		$section = trim ( $acsv [2] );
		$seq = trim ( $acsv [3] );
		$question = trim ( $acsv [5] );
		$fldtype = trim ( $acsv [6] );
		$options = trim ( $acsv [7] );
		$maxsize = trim ( $acsv [8] );
		$tips = trim ( $acsv [9] );
		
		$required = 1; // always required
		
		if (empty ( $field ) || empty ( $zseg ))
			continue;
			
			// look for existing record
		$qrow = sqlQuery ( "SELECT * FROM procedure_questions WHERE lab_id = ? AND procedure_code = ? AND question_code = ?", array (
				$lab_id,
				$field,
				$zseg 
		) );
		
		echo "QUESTION: $zseg, $field, $seq, $question\n";
		
		$activity = 1; // assume AOE required (except for standard fields below)
		if ($field == 'ZCI4' || $field == 'ZCI3.1' || $field == 'OBR15')
			$activity = 0;
			
			// create or update the record
		sqlStatement ( "REPLACE INTO procedure_questions SET lab_id = ?, procedure_code = ?, question_code = ?, seq = ?, question_text = ?, fldtype = ?, required = ?, options = ?, tips = ?, maxsize = ?, section = ?, activity = ?", array (
				$lab_id,
				$zseg,
				$field,
				$seq,
				$question,
				$fldtype,
				$required,
				$options,
				$tips,
				$maxsize,
				$section,
				$activity 
		) );
	} // end while
	
	echo "</pre>";
} // end load questions

if ($form_action == 4) { // load profiles
                         // Get the compendium server parameters
                         // 0: server address
                         // 1: user name
                         // 2: password
	$params = array ();
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv );
		$params [] = trim ( $acsv [0] );
	}
	
	// verify directory
	$server = $params [0];
	$login = $params [1];
	$password = $params [2];
	$file = $params[3];
	if (empty($file)) $file = 'labcorp_compendium.csv';
	
	$cdcdir = $GLOBALS ['temporary_files_dir'] . "/labcorp";
	if (! file_exists ( $cdcdir )) {
		if (! mkdir ( $cdcdir, 0700 )) {
			die ( '<br/><br/>Unable to create directory for CDC files (' . $cdcdir . ')' );
		}
	}
	
	
	// if a local file exists then use it
	if (! file_exists ( $cdcdir . "/" . $file )) {
		if (($fp = fopen ( $cdcdir . "/" . $file, "w+" )) == false) {
			die ( '<br/><br/>Could not create local CDC file (' . $cdcdir . "/" . $file . ')' );
		}
		
		echo "<br/>LOADING FROM: " . $server . "<br/><br/>";
		
		$ch = curl_init ();
		$credit = ($login . ':' . $password);
		curl_setopt ( $ch, CURLOPT_URL, $server . "/compendium/" . $file );
		curl_setopt ( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt ( $ch, CURLOPT_USERPWD, $credit );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, 90 );
		curl_setopt ( $ch, CURLOPT_FILE, $fp );
		
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		
		curl_setopt ( $ch, CURLOPT_VERBOSE, 1 );
		
		if (($xml = curl_exec ( $ch )) === false) {
			curl_close ( $ch );
			fclose ( $fp );
			unlink ( $cdcdir . "/" . $file );
			die ( "<br/><br/>READ ERROR: " . $server . "/compendium/" . $file . " resulted in error: " . curl_error ( $ch ) . " QUITING..." );
		}
		
		curl_close ( $ch );
		fclose ( $fp );
	} else {
		echo "<br/>LOADING FROM: " . $cdcdir . "/" . $file . "<br/><br/>";
	}
	
	// Load category group ids (procedure and profile)
	$result = sqlStatement ( "SELECT procedure_type_id, name FROM procedure_type WHERE parent = ? AND procedure_type = 'grp'", array (
			$form_group 
	) );
	while ( $record = sqlFetchArray ( $result ) )
		$groups [$record ['name']] = $record [procedure_type_id];
	if (! $groups ['Profiles'] || ! $groups ['Procedures'])
		die ( "<br/><br/>Missing required compendium groups [Profiles, Procedures]" );
		
		// open the order code file for processing
	$fhcsv = fopen ( $cdcdir . "/" . $file, 'r' );
	if (! $fhcsv) {
		die ( "<br/><br/>LabCorp compendium file [$file] could not be openned!!" );
	}
	
	$orderid = '';
	$lastcode = '';
	$pseq = 1;
	$rseq = 1;
	$dseq = 100;
	$components = array ();
	
	echo "<pre style='font-size:10px'>";
	
	$groupid = $groups ['Profiles'];
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv );
		
		if (trim ( $acsv [3] ) != 'Y')
			continue; // not orderable
//		if (trim ( $acsv [4] ) != 'P')
//			continue; // not published
		if (trim ( $acsv [90] ) != 'P')
			continue; // no tests
				                                      
		// store the order
		$ordercode = trim ( $acsv [1] );
		if (strtolower ( $ordercode ) == "order code")
			continue;
			
			// store the data
		$ordercode = trim ( $acsv [1] );
		if (empty ( $ordercode ))
			continue;
		
		if ($lastcode != $ordercode) { // new code (store only once)
			if ($lastcode && $components) {
				// store componets for previous record
				$trow = sqlQuery ( "SELECT procedure_type_id FROM procedure_type WHERE parent = ? AND procedure_code = ? AND lab_id = ? AND procedure_type = 'pro' ORDER BY procedure_type_id DESC LIMIT 1", array (
						$groupid,
						$lastcode,
						$lab_id 
				) );
				
				if (! empty ( $trow ['procedure_type_id'] )) {
					$comp_list = implode ( "^", $components );
					sqlInsert ( "UPDATE procedure_type SET related_code = ? WHERE procedure_type_id = ?", array (
							$comp_list,
							$trow ['procedure_type_id'] 
					) );
				}
			}
			
			// clear new components
			$components = array ();
			
			// store profile record
			$stdcode = '';
			if (trim ( $acsv [7] ) != '') {
				$cpts = str_replace ( ' 001', '', trim ( $acsv [7] ) );
				$stdcode = "CPT4:" . str_replace ( ' ', ', ', $cpts );
			}
			
			$trow = sqlQuery ( "SELECT * FROM procedure_type WHERE parent = ? AND procedure_code = ? AND lab_id = ? AND procedure_type = 'ord' ORDER BY procedure_type_id DESC LIMIT 1", array (
					$groupid,
					$ordercode,
					$lab_id 
			) );
			
			$name = trim ( $acsv [2] );
			$notes = trim ( $acsv [80] );
			$specimen = trim ( $acsv [81] );
			$name = trim ( $acsv [2] );
			$zseg = trim ( $acsv [6] );
			$pclass = trim ( $acsv [8] );
			$notes = trim ( $acsv [80] );
			$specimen = trim ( $acsv [81] );
			
			echo "PROFILE: $ordercode, $name, $stdcode, $specimen\n";
			flush ();
			
			if (empty ( $trow ['procedure_type_id'] )) {
				$orderid = sqlInsert ( "INSERT INTO procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, standard_code = ?, notes = ?, body_site = ?, transport = ?, procedure_type = ?, seq = ?, activity = 1", array (
						$groupid,
						$name,
						$specimen,
						$lab_id,
						$ordercode,
						$stdcode,
						$notes,
						$pclass,
						$zseg,
						'pro',
						$pseq ++ 
				) );
			} else {
				$orderid = $trow ['procedure_type_id'];
				sqlStatement ( "UPDATE procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, notes = ?, body_site = ?, transport = ?, procedure_type = ?, seq = ?, activity = 1 WHERE procedure_type_id = ?", array (
						$groupid,
						$name,
						$specimen,
						$lab_id,
						$ordercode,
						$notes,
						$pclass,
						$zseg,
						'pro',
						$pseq ++,
						$orderid 
				) );
			}
			
			// store detail records (one record per detail)
			if (trim ( $acsv [85] )) { // specimen collection
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMEN COLLECTION',
						'Preferred specimen collection method',
						$lab_id,
						$ordercode,
						trim ( $acsv [85] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [84] )) { // container
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'CONTAINER TYPE',
						'Specimen container type',
						$lab_id,
						$ordercode,
						trim ( $acsv [84] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [82] )) { // volume
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMUN VOLUME',
						'Specimen volume requirement',
						$lab_id,
						$ordercode,
						trim ( $acsv [82] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [86] )) { // storage
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMEN STORAGE',
						'Method of specimen storage',
						$lab_id,
						$ordercode,
						trim ( $acsv [86] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [88] )) { // method
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'TESTING METHOD',
						'Method of performing test',
						$lab_id,
						$ordercode,
						trim ( $acsv [88] ),
						'det',
						$dseq ++ 
				) );
			}
			
			if (trim ( $acsv [87] )) { // frequency
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'TESTING FREQUENCY',
						'How frequently tests a processed',
						$lab_id,
						$ordercode,
						trim ( $acsv [87] ),
						'det',
						$dseq ++ 
				) );
			}
			
			// reset counters for new procedure
			$lastcode = $ordercode;
			$rseq = 1;
			$dseq = 100;
		}
		
		// collect the comopnents
		$comp = trim ( $acsv [9] );
		$components [$comp] = $comp;
		$lastcode = $ordercode;
		
		echo "COMPONENT: $comp, $acsv[10] \n";
		flush ();

		// store the results
		$stdcode = '';
		$resultcode = trim ( $acsv [9] );
		if ( $resultcode ) {
			$stdcode = "LabCorp: " . $resultcode;

			$loinc = trim ( $acsv[13] );
			if ($loinc && $loinc != 'UNLOINC') $resultcode = $loinc; 
		
			$name = trim ( $acsv [10] );
			$units = trim ( $acsv [11] );
			$range = ''; // not available from LABCORP
		
			echo "RESULT: $resultcode, $name, $stdcode, $units\n";
			flush ();
		
			sqlStatement ( "INSERT INTO procedure_type SET parent = ?, name = ?, lab_id = ?, procedure_code = ?, standard_code = ?, units = ?, `range` = ?, seq = ?, procedure_type = ?, activity = 1 ", array (
					$orderid,
					$name,
					$lab_id,
					$resultcode,
					$stdcode,
					$units,
					$range,
					$rseq ++,
					'res' 
			) );
		}
	}
	
	// process last profile code
	if ($components) {
		$trow = sqlQuery ( "SELECT * FROM procedure_type WHERE parent = ? AND procedure_code = ? AND lab_id = ? AND procedure_type = 'pro' ORDER BY procedure_type_id DESC LIMIT 1", array (
				$groupid,
				$lastcode,
				$lab_id 
		) );
		
		if (! empty ( $trow ['procedure_type_id'] )) {
			$comp_list = implode ( "^", $components );
			sqlInsert ( "UPDATE procedure_type SET related_code = ? WHERE procedure_type_id = ?", array (
					$comp_list,
					$trow ['procedure_type_id'] 
			) );
			$components = array ();
		}
	}
	
	echo "</pre>";
}



