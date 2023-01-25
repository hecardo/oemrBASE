<?php
/** **************************************************************************
 *	ParserHL7v23.PHP
 *
 *	Copyright (c)2013 - Williams Medical Technology, Inc.
 *
 *	This program is licensed software: licensee is granted a limited nonexclusive
 *  license to install this Software on more than one computer system, as long as all
 *  systems are used to support a single licensee. Licensor is and remains the owner
 *  of all titles, rights, and interests in program.
 *  
 *  Licensee will not make copies of this Software or allow copies of this Software 
 *  to be made by others, unless authorized by the licensor. Licensee may make copies 
 *  of the Software for backup purposes only.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT 
 *	ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 *  FOR A PARTICULAR PURPOSE. LICENSOR IS NOT LIABLE TO LICENSEE FOR ANY DAMAGES, 
 *  INCLUDING COMPENSATORY, SPECIAL, INCIDENTAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL 
 *  DAMAGES, CONNECTED WITH OR RESULTING FROM THIS LICENSE AGREEMENT OR LICENSEE'S 
 *  USE OF THIS SOFTWARE.
 *
 *  @package laboratory
 *  @subpackage generic
 *  @version 2.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 *************************************************************************** */

namespace WMT\Laboratory\Generic;

if (!class_exists('WMT\Laboratory\Generic\ParseException')) {
	class ParseException extends \Exception {
		// just adds exception class name
	}
}

class SegmentHL7v23 {
	// contents are dynamic
	var $type;
}

class ParserHL7v23 {

	var $field_separator;
	var $map;
	var $message;
	var $message_type;
	var $options;

	var $MSH;
	var $PID;
	var $PV1;
	var $IN1;
	var $GT1;
	var $DG1;
	var $ORC;
	var $OBR;
	var $OBX;
	var $NTE;
	var $ZPS;
	var $OTHER;

	function __construct($message, $_options = NULL ) {
		$this->message = $message;
		$this->field_separator = '|'; // default
		if (is_array($_options)) {
			$this->options = $_options;
		}
		
		// TURN OFF DETAILS !!!
		$this->options['detail'] = false;
	}
	
	function parse() {
		// reference to message
		$message = &$this->message;
		
		// Split HL7v23 message into lines
		$segments = explode("\r", $message);
		
		// Fail if there are no or one segments
		if (count($segments) <= 1) {
			throw new ParseException('No segments found in HL7 message');
		}

		// Loop through messages
		$count = 0;
		foreach ($segments AS $segment) {
			$segment = trim($segment); // strip garbage
			if (! $segment) continue; // blank line
			
			$pos = 0;
			$count++;

			// Determine segment ID
			$type = substr($segment, 0, 3);
			switch ($type) {
				case 'MSH':
				case 'PID':
				case 'PV1':
				case 'ORC':
					// non-repeating segments
					$this->message_type = trim($type);
					$pos = call_user_func_array(
						array(&$this, '_'.$type),
						array($segment)
					);
					$this->map[$count]['type'] = $type;
					$this->map[$count]['position'] = 0;
					break;

				case 'IN1':
				case 'OBR':
				case 'OBX':
				case 'NTE':
				case 'EVN':
					// repeating segments
					$this->message_type = trim($type);
					$pos = call_user_func_array(
						array(&$this, '_'.$type),
						array($segment)
					);
					$this->map[$count]['type'] = $type;
					$this->map[$count]['position'] = $pos;
					break;

				default:
					// unknown segment type
					$this->message_type = trim($type);
					$this->__default_segment_parser($segment);
					$this->map[$count]['type'] = $type;
					$this->map[$count]['position'] = count($this->OTHER[$type]);
					break;
					
			} // end switch type
		}
	}


	// ----- All handlers go below here -----

	
	function _EVN ($segment) {
		$composites = $this->__parse_segment ($segment);

		list (
			$this->EVN['event_type_code'],
			$this->EVN['event_datetime'],
			$this->EVN['event_planned'],
			$this->EVN['event_reason'],
			$this->EVN['operator_id']
		) = $composites;

		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>EVN segment</b><br/>";
			print_r($this->EVN);
			print "<br/>";
		}
		
	} // end method _EVN

	
	function _MSH($segment) {
		// Get separator
		$this->field_separator = substr($segment, 3, 1);
		
		// decompose composite segments
		$composites = $this->__parse_segment($segment);
		
		// try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		// Assign values
		list (
			$__garbage, // Skip index [0], it's the separator
			$this->MSH['encoding_characters'],
			$this->MSH['sending_application'],
			$this->MSH['sending_facility'] ,
			$this->MSH['receiving_application'],
			$this->MSH['receiving_facility'],
			$this->MSH['message_datetime'],
			$__garbage, // unsupported
			$this->MSH['message_type'],
			$this->MSH['message_control_id'],
			$this->MSH['processing_id'],
			$this->MSH['version_id']
		) = $composites;

		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>MSH segment</b><br/>";
			print_r($this->MSH);
			print "<br/>";
		}
		
	} // end method _MSH

	
	function _PID($segment) {
		$composites = $this->__parse_segment($segment);

		// try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		// Assign values
		list (
			$__garbage, // Skip index [0], it's the type
			$this->PID['set_id'],
			$this->PID['alternate_id'],
			$this->PID['external_id'],
			$this->PID['patient_id'],
			$this->PID['patient_name'],
			$this->PID['maiden_name'],
			$this->PID['birth_datetime'],
			$this->PID['sex'],
			$this->PID['patient_alias'],
			$this->PID['race'],
			$this->PID['patient_address'],
			$this->PID['country_code'],
			$this->PID['phone_number'],
			$this->PID['phone_business'],
			$this->PID['primary_language'],
			$this->PID['marital_status'],
			$this->PID['religion'],
			$this->PID['accounting'],
			$this->PID['ssn']
		) = $composites;

		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>PID segment</b><br/>";
			print_r($this->PID);
			print "<br/>";
		}
		
	} // end method _PID

	
	function _PV1($segment) {
		$composites = $this->__parse_segment($segment);

		// try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		// Assign values
		list (
			$__garbage, // Skip index [0], it's the type
			$this->PV1['set_id'],
			$this->PV1['patient_class'],
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$this->PV1['attending_provider'],
			$this->PV1['referring_provider'],
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$__garbage, // unsupported,
			$this->PV1['datetime_submitted']
		) = $composites;

		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>PV1 segment</b><br/>";
			print_r($this->PV1);
			print "<br/>";
		}
		
	} // end method _PV1

	
	function _IN1($segment) {
		$composites = $this->__parse_segment($segment);

		// Try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		// Find out where we are
		$pos = 0;
		if (is_array($this->IN1)) {
			$pos = count($this->IN1);
		}
		
		list (
			$__garbage, // Skip index [0], it's the type
			$this->IN1[$pos]['set_id'],
			$__garbage, // unsupported,
			$this->IN1[$pos]['ins_company_id'],
			$this->IN1[$pos]['ins_company_name'],
			$this->IN1[$pos]['ins_company_address'],
			$__garbage, // unsupported
			$this->IN1[$pos]['ins_phone_number'],
			$this->IN1[$pos]['group_number'],
			$__garbage, // unsupported
			$__garbage, // unsupported
			$this->IN1[$pos]['group_emp_name'],
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$this->IN1[$pos]['insured_name'],
			$this->IN1[$pos]['insured_relation'],
			$__garbage, // unsupported
			$this->IN1[$pos]['insured_address'],
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$__garbage, // unsupported
			$this->IN1[$pos]['company_plan_code'],
			$this->IN1[$pos]['policy_number']
		) = $composites;
		
		return $pos;

		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>IN1 segment</b><br/>";
			print_r($this->IN1);
			print "<br/>";
		}
		
	} // end method _IN1

	
	function _ORC($segment) {
		$composites = $this->__parse_segment($segment);

		// Try to parse composites
		foreach ($composites as $key => $composite) {
			if (!(strpos($composite, '^LAB') === false)) {
				$composites[$key] = str_replace('^LAB', '', $composite);
			}
			elseif (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		list (
			$__garbage, // Skip index [0], it's the type
			$this->ORC['order_control'],
			$this->ORC['placer_order_number'],
			$this->ORC['filler_order_number'],
			$__garbage, // unsupported ORC-4
			$this->ORC['order_status'],
			$__garbage, // unsupported ORC-6
			$this->ORC['datetime_processed'], 
			$__garbage, // unsupported ORC-8
			$this->ORC['datetime_transaction'], 
			$__garbage, // unsupported
			$__garbage, // unsupported
			$this->ORC['ordering_provider']
		) = $composites;

		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>ORC segment</b><br/>";
			print_r($this->ORC);
			print "<br/>";
		}
		
	} // end method _ORC

	
	function _OBR($segment) {
		$composites = $this->__parse_segment($segment);
	
		// Try to parse composites
		foreach ($composites as $key => $composite) {
			if (!(strpos($composite, '^LAB') === false)) {
				$composites[$key] = str_replace('^LAB', '', $composite);
			}
			elseif (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
	
		// Find out where we are
		$pos = 0;
		if (is_array($this->OBR)) {
			$pos = count($this->OBR);
		}
	
		list (
			$__garbage, // Skip index [0], it's the type
			$this->OBR[$pos]['set_id'],
			$this->OBR[$pos]['placer_order_number'],
			$this->OBR[$pos]['filler_order_number'],
			$this->OBR[$pos]['universal_service_id'],
			$__garbage, // unsupported OBR-5
			$this->OBR[$pos]['bbpl_datetime'], // BBPL received 
			$this->OBR[$pos]['collected_datetime'],
			$__garbage, // unsupported OBR-8
			$__garbage, // unsupported OBR-9
			$__garbage, // unsupported OBR-10
			$this->OBR[$pos]['action_type'],
			$__garbage, // unsupported OBR-12
			$this->OBR[$pos]['additional_data'],
			$this->OBR[$pos]['received_datetime'],
			$__garbage, // unsupported OBR-15
			$this->OBR[$pos]['ordering_provider'],
			$__garbage, // unsupported OBR-17
			$this->OBR[$pos]['passthru_field1'], // ACCESSION FOR SCARSDALE
			$__garbage, // unsupported OBR-19
			$this->OBR[$pos]['microbiology_field'],
			$this->OBR[$pos]['producer_data'], // NEW FOR BIOREF
//			$__garbage, // unsupported OBR-21
			$this->OBR[$pos]['reported_datetime'],
			$__garbage, // unsupported OBR-23
			$this->OBR[$pos]['producer_id'],
			$this->OBR[$pos]['result_status'],
			$this->OBR[$pos]['parent_id'],
			$this->OBR[$pos]['processed_datetime'], // SCARSDALE
			$__garbage, // unsupported OBR-28
			$__garbage, // unsupported OBR-29
			$__garbage, // unsupported OBR-30
			$__garbage, // unsupported OBR-31
			$this->OBR[$pos]['reported_by'] // SCARSDALE
		) = $composites;
		
		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>OBR segment - $pos</b><br/>";
			print_r($this->OBR[$pos]);
			print "<br/>";
		}
		
		return $pos;
	
	} // end method _OBR
	
	
	function _OBX($segment) {
		$composites = $this->__parse_segment($segment);
	
		// Try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
	
		// Find out where we are
		$pos = 0;
		if (is_array($this->OBX)) {
			$pos = count($this->OBX);
		}
	
		list (
			$__garbage, // Skip index [0], it's the type
			$this->OBX[$pos]['set_id'],
			$this->OBX[$pos]['value_type'],
			$this->OBX[$pos]['universal_service_id'],
			$__garbage, // unsupported OBX-4
			$this->OBX[$pos]['observation_value'],
			$this->OBX[$pos]['observation_units'],
			$this->OBX[$pos]['observation_range'],
			$this->OBX[$pos]['observation_abnormal'],
			$__garbage, // unsupported OBX-9
			$__garbage, // unsupported OBX-10
			$this->OBX[$pos]['observation_status'],
			$__garbage, // unsupported OBX-12
			$__garbage, // unsupported OBX-13
			$this->OBX[$pos]['observation_datetime'],
			$this->OBX[$pos]['producer_id']
		) = $composites;
		
		if ($this->OBX[$pos]['observation_abnormal'] == 'N') $this->OBX[$pos]['observation_abnormal'] = '';
		
		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>OBX segment - $pos</b><br/>";
			print_r($this->OBX[$pos]);
			print "<br/>";
		}
		
		return $pos;
	
	} // end method _OBX
	
	
	function _NTE($segment) {
		$composites = $this->__parse_segment($segment);
	
		// Try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
	
		// Find out where we are
		$pos = 0;
		if (is_array($this->NTE)) {
			$pos = count($this->NTE);
		}
	
		list (
			$__garbage, // Skip index [0], it's the type
			$this->NTE[$pos]['set_id'],
			$this->NTE[$pos]['source'],
			$this->NTE[$pos]['comment']
		) = $composites;
	
		return $pos;
	
	} // end method _NTE
	
	function zoneDate($dateString,$offset='') {
		$date = array();
		if (strlen($dateString) < 9) {
			$date[1] = $dateString;
			$date[2] = '';
		}
		elseif (strlen($dateString) < 13) {
			preg_match('/(\d{8})(\d{4})/',$dateString,$date);
		}
		elseif (strlen($dateString) < 15) {
			preg_match('/(\d{8})(\d{6})/',$dateString,$date);
		}
		elseif (strlen($dateString) == 17) {
			preg_match('/(\d{8})(\d{4})([\-|\+]\d{4})/',$dateString,$date);
			$offset = $date[3];
		}
		elseif (strlen($dateString) == 19) {
			preg_match('/(\d{8})(\d{6})([\-|\+]\d{4})/',$dateString,$date);
			$offset = $date[3];
		}
		elseif (strlen($dateString) == 25) {
			preg_match('/(\d{8})(\d{6})(\.\d{4})([\-|\+].{5})/',$dateString,$date);
			$offset = str_replace(':','',$date[4]);
		}
		if (!$date[2] || substr($date[2],0,4) == '0000')
			$zone_date = date('Y-m-d',strtotime($date[1]." ".$offset));
		elseif ($date[1])
			$zone_date = date('Y-m-d H:i:s',strtotime($date[1]." ".$date[2]." ".$offset));
		else
			$zone_date = '';
	
		return $zone_date;
	}
	
	function getMessage() {
		$map = &$this->map;
		$message = new SegmentHL7v23();
	
		// determine time zone
		$zone_offset = ''; // global UTC offset
		if ($this->MSH['message_datetime']) {
			$zone_sign = substr($this->MSH['message_datetime'],-5,1);
			if ($zone_sign == '+' || $zone_sign == '-') 
				$zone_offset = substr($this->MSH['message_datetime'],-5,5);
		}
		
		// gather request information
		$message->datetime = self::zoneDate($this->MSH['message_datetime'],$zone_offset);
		$message->application = $this->MSH['receiving_application'];
		$message->facility_id = $this->MSH['receiving_facility'];
		
		$message->pid = $this->PID['patient_id'];
		$message->dob = self::zoneDate($this->PID['birth_datetime'],$zone_offset);
		$message->age = $this->PID['age'][0];
		$message->name = $this->PID['patient_name'];
		$message->sex = $this->PID['sex'];
		$message->ss = $this->PID['ss'];
		$message->pubpid = (is_array($this->PID['alternate_id'])) ? $this->PID['alternate_id'][0] : $this->PID['alternate_id'];
		$message->extpid = (is_array($this->PID['external_id'])) ? $this->PID['external_id'][0] : $this->PID['external_id'];
		$message->phone = $this->PID['phone_number'];
		$message->address = $this->PID['patient_address'];
		$message->order_control = $this->ORC['order_control'];
		// this is really only for BioReference but should be okay for all
		if (is_array($this->ORC['placer_order_number'])) $this->ORC['placer_order_number'] = $this->ORC['placer_order_number'][0];
		$message->order_number = $this->ORC['placer_order_number'];
		// Only for CPL but it won't hurt anybody else (strips acct- from order number)
		//OLD $message->order_number = str_replace($this->MSH['receiving_facility']."-", "", $message->order_number);
		if ($this->MSH['sending_application'] == 'CPL') {
			$facility = stristr($message->order_number, '-'); // grab from '-' thru end
			if ($facility !== false) { // no '-'
				$message->order_number = ltrim($facility, '-');
			}
		}
		
		$message->provider = $this->ORC['ordering_provider'];
		$message->lab_received = self::zoneDate($this->ORC['datetime_transaction'],$zone_offset);
		$message->control_id = $this->ORC['filler_order_number'];

		// special for scarsdale
		if ($this->MSH['receiving_facility'] == 'TX-SCARS') {
			$message->specimen_datetime = self::zoneDate($this->PV1['datetime_submitted'],$zone_offset);
			$message->received_datetime = self::zoneDate($this->ORC['datetime_transaction'],$zone_offset);
			$message->reported_datetime = self::zoneDate($this->OBR[0]['collected_datetime'],$zone_offset);
			$message->provider = $this->PV1['referring_provider'];
			$message->control_id = $this->OBR[0]['passthru_field1'];
		}
				
		$message->lab_status = '';
/*
 *  C - Record coming over is a correction and thus replaces a final result
 *  D - Deletes the OBX record
 *  F - Final results; Can only be changed with a corrected result.
 *  I - Specimen in lab; results pending
 *  P - Preliminary results
 *  R - Results entered -- not verified
 *  S - Partial results
 *  X - Results cannot be obtained for this observation
 *  U - Results status change to Final. 
 *  W - Post original as wrong, e.g., transmitted for wrong patient
 */
		$message->reports = array();
		$message->labs = array(); 
		
		for ($i = 0; $i < count($map); $i++) {
			$item = $map[$i];
			
			while ($item['type'] == 'NTE') {
				$nte_data = &$this->NTE[$item['position']];
				
				$note = new SegmentHL7v23();
				$note->set_id = $nte_data['set_id'];
				$note->source = $nte_data['source'];
				//$note->comment = $nte_data['comment'];
				//special replacement for CERNER (doesn't hurt anybody else)
				$note->comment = str_ireplace("\\.br\\", "\n", $nte_data['comment']);
				
				$message->notes[] = $note;
				$item = $map[++$i];		
			}
			
			while ($item['type'] == 'OBR') {
				$obr_data = &$this->OBR[$item['position']];

				// check for existing -- CERNER DUPLICATE CHECKING
				if (!empty($message->reports)) {
					foreach ($message->reports AS $idx => $dupchk) {
						// is there a duplicate result					
						if ($dupchk->service_id == $obr_data['universal_service_id']) {
							// if existing record is newer
							$thisDatetime = self::zoneDate($obr_data['reported_datetime'],$zone_offset);
							if ($dupchk->result_datetime >= $thisDatetime) {
								// ignore current record
								continue;
							}
							else {
								// remove existing record
								unset($message->reports[$idx]);
							}
						}
					}
				} // end dup check	
							
				$report = new SegmentHL7v23();
				$report->set_id = $obr_data['set_id'];
				$report->order_control = $this->ORC['order_control'];
                $report->order_number = $obr_data['placer_order_number'];
                if (is_array($report->order_number)) $report->order_number = $report->order_number[0];
				// this is really only for CPL but it won't hurt anybody else (strips acct- from order number)
                $report->order_number = str_replace($this->MSH['receiving_facility']."-", "", $report->order_number);
                if (!$message->order_number) $message->order_number = $report->order_number;
                
                $report->control_id = $obr_data['filler_order_number'];
                if (is_array($report->control_id)) $report->control_id = $report->control_id[0];
                if (!$message->control_id) $message->control_id = $report->control_id;
				if (!$message->order_number) $message->order_number = $report->order_number;
				
				$report->service_id = $obr_data['universal_service_id'];
				$report->parent_id = $obr_data['parent_id'];
				$report->component_id = null;
				
// BBPL ONLY !!! $received_datetime = ($obr_data['bbpl_datetime'])? $obr_data['bbpl_datetime'] : $obr_data['received_datetime'];
				$received_datetime = ($obr_data['received_datetime'])? $obr_data['received_datetime'] : $obr_data['bbpl_datetime'];
				$specimen_datetime = ($obr_data['collected_datetime']) ? $obr_data['collected_datetime'] : $received_datetime;
				$reported_datetime = ($obr_data['reported_datetime'])? $obr_data['reported_datetime'] : '';
				
				$report->specimen_datetime = self::zoneDate($specimen_datetime,$zone_offset);
				if (!$message->specimen_datetime) $message->specimen_datetime = $report->specimen_datetime;
				
				$report->received_datetime = self::zoneDate($received_datetime,$zone_offset);
				if (!$message->received_datetime) $message->received_datetime = $report->received_datetime;
				
				$report->result_datetime = self::zoneDate($reported_datetime,$zone_offset);
				if (!$message->reported_datetime) $message->reported_datetime = $report->result_datetime;
				
				$report->provider = $obr_data['ordering_provider'];
				if (!$message->provider) $message->provider = $obr_data['ordering_provider'];
				$lab_default = $obr_data['producer_id']; // producing laboratory facility 
				$report->service_section = $lab_default;
				$report->result_status = $obr_data['result_status'];
				$report->action_type = $obr_data['action_type'];
				if (!$message->additional_data) $message->additional_data = $obr_data['additional_data'];

				// determine overall result status
				if ($message->lab_status == '' && $obr_data['result_status'] == 'F') $message->lab_status = 'F'; // default final if we get any results
				if ($message->lab_status == '' && $obr_data['result_status'] != 'F') $message->lab_status = $obr_data['result_status'];
				
				// ADDED FOR BIOREF LAB DATA
				if ($obr_data['producer_data']) {
					if (is_array($obr_data['producer_data'])) {
						$lab_code = $obr_data['producer_data'][0];
						$lab_name = $obr_data['producer_data'][1];
						$lab_director = $obr_data['producer_data'][6];
						
						$lab_address = '';
						if ($obr_data['producer_data'][2]) {
							$lab_address = array();
							$lab_address[] = $obr_data['producer_data'][2]; // street
							$lab_address[] = '';
							$lab_address[] = $obr_data['producer_data'][3]; // city
							$lab_address[] = $obr_data['producer_data'][4]; // state
							$lab_address[] = $obr_data['producer_data'][5]; // zip
						}
				
						if ($message->labs[$lab_code]) { // already on file
							$lab = $message->labs[$lab_code];
						}
						else { // need to file
							$lab = new SegmentHL7v23();
				
							$lab->code = $lab_code;
							$lab->name = $lab_name;
							$lab->address = $lab_address;
							$lab->director = $lab_director;
							$lab->phone = '';
				
							$message->labs[$lab_code] = $lab; // only need one instance
						}
					}
					else {
						$lab_code = $obr_data['producer_data'];
					}
				}

				// update default if necessary
				if (!$lab_default) $lab_default = $lab_code;
				
				$item = $map[++$i];		
				while ($item['type'] == 'NTE') {
					$nte_data = &$this->NTE[$item['position']];
				
					$note = new SegmentHL7v23();
					$note->set_id = $nte_data['set_id'];
					$note->source = $nte_data['source'];
					//$note->comment = $nte_data['comment'];
					$note->comment = str_ireplace("\\.br\\", "\n", $nte_data['comment']);
						
				
					$report->notes[] = $note;
					$item = $map[++$i];		
				}
			
				while ($item['type'] == 'OBX') {
					$obx_data = &$this->OBX[$item['position']];
			
					$result = new SegmentHL7v23();
					$result->set_id = $obx_data['set_id'];
					$result->value_type = $obx_data['value_type'];
					$result->observation_id = $obx_data['universal_service_id'];
					//$result->observation_value = $obx_data['observation_value'];
					//special replacement for SCARSDALE (doesn't hurt anybody else)
					$result->observation_value = str_ireplace("~", "\n", $obx_data['observation_value']);
					$result->observation_units = $obx_data['observation_units'];
					$result->observation_range = $obx_data['observation_range'];
					$result->observation_abnormal = $obx_data['observation_abnormal'];
					if ($obx_data['observation_abnormal'] == 'N') $result->observation_abnormal = '';
					$result->observation_status = $obx_data['observation_status'];

					$observation_datetime = ($obx_data['observation_datetime']) ? $obx_data['observation_datetime'] : $reported_datetime;
					$result->observation_datetime = self::zoneDate($observation_datetime,$zone_offset);
					if (!$message->reported_datetime && $observation_datetime) $message->reported_datetime = $result->observation_datetime; 
					
					$result->producer_id = ($obx_data['producer_id'])? $obx_data['producer_id'] : $lab_default;
					if (is_array($result->producer_id)) {
						$result->producer_id = $result->producer_id[0];
					}

					// get next HL7 item
					$item = $map[++$i];
					
					while ($item['type'] == 'NTE') {
						$nte_data = &$this->NTE[$item['position']];
				
						$note = new SegmentHL7v23();
						$note->set_id = $nte_data['set_id'];
						$note->source = $nte_data['source'];
						//$note->comment = $nte_data['comment'];
						$note->comment = str_ireplace("\\.br\\", "\n", $nte_data['comment']);
						
				
						$result->notes[] = $note;
						$item = $map[++$i];		
					}
			
					$report->results[] = $result;
				}
				
				$message->reports[] = $report;
			}
			
			// Lab specific segment
			while ($item['type'] == 'ZPS') { // place of service
				$zps_data = &$this->ZPS[$item['position']];
			
				$lab = new SegmentHL7v23();
				$lab->set_id = $zps_data['set_id'];
				$lab->code = $zps_data['lab_id'];
				$lab->name = $zps_data['lab_name'];
				$lab->address = $zps_data['lab_address'];
				$lab->phone = $zps_data['lab_phone'];
				$lab->director = $zps_data['lab_director'];
				$lab->clia = $zps_data['lab_clia'];
				
				$message->labs[$zps_data['lab_id']] = $lab; // only need one instance
				$item = $map[++$i];		
			}
		}	
			
		// check for no results
		if (! $message->lab_status) $message->lab_status = 'I'; // incomplete
		
		return $message;
	}
	
	
	//----- Truly internal functions

	function __default_segment_parser ($segment) {
		$composites = $this->__parse_segment($segment);

		// Try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		// The first composite is always the message type
		$type = $composites[0];

		// Debug
		if ($this->options['debug'] && $this->options['detail']) {
			print "<b>".$type." segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".$v."<br/>\n";
			}
		}

		$pos = 0;

		// Find out where we are
		if (is_array($this->OTHER[$type])) {
			$pos = count($this->OTHER[$type]);
		}
		
		$this->OTHER[$type][$pos] = $composites;

	} // end method __default_segment_parser

	function __parse_composite ($composite) {
		return explode('^', $composite);
	} // end method __parse_composite

	function __parse_segment ($segment) {
		return explode($this->field_separator, $segment);
	} // end method __parse_segment
	
} // end class ParserHL7v23

?>
