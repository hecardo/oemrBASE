<?php
// -----------------------------------------------------------------------------------------------------------------
// Vendor = Clinical Pathology Laboratories
// -----------------------------------------------------------------------------------------------------------------
if ($form_action == 1) { // load compendium
    // Delete the detail records for this lab.
	sqlStatement ( "DELETE FROM procedure_type WHERE lab_id = ? AND (procedure_type = 'det' OR procedure_type = 'res') ", array (
			$lab_id 
	) );
	
	// Mark everything for the indicated lab as inactive.
	sqlStatement ( "UPDATE procedure_type SET activity = 0, seq = 999999 WHERE lab_id = ? AND procedure_type != 'grp' AND procedure_type != 'pro'", array (
			$lab_id 
	) );
	
	// Load category group ids
	$result = sqlStatement ( "SELECT procedure_type_id, name FROM procedure_type WHERE lab_id = ? AND parent = ? AND procedure_type = 'grp'", array (
			$lab_id,
			$form_group 
	) );
	$groups = array();
	while ( $record = sqlFetchArray ( $result ) )
		$groups[$record ['name']] = $record[procedure_type_id];
		
	// What should be uploaded is the Order Compendium spreadsheet provided
	// by CPL, saved in "Text CSV" format from OpenOffice, using its
	// default settings. Sort table by Order Code!!!
	// Values for each row are:
	// 0: Order code : mapped as procedure_code
	// 1: Order Name : mapped as procedure name
	// 2: Result Code : mapped as discrete result code
	// 3: Result Name : mapped as discrete result name
	// 4: Result LOINC : mapped as identification number
	// 5: Result CPT4 : ignored
	// 6: Flags? (Container type)
	// 7: Category (Preferred Specimen)
	// 8: Preferred Specimen (Transport)
	// 9: Container Type (Category : mapped as group)
	// 10: TransTemp (UofM)
	// 11: UofM (Reference Range)
	// 12: Referance Range (Method)
	// 13: Method
	
	$lastcode = '';
	$pseq = 1;
	$rseq = 1;
	$dseq = 100;
	
	echo "<pre style='font-size:10px'>";
	
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv, 4096 );
		if (!$acsv) break;
		
		$category = trim ( $acsv [7] );
		if (! $category || $category == 'Category') {
			$groupid = $form_group; // no category, store under root
		} else { // find or add category
			$groupid = $groups [$category];
			if (! $groupid) {
				$groupid = sqlInsert ( "INSERT INTO procedure_type SET procedure_type = 'grp', lab_id = ?, parent = ?, name = ?", array (
						$lab_id,
						$form_group,
						$category 
				) );
				$groups [$category] = $groupid;
			}
		}
		
		// store the order
		$ordercode = trim ( $acsv [0] );
		if (count ( $acsv ) < 2 || strtolower ( $ordercode ) == "order code")
			continue;
		
		if ($lastcode != $ordercode) { // new code (store only once)
			$stdcode = '';
			if (trim ( $acsv [5] ) != '')
				$stdcode .= "CPT4:" . trim ( $acsv [5] );
			
			$trow = sqlQuery ( "SELECT * FROM procedure_type WHERE parent = ? AND procedure_code = ? AND procedure_type = 'ord' ORDER BY procedure_type_id DESC LIMIT 1", array (
					$groupid,
					$ordercode 
			) );
			
			$name = trim ( $acsv [1] );
			$category = trim ( $acsv [7] );
			
			// display last profile
			echo "TEST: $ordercode - $name ($category)\n";
			flush ();
			
			if (empty ( $trow ['procedure_type_id'] )) {
				$orderid = sqlInsert ( "INSERT INTO procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, standard_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1", array (
						$groupid,
						$name,
						$category,
						$lab_id,
						$ordercode,
						$stdcode,
						$notes,
						'ord',
						$pseq ++ 
				) );
			} else {
				$orderid = $trow ['procedure_type_id'];
				sqlStatement ( "UPDATE procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 WHERE procedure_type_id = ?", array (
						$groupid,
						$name,
						$category,
						$lab_id,
						$ordercode,
						$notes,
						'ord',
						$pseq ++,
						$orderid 
				) );
			}
			
			// store detail records (one record per detail)
			if (trim ( $acsv [8] )) { // preferred specimen
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'PREFERRED SPECIMEN',
						'Preferred specimen collection method',
						$lab_id,
						$ordercode,
						trim ( $acsv [8] ),
						'det',
						$dseq ++
				) );
			}
			
			if (trim ( $acsv [9] )) { // container
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'CONTAINER TYPE',
						'Specimen container type',
						$lab_id,
						$ordercode,
						trim ( $acsv [9] ),
						'det',
						$dseq ++
				) );
			}
			
			if (trim ( $acsv [10] )) { // container
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMEN TRANSPORT',
						'Method of specimen transport',
						$lab_id,
						$ordercode,
						trim ( $acsv [10] ),
						'det',
						$dseq ++
				) );
			}
			
			if (trim ( $acsv [13] )) { // container
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'TESTING METHOD',
						'Method of performing test',
						$lab_id,
						$ordercode,
						trim ( $acsv [13] ),
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
		$resultcode = trim ( $acsv [2] );
		$trow = sqlQuery ( "SELECT * FROM procedure_type WHERE parent = ? AND procedure_code = ? AND procedure_type = 'res' ORDER BY procedure_type_id DESC LIMIT 1", array (
				$orderid,
				$resultcode 
		) );
		
		$stdcode = '';
		if (trim ( $acsv [4] ) != '')
			$stdcode .= "LOINC:" . trim ( $acsv [4] );
		$name = trim ( $acsv [3] );
		$units = trim ( $acsv [11] );
		$range = trim ( $acsv [12] );
		
		echo "RESULT: $resultcode, $name, $stdcode\n";
		flush ();
		
		if (empty ( $trow ['procedure_type_id'] )) {
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
		} else {
			sqlStatement ( "UPDATE procedure_type SET parent = ?, name = ?, lab_id = ?, procedure_code = ?, standard_code = ?, units = ?, `range` = ?, seq = ?, procedure_type = ?, activity = 1 WHERE procedure_type_id = ?", array (
					$groupid,
					$name,
					$lab_id,
					$resultcode,
					$stdcode,
					$units,
					$range,
					$rseq ++,
					'res',
					$trow ['procedure_type_id'] 
			) );
		}
	} // end file loop
	echo "</pre>\n";
} 

else if ($form_action == 2) { // load questions
                              // Mark the vendor's current questions inactive.
	sqlStatement ( "UPDATE procedure_questions SET activity = 0 WHERE lab_id = ?", array (
			$lab_id 
	) );
	
	// What should be uploaded is the "AOE Questions" spreadsheet provided
	// by CPL, saved in "Text CSV" format from OpenOffice, using its
	// default settings. Values for each row are:
	// 0: Order Code
	// 1: Question Code
	// 2: Question
	// 3: Is Required (always "false")
	// 4: Field Type ("Free Text", "Pre-Defined Text" or "Drop Down";
	// "Drop Down" was previously "Multiselect Pre-Defined Text" and
	// indicates that more than one choice is allowed)
	// 5: Response (just one; the row is duplicated for each possible value)
	//
	
	echo "<pre style='font-size:10px'>";
	
	$seq = 1;
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv, 4096 );
		if ($seq ++ < 2 || strtolower ( $acsv [0] ) == "order code")
			continue;
		
		$pcode = trim ( $acsv [0] );
		$qcode = trim ( $acsv [1] );
		if (empty ( $pcode ) || empty ( $qcode )) continue;
		
		$required = 1; // always required
		$options = trim ( $acsv [3] );
		$measure = trim ( $acsv [4] );
		if ($measure) $options .= " ".$measure;
			
		// Figure out field type.
//		$fldtype = trim ( $acsv [3] );
//		if (! $fldtype)
		$fldtype = 'T'; // always text
				                               
		// display question
		echo "QUESTION: $qcode - trim($acsv[2])\n";
		flush ();
		
		$qrow = sqlQuery ( "SELECT * FROM procedure_questions WHERE lab_id = ? AND procedure_code = ? AND question_code = ?", array (
				$lab_id,
				$pcode,
				$qcode 
		) );
		
		if (empty ( $qrow ['procedure_code'] )) {
			sqlStatement ( "INSERT INTO procedure_questions SET lab_id = ?, procedure_code = ?, question_code = ?, question_text = ?, fldtype = ?, required = ?, options = ?, activity = 1", array (
					$lab_id,
					$pcode,
					$qcode,
					trim ( $acsv [2] ),
					$fldtype,
					$required,
					$options 
			) );
		} else {
			if ($qrow ['activity'] == '1' && $qrow ['options'] !== '' && $options !== '') {
				$options = $qrow ['options'] . ';' . $options;
			}
			sqlStatement ( "UPDATE procedure_questions SET question_text = ?, fldtype = ?, required = ?, options = ?, activity = 1 WHERE lab_id = ? AND procedure_code = ? AND question_code = ?", array (
					trim ( $acsv [2] ),
					$fldtype,
					$required,
					$options,
					$lab_id,
					$pcode,
					$qcode 
			) );
		}
	} // end while
	echo "</pre>\n";
} // end load questions

if ($form_action == 4) { // load profiles
	die ("CPL DOES NOT PROVIDE PROFILE INFORMATION");
	
                         // Mark everything for the indicated lab as inactive.
	sqlStatement ( "UPDATE procedure_type SET activity = 0, seq = 999999 WHERE lab_id = ? AND procedure_type = 'pro'", array (
			$lab_id 
	) );
	
	// Load category group ids
	$result = sqlStatement ( "SELECT procedure_type_id, name FROM procedure_type WHERE lab_id = ? AND parent = ? AND procedure_type = 'grp'", array (
			$lab_id,
			$form_group 
	) );
	while ( $record = sqlFetchArray ( $result ) )
		$groups [$record ['name']] = $record ['procedure_type_id'];
		
		// What should be uploaded is the Profile Compendium spreadsheet provided
		// by CPL, saved in "Text CSV" format from OpenOffice, using its
		// default settings. Sort table by Profile Code!!!
		// Values for each row are:
		// 0: Profile code : mapped as procedure_code
		// 1: Profile Name : mapped as procedure name
		// 2: Component Code : mapped as component order code
		// 3: Component Name : mapped as component order name
		// 4: Test Code
		// 5: Test Name
		// 6: Result LOINC : ignored
		// 7: Result CPT4 : ignored
		// 8: Category : mapped as group
		// 9: Preferred Specimen
		// 10: Container type
		// 11: Transport
		// 12: UofM : ignored
		// 13: Reference Range : ignored
		// 14: Method : ignored
	
	$orderid = '';
	$lastcode = '';
	$pseq = 1;
	$rseq = 1;
	$dseq = 100;
	$components = array ();
	
	echo "<pre style='font-size:10px'>";
	
	while ( ! feof ( $fhcsv ) ) {
		$acsv = fgetcsv ( $fhcsv, 4096 );
		
		$category = trim ( $acsv [8] );
		if (! $category || $category == 'Category') {
			$groupid = $form_group; // no category, store under root
		} else { // find or add category
			$groupid = $groups [$category];
			if (! $groupid) {
				$groupid = sqlInsert ( "INSERT INTO procedure_type SET procedure_type = 'grp', lab_id = ?, parent = ?, name = ?", array (
						$lab_id,
						$form_group,
						$category 
				) );
				$groups [$category] = $groupid;
			}
		}
		
		// store the profile
		$ordercode = trim ( $acsv [0] );
		if (count ( $acsv ) < 2 || strtolower ( $ordercode ) == "profile code")
			continue;
		
		if ($lastcode != $ordercode) { // new code (store only once)
		                               
			// store componets for previous record
			if ($orderid) {
				$comp_list = implode ( "^", $components );
				$comp_list = $comp_list;
				sqlInsert ( "UPDATE procedure_type SET related_code = ? WHERE procedure_type_id = ?", array (
						$comp_list,
						$orderid 
				) );
				$components = array ();
			}
			$trow = sqlQuery ( "SELECT * FROM procedure_type WHERE parent = ? AND procedure_code = ? AND procedure_type = 'pro' ORDER BY procedure_type_id DESC LIMIT 1", array (
					$groupid,
					$ordercode 
			) );
			
			$name = trim ( $acsv [1] );
			$notes = trim ( $acsv [9] );
			$category = trim ( $acsv [8] );
			
			// display last profile
			echo "PROFILE: $ordercode - $name ($category)\n";
			flush ();
			
			if (empty ( $trow ['procedure_type_id'] )) {
				// create new record
				$orderid = sqlInsert ( "INSERT INTO procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, notes = ?, related_code = ?, procedure_type = ?, seq = ?, activity = 1", array (
						$groupid,
						$name,
						$category,
						$lab_id,
						$ordercode,
						$notes,
						'',
						'pro',
						$pseq ++ 
				) );
			} else {
				$orderid = $trow ['procedure_type_id'];
				
				// delete detail and other records
				sqlStatement ( "DELETE FROM procedure_type WHERE parent = ?", array (
						$orderid 
				) );
				
				// update profile record
				sqlStatement ( "UPDATE procedure_type SET parent = ?, name = ?, specimen = ?, lab_id = ?, procedure_code = ?, notes = ?, related_code = ?, procedure_type = ?, seq = ?, activity = 1 WHERE procedure_type_id = ?", array (
						$groupid,
						$name,
						$category,
						$lab_id,
						$ordercode,
						$notes,
						'',
						'pro',
						$pseq ++,
						$orderid 
				) );
			}
			
			// store detail records (one record per detail)
			if (trim ( $acsv [9] )) { // preferred specimen
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'PREFERRED SPECIMEN',
						'Preferred specimen collection method',
						$lab_id,
						$ordercode,
						trim ( $acsv [9] ),
						'det',
						$dseq ++,
						$orderid 
				) );
			}
			
			if (trim ( $acsv [10] )) { // container
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'CONTAINER TYPE',
						'Specimen container type',
						$lab_id,
						$ordercode,
						trim ( $acsv [10] ),
						'det',
						$dseq ++,
						$orderid 
				) );
			}
			
			if (trim ( $acsv [11] )) { // transport
				sqlStatement ( "REPLACE INTO procedure_type SET parent = ?, name = ?, description = ?, lab_id = ?, procedure_code = ?, notes = ?, procedure_type = ?, seq = ?, activity = 1 ", array (
						$orderid,
						'SPECIMEN TRANSPORT',
						'Method of specimen transport',
						$lab_id,
						$ordercode,
						trim ( $acsv [11] ),
						'det',
						$dseq ++,
						$orderid 
				) );
			}
			
			// reset counters for new procedure
			$rseq = 1;
			$dseq = 100;
		}
		
		// collect the components
		$comp = trim ( $acsv [2] );
		$components [$comp] = $comp;
		$lastcode = $ordercode;
	}
	
	// process last profile code
	if ($orderid) { // previous record
		$comp_list = implode ( "^", $components );
		$comp_list = $comp_list;
		sqlInsert ( "UPDATE procedure_type SET related_code = ? WHERE procedure_type_id = ?", array (
				$comp_list,
				$orderid 
		) );
		$components = array ();
	}
}
echo "</pre>";

