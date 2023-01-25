<?php
include_once($GLOBALS['srcdir'] . '/billing.inc');
include_once($GLOBALS['srcdir'] . '/wmt-v2/billing_tools.inc');
include_once ($GLOBALS['srcdir'] . '/../custom/code_types.inc.php');
$mrg_codes = array();
$res = sqlStatement('SELECT * FROM `wmt_result_mrg_map` WHERE 1');
while($row = sqlFetchArray($res)) {
    $mrg_codes[] = $row;
}

$mrg_lab_type = array();
$res = sqlStatement('SELECT `ppid`, `type` FROM `procedure_providers` WHERE 1');
while($row = sqlFetchArray($res)) {
    $mrg_lab_type[$row['ppid']] = $row{'type'};
}

$mrg_a1c_codes = array("4548-4", "48521");

function test_lab_rule($pid, $date='', $result='', $code, $type='', $lab=0, $nt ='', $order_id='', $encounter = '',  $mode = '') {
    error_log("Test Lab Rule Called ($pid) [$date] <$result> '$code' ($type) [$lab] '$nt' <$order_id> ($encounter) [$mode]");
	if(!$pid || !$code || !$date) return FALSE;
	if($date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
		error_log("Got Zero Date ($pid) [$date] ($code : $type)");
		return FALSE;
	}
	global $mrg_codes, $mrg_lab_type, $mrg_a1c_codes;

	foreach($mrg_codes as $item) {
	    if($item['type'] != $type) continue;
	    if($item['code'] != $code) continue;
	    if($lab && ($item['lab_id'] != $lab)) continue;
	    error_log("Processing Begin For ($date) [$result]");
		$target_table = attr($item['target_table']);
		$target_cat_field = attr($item['target_cat_field']);
		$target_cat_field_value = attr($item['target_cat_field_value']);
		$target_lbl_field = attr($item['target_lbl_field']);
		$target_lbl_field_value = attr($item['target_lbl_field_value']);
		$target_dt_field = attr($item['target_dt_field']);
		$target_val_field = attr($item['target_val_field']);
		$target_nt_field = attr($item['target_nt_field']);
		$target_order_id_field = attr($item['target_order_id_field']);
		$target_flags = explode('|', $item['misc']);
		$sort_order_dt_field = attr($item['sort_order_dt_field']);
		
		if(in_array('strtoupper', $target_flags)) $result = strtoupper($result);
		if(in_array('strtolower', $target_flags)) $result = strtolower($result);
		
		if($mode == 'order_update' && !$item['if_result_is']) continue;

		// LOCATE THE MOST RECENT ENTRY REGARDING THIS MERGE ITEM
		$flds = sqlListFields($target_table);
		$store_date = $date;
		$sort_order_date = $date;
		if(strtolower($item['target_dt_type']) == 'datetime' && strlen($date) == 10)  
		    $store_date = $date . ' 00:00:00';
		if(strtolower($item['target_dt_type']) == 'date') $store_date = substr($date,0,10);
		if(strtolower($item['sort_order_dt_type']) == 'datetime' && strlen($date) == 10)
		    $sort_order_date = $date . ' 23:59:59';
		if(strtolower($item['sort_order_dt_type']) == 'date') $sort_order_date = substr($date,0,10);
		    
		$sql = "SELECT * FROM `$target_table` WHERE `pid` = ? ";
		$binds = array($pid);
		if($item['target_lbl_field']) {
			$sql .= "AND `$target_lbl_field` = ? ";
			$binds[] = $target_lbl_field_value;
		}
		if(in_array('activity', $flds)) $sql .= "AND `activity` > 0 ";
		$order_by_date = 'date';
		if($target_dt_field) $order_by_date = $target_dt_field;
		if($sort_order_dt_field) $order_by_date = $sort_order_dt_field;
		
		$sql .= "AND `$order_by_date` <= ? ";
		$binds[] = $sort_order_date;
		
		$sql .= "ORDER BY `$order_by_date` DESC";
		$target = sqlQuery($sql, $binds);
		if(!isset($target['id'])) $target['id'] = '';
		
		// NOW IF THERE IS AN ORDER ID - SEE IF WE NEED TO RELEASE TO THE PORTAL
		// OR SOME OTHER FUNCTION SIMILAR TO RELEASING!
		if($order_id) {
            // FOR A1C RESULTS WE NEED TO GET THE ENCOUNTER AND DROP THE APPROPRIATE BILLING LINE
		    if(in_array($code, $mrg_a1c_codes) && ($encounter != '') && ($encounter != 0)) {
		        $gt_flag = $lt_flag = FALSE;
		        if(substr($result,0,1) == '>') $gt_flag = TRUE;
		        if(substr($result,0,1) == '<') $lt_flag = TRUE;
		        $result_val = preg_replace('/[^0-9.]/', '', $result);
		        // echo "For Result ($code) LT [$lt_flag] GT [$gt_flag] Inital ($result) and Final Value ($result_val)<br>";
		        if($result_val != 0 && $result_val != '') {
		              $code_type = 'CPT4';
		            if($result_val > 9) {
		                $bill_code = "3046F";
		            } else if($result_val < 7) {
		                $bill_code = "3044F";
		            } else {
		                $bill_code = "3045F";
		            }
		            $desc = lookup_code_descriptions($code_type . ':' . $bill_code);
		            if(!billingExists($code_type, $bill_code, $pid, $encounter))
		                 addBilling($encounter, $code_type, $bill_code, $desc, $pid, 1, 0);
		        }
		    }

		    /* 
		    $type = 'laboratory';
		    if($lab) $type = strtolower($mrg_lab_type[$lab]);
		    $order_table = 'form_' . $type;
	        if($item['related_table'] == '__LABORATORY__') $item['related_table'] = $order_table;
	        if($item['related_table'] && $item['related_column']) {
	           echo " -> Releasing to portal ($pid,$order_id - $result)\n";
                      $portal = sqlQuery('SELECT `pid`, `allow_patient_portal` FROM `patient_data` WHERE `pid` = ?', array($pid));
                      if(strtoupper($portal{'allow_patient_portal'}) == 'YES') {
		          $sql = 'UPDATE `' . attr($item['related_table']) . '` SET `' . attr($item['related_column']) . '` = ? WHERE `procedure_order_id` = ?';
		          sqlStatement($sql, array($item['related_value'], $order_id));
                      }
		    }
		 */
		}
		if($mode == 'order_update' || $mode == 'release') continue;
		
		// THIS WAS BASICALLY A ONE TIME USE TO UPDATE THE LINK BACK TO AN ORDER ID
		if($mode == 'order_update' && $target['id']) {
		    $sql = "UPDATE `$target_table` SET `$target_order_id_field` = ? WHERE `id` = ?";
		    $binds = array($order_id, $target['id']);
		    sqlStatement($sql, $binds);
		}
		if($mode == 'order_update') continue;
		

		error_log("Target Order Date (" . $target[$order_by_date] . ") And Store Date [$store_date]");
		if($target[$order_by_date] < $store_date) {
            unset($binds);		    
			// THESE REQUIRE A NEW RECORD TO BE ADDED
			// 'target_add_new' OF '2' MEANS DON'T ADD A NEW RECORD, ONLY MATCH TO AN EXISTING 
			if($item['target_add_new'] == 1 || (!$target['id'] && ($item['target_add_new'] != 2))) {
				$sql = "INSERT INTO `$target_table` (pid, `$target_dt_field`";
				$prm = "VALUES (?, ?";
				$binds = array($pid, $store_date);
				if($target_cat_field && $target_cat_field_value) {
					$sql .= ", `$target_cat_field`";
					$prm .= ", ?";
					$binds[] = $target_cat_field_value;
				}
				if($target_lbl_field) {
					$sql .= ", `$target_lbl_field`";
					$prm .= ", ?";
					$binds[] = $target_lbl_field_value;
				}
				if($target_val_field) {
					$sql .= ", `$target_val_field`";
					$prm .= ", ?";
					if($item['target_val'] == "__RESULT__") {
						$binds[] = $result;
					} else {
						$binds[] = $item['target_val'];
					}
				}
				if($target_nt_field && $nt) {
					$sql .= ", `$target_nt_field`";
					$prm .= ", ?";
					$binds[] = $nt;
				}
				if($target_order_id_field && $order_id) {
				    $sql .= ", `$target_order_id_field`";
				    $prm .= ", ?";
				    $binds[] = $order_id;
				}
				$sql .= ') ' . $prm . ')';
				echo "Target Insert: $sql -- with Binds: " . print_r($binds, TRUE) . "\n";
		
				sqlInsert($sql, $binds);
				
			// THESE TYPES NEED THE MOST RECENT ITERATION UPDATED
			} else if($target['id']) {
				$sql = "UPDATE `$target_table` SET `pid` = ?";
				$binds = array($pid);
				if($target_dt_field) {
				    if((in_array('preserve_date', $target_flags) === FALSE) || 
				            ($target[$target_dt_field] == NULL || $target[$target_dt_field] == '')) {
                        $sql .= ", `$target_dt_field` = ?";
                        $binds[] = $store_date;
				    }
				}
				if($target_cat_field && $target_cat_field_value) {
					$sql .= ", `$target_cat_field` = ?";
					$binds[] = $target_cat_field_value;
				}
				if($target_lbl_field) {
					$sql .= ", `$target_lbl_field` = ?";
					$binds[] = $target_lbl_field_value;
				}
				if($target_val_field) {
				    if((in_array('preserve_result', $target_flags) === FALSE) || 
				            ($target[$target_val_field] == NULL || $target[$target_val_field] == '')) {
					   $sql .= ", `$target_val_field` = ?";
					   if($item['target_val'] == "__RESULT__") {
					      $binds[] = $result;
					   } else {
					       $binds[] = $item['target_val'];
					   }
				    }
				}
				if($target_nt_field) {
					$sql .= ", `$target_nt_field` = ?";
					if(in_array('append_note', $target_flags)) {
					    // IF THIS NOTE TEXT IS NOT ALREADY IN THE NOTE APPEND IT
					    if((stripos($target[$target_nt_field], $nt) === FALSE) && 
					       ($target[$target_nt_field])) $nt = $target{$target_nt_field} . "\r" . $nt;
					}
					$binds[] = $nt;
				}
				if($target_order_id_field && $order_id) {
				    $sql .= ", `$target_order_id_field` = ?";
				    $binds[] = $order_id;
				}
				$sql .= ' WHERE `id` = ?';
				$binds[] = $target['id'];
				sqlStatement($sql, $binds);
				error_log("Target Update: $sql -- with Binds: " . print_r($binds, TRUE));
			} else {
			    error_log('No Recent Record to Update');
			    error_log("Test Lab Rule ($pid) [$date] ($code) - $type - [$result]\n");
			}
			/*
			echo "This was the SQL: $sql<br>\n";
			echo "Binds: ";
			print_r($binds);
			echo "<br>\n";
			*/
		}
	}
}

?>
