<?php
/** **************************************************************************
 *	ParserHL7v251.PHP
 *
 *	Copyright (c)2014 - Williams Medical Technology, Inc.
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

class SegmentHL7v251 {
	// contents are dynamic
	var $type;
}

class ParserHL7v251 {

	var $field_separator;
	var $map;
	var $message;
	var $message_type;

	var $MSH;
	var $PID;
	var $IN1;
	var $GT1;
	var $DG1;
	var $ORC;
	var $OBR;
	var $OBX;
	var $NTE;
	var $SPM;
	var $ZPS;
	var $OTHER;

	function Parser_HL7v251( $message, $_options = NULL ) {
		$this->message = $message;
		$this->field_separator = '|'; // default
		if (is_array($_options)) {
			$this->options = $_options;
		}
	}
	
	function parse() {
		// reference to message
		$message = &$this->message;
		
		// Split HL7v251 message into lines
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
				case 'ORC':
					// only keep original ORC segment (require placer order number)
					if ($type == 'ORC' && substr($segment, 6, 2) == '||') break;
					
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
				case 'SPM':
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

		if ($this->options['debug']) {
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
			$this->MSH['message_datetime'], // sets default time-zone
			$__garbage, // unsupported
			$this->MSH['message_type'], // ORU^R01^ORU_R01
			$this->MSH['message_control_id'],
			$this->MSH['processing_id'],
			$this->MSH['version_id'], // 2.5.1
			$__garbage, // MSH-13
			$__garbage, // MSH-14
			$this->MSH['accept_ack_type'], // AL
			$this->MSH['appl_ack_type'], // NE
			$__garbage, // MSH-17
			$__garbage, // MSH-18
			$__garbage, // MSH-19
			$__garbage, // MSH-20
			$this->MSH['message_profile'] // LRI_NG_RU_Profile^^2.16.840.1.113883.9.19^ISO
		) = $composites;

		if ($this->options['debug']) {
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
			$this->PID['set_id'], // 1
			$this->PID['patient_id'], // clinic PID
			$this->PID['identifier_list'], // patient identifiers
			$this->PID['alternate_pid'], // laboratory PID
			$this->PID['patient_name'],
			$this->PID['maiden_name'],
			$this->PID['birth_datetime'],
			$this->PID['sex'],
			$this->PID['patient_alias'],
			$this->PID['race'],
			$this->PID['patient_address'],
			$__garbage, // country
			$this->PID['phone_number'],
			$this->PID['phone_business'],
			$this->PID['primary_language'],
			$this->PID['marital_status'],
			$this->PID['religion'],
			$this->PID['accounting'],
			$__garbage,
			$__garbage,
			$__garbage, // mother
			$this->PID['ethnicity']
		) = $composites;

		if ($this->options['debug']) {
			print "<b>PID segment</b><br/>";
			print_r($this->PID);
			print "<br/>";
		}
		
	} // end method _PID

	
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

		if ($this->options['debug']) {
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
			$this->ORC['placer_group_number'],
			$this->ORC['order_status'],
			$__garbage, // unsupported ORC-6
			$__garbage, // unsupported ORC-7
			$__garbage, // unsupported ORC-8
			$this->ORC['transaction_datetime'],
			$this->ORC['entered_by'],
			$__garbage, // unsupported ORC-11
			$this->ORC['ordering_provider']
		) = $composites;

		if ($this->options['debug']) {
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
			$__garbage, // unsupported OBR-6
			$this->OBR[$pos]['specimen_datetime'],
			$__garbage, // unsupported OBR-8
			$__garbage, // unsupported OBR-9
			$__garbage, // unsupported OBR-10
			$__garbage, // unsupported OBR-11
			$__garbage, // unsupported OBR-12
			$this->OBR[$pos]['additional_data'],
			$__garbage, // unsupported OBR-14
			$__garbage, // unsupported OBR-15
			$this->OBR[$pos]['ordering_provider'],
			$__garbage, // unsupported OBR-17
			$this->OBR[$pos]['placer_field1'],
			$this->OBR[$pos]['placer_field2'],
			$this->OBR[$pos]['filler_field1'],
			$this->OBR[$pos]['filler_field2'],
			$this->OBR[$pos]['reported_datetime'],
			$__garbage, // unsupported OBR-23
			$this->OBR[$pos]['serv_sect_id'],
			$this->OBR[$pos]['result_status'],
			$this->OBR[$pos]['parent_id'],
			$__garbage, // unsupported OBR-27
			$this->OBR[$pos]['copy_to'],
			$__garbage, // unsupported OBR-29
			$__garbage // unsupported OBR-30
		) = $composites;
		
		if ($this->options['debug']) {
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
			$this->OBX[$pos]['universal_service_id'], // LONIC
			$this->OBX[$pos]['observation_set'],
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
			$__garbage, // unsupported OBX-15
			$__garbage, // unsupported OBX-16
			$__garbage, // unsupported OBX-17
			$__garbage, // unsupported OBX-18
			$this->OBX[$pos]['analyzed_datetime'],
			$__garbage, // unsupported OBX-20
			$__garbage, // unsupported OBX-21
			$__garbage, // unsupported OBX-22
			$this->OBX[$pos]['lab_identifier'],
			$this->OBX[$pos]['lab_address'],
			$this->OBX[$pos]['lab_director']
		) = $composites;
		
		if ($this->OBX[$pos]['observation_abnormal'] == 'N') $this->OBX[$pos]['observation_abnormal'] = '';
		
		if ($this->options['debug']) {
			print "<b>OBX segment - $pos</b><br/>";
			print_r($this->OBX[$pos]);
			print "<br/>";
		}
		
		return $pos;
	
	} // end method _OBX
	
	
	function _SPM($segment) {
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
		if (is_array($this->SPM)) {
			$pos = count($this->SPM);
		}
	
		list (
			$__garbage, // Skip index [0], it's the type
			$this->SPM[$pos]['set_id'],
			$this->SPM[$pos]['specimen_id'],
			$__garbage, // unsupported SPM-3
			$this->SPM[$pos]['specimen_type'], // HL70487 or SNOMED-CT
			$this->SPM[$pos]['type_modifier'], 
			$this->SPM[$pos]['specimen_additive'], 
			$this->SPM[$pos]['collection_method'], // HL70488 or SNOMED-Ct
			$this->SPM[$pos]['source_site'], // SNOMED-CT
			$this->SPM[$pos]['source_quantifier'],
			$__garbage, // unsupported SPM-10
			$__garbage, // unsupported SPM-11
			$this->SPM[$pos]['specimen_volume'], // UCUM
			$__garbage, // unsupported SPM-13
			$__garbage, // unsupported SPM-14
			$__garbage, // unsupported SPM-15
			$__garbage, // unsupported SPM-16
			$this->SPM[$pos]['specimen_datetime'],
			$this->SPM[$pos]['received_datetime'],
			$__garbage, // unsupported SPM-19
			$__garbage, // unsupported SPM-20
			$this->SPM[$pos]['specimen_rejected'],
			$__garbage, // unsupported SPM-22
			$__garbage, // unsupported SPM-23
			$this->SPM[$pos]['specimen_condition'],
			$__garbage, // unsupported SPM-25
			$__garbage, // unsupported SPM-26
			$__garbage, // unsupported SPM-27
			$__garbage, // unsupported SPM-28
			$__garbage // unsupported SPM-29
		) = $composites;
		
		if ($this->options['debug']) {
			print "<b>SPM segment - $pos</b><br/>";
			print_r($this->SPM[$pos]);
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
	
		if (is_array($this->NTE[$pos]['comment'])) {
			$this->NTE[$pos]['comment'] = implode("\n",$this->NTE[$pos]['comment']);
		}
		
		if ($this->options['debug']) {
			print "<b>NTE segment - $pos</b><br/>";
			print_r($this->NTE[$pos]);
			print "<br/>";
		}
		
		return $pos;
	
	} // end method _NTE
	
	
	// LabCorp specific
	function _ZEF($segment) {
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
		if (is_array($this->ZEF)) {
			$pos = count($this->ZEF);
		}
	
		list (
			$__garbage, // Skip index [0], it's the type
			$this->ZEF[$pos]['set_id'],
			$this->ZEF[$pos]['base64']
		) = $composites;
	
		if ($this->options['debug']) {
			print "<b>ZEF segment - $pos</b><br/>";
			print_r($this->ZEF[$pos]);
			print "<br/>";
		}
		
		return $pos;
	
	} // end method _NTE
	
	
	// laboratory specific
	function _ZPS($segment) {
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
		if (is_array($this->ZPS)) {
		$pos = count($this->ZPS);
		}
	
		list (
		$__garbage, // Skip index [0], it's the type
		$this->ZPS[$pos]['set_id'],
		$this->ZPS[$pos]['lab_id'],
		$this->ZPS[$pos]['lab_name'],
		$this->ZPS[$pos]['lab_address'],
		$this->ZPS[$pos]['lab_phone'],
		$__garbage,
		$this->ZPS[$pos]['lab_director'],
		$__garbage,
		$this->ZPS[$pos]['lab_clia']
		) = $composites;
	
		if ($this->options['debug']) {
			print "<b>ZPS segment - $pos</b><br/>";
			print_r($this->ZPS[$pos]);
			print "<br/>";
		}
		
		return $pos;
	
	} // end method _NTE
	
	function zoneDate($dateString,$offset='') {
		$date = array();
		if (strlen($dateString) < 9) {
			$date[1] = $dateString;
			$date[2] = '';
		}
		elseif (strlen($dateString) < 13) {
			preg_match('/(\d{8})(\d{4})/',dateString,$date);
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
			preg_match('/(\d{8})(\d{6})(\.\d{4})([\-|\+]\d{4})/',$dateString,$date);
			$offset = $date[4];
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
		$message = new SegmentHL7v251();
	
		// gather request information
		$zone_offset = ''; // global UTC offset
		if ($this->MSH['message_datetime']) {
			$zone_sign = substr($this->MSH['message_datetime'],-5,1);
			if ($zone_sign == '+' || $zone_sign == '-') 
				$zone_offset = substr($this->MSH['message_datetime'],-5,5);
		}
		$message->datetime = self::zoneDate($this->MSH['message_datetime'],$zone_offset);
		$message->application = $this->MSH['receiving_application'];
		if (is_array($message->application)) $message->application = $message->application[0];
		$message->facility_id = $this->MSH['receiving_facility'];
		if (is_array($message->facility_id)) $message->facility_id = $message->facility_id[0];
		
		$message->pid = false; // we will determine later if the pubpid is a valid oemr pid
		$message->pubpid = $this->PID['identifier_list'];
		if (is_array($message->pubpid)) {
			$message->pubpid = $this->PID['identifier_list'][0];
			$message->external_pid = $message->pubpid; // store for CERNER
			$message->namespace = $this->PID['identifier_list'][3];
			$message->idtype = $this->PID['identifier_list'][4]; 
		}
		
		$message->race = $this->PID['race'];
		if (is_array($message->race)) {
			$message->race = $this->PID['race'][1];
			$message->ethnicity = $this->PID['race'][4]; 
		}
		
//		if (is_array($this->PID['ethnicity'])) $message->ethnicity = $this->PID['ethnicity'][1]; 
		if (is_array($this->PID['phone_number'])) {
			$message->phone = $this->PID['phone_number'][5]; 
			$message->phone .= "-".substr($this->PID['phone_number'][6],0,3)."-".substr($this->PID['phone_number'][6],3,4); 
		}
		
		$dob = str_replace('0000','',$this->PID['birth_datetime']);
		if (strlen($dob) == 8)
			$message->dob = substr($dob, 0, 4)."-".substr($dob, 4, 2)."-".substr($dob, 6, 2);
		elseif (strlen($dob) == 10)
			$message->dob = $dob;
		else 
			$message->dob = '';
		
		$message->name = $this->PID['patient_name'];
		$message->sex = $this->PID['sex'];
		$message->ss = $this->PID['ss'];
		$message->address = $this->PID['patient_address'];
//		$message->account = $this->PID['accounting'][0]; // first element
//		$message->bill_type = $this->PID['accounting'][3];
//		$message->lab_status = $this->PID['accounting'][5];
		
		$message->order_number = $this->ORC['placer_order_number'];
		if (is_array($message->order_number)) {
			$message->order_number = $this->ORC['placer_order_number'][0];
			$message->order_namespace = $this->ORC['placer_order_number'][1];
		}
		$message->lab_number = $this->ORC['filler_order_number'];
		if (is_array($message->lab_number)) {
			$message->lab_number = $this->ORC['filler_order_number'][0]; 
			$message->lab_namespace = $this->ORC['filler_order_number'][1]; 
		}
		$message->group_number = $this->ORC['placer_group_number'];
		if (is_array($message->group_number)) {
			$message->group_number = $this->ORC['placer_group_number'][0];
			$message->group_namespace = $this->ORC['placer_group_number'][1];
		}
		
		$message->provider = $this->ORC['ordering_provider'];
		$message->lab_received = $this->ORC['transaction_datetime'];
		$message->lab_status = 'F'; // assume its a final result
		
		// ADDED FOR CERNER BUT HURTS NO ONE ELSE
		$message->order_control = $this->ORC['order_control'];
		
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
		
		for ($i = 1; $i < count($map); $i++) {
			$item = $map[$i];
			
			while ($item['type'] == 'NTE') {
				$nte_data = &$this->NTE[$item['position']];
				
				$note = new SegmentHL7v251();
				$note->set_id = $nte_data['set_id'];
				$note->source = trim($nte_data['source']);
				$note->comment = str_ireplace("\\.br\\", "\n", $nte_data['comment']);
				
				$message->notes[] = $note;
				$item = $map[++$i];		
			}
			
			while ($item['type'] == 'OBR') {
				$obr_data = &$this->OBR[$item['position']];

				// check for existing
				if (!empty($message->reports)) {
					foreach ($message->reports AS $idx => $dupchk) {
						// is there a duplicate result
						if ($dupchk->service_id == $obr_data['universal_service_id']) {
							// is existing record is newer
							if ($dupchk->result_datetime >= $obr_data['reported_datetime']) {
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
				
				$report = new SegmentHL7v251();
				$report->set_id = $obr_data['set_id'];
				$report->order_control = $message->order_control;
				
				if (is_array($obr_data['placer_order_number'])) $report->order_number = $obr_data['placer_order_number'][0];
				else $report->order_number = $obr_data['placer_order_number'];
				if (!$message->order_number) $message->order_number = $report->order_number;

				if (is_array($obr_data['filler_order_number'])) $report->lab_number = $obr_data['filler_order_number'][0];
				else $report->lab_number = $obr_data['filler_order_number'];
				if (!$message->lab_number) $message->lab_number = $report->lab_number;

				$report->service_id = $obr_data['universal_service_id'];
				$report->parent_id = $obr_data['parent_id'];
				$report->component_id = null;

				if ($obr_data['specimen_datetime']) {
					$report->specimen_datetime = self::zoneDate($obr_data['specimen_datetime'],$zone_offset);
					if (!$message->specimen_datetime) $message->specimen_datetime = $report->specimen_datetime;
				}
				 
//				if ($obr_data['received_datetime']) {
//					$report->received_datetime = self::zoneDate($obr_data['received_datetime'],$zone_offset);
//					if (!$message->received_datetime) $message->received_datetime = $report->received_datetime; 
//				}
//				else {
//					$report->received_datetime = $report->specimen_datetime;
//				}
				
				if ($obr_data['reported_datetime']) {
					$report->reported_datetime = self::zoneDate($obr_data['reported_datetime'],$zone_offset);
					if (!$message->reported_datetime) $message->reported_datetime = $report->reported_datetime;
				}
				
				$report->provider = $obr_data['ordering_provider'];
				if (!$message->provider) $message->provider = $report->provider; 
				$report->service_section = $obr_data['producer_id'];
				$report->result_status = $obr_data['result_status'];
				if ($obr_data['additional_data']) {
					if ($message->additional_data) $message->additional_data .= "<br/>";
					if (is_array($obr_data['additional_data'])) $message->additional_data .= $obr_data['additional_data'][1];
					else $message->additional_data .= $obr_data['additional_data'];
				}

				// determine overall result status - anything other than FINAL or CORRECTED 
				if ($message->lab_status == 'F' && ($obr_data['result_status'] != 'F' && $obr_data['result_status'] != 'C') ) $message->lab_status = $obr_data['result_status'];
				
				// get next HL7 item
				$item = $map[++$i];

				while ($item['type'] == 'NTE') {
					$nte_data = &$this->NTE[$item['position']];
				
					$note = new SegmentHL7v251();
					$note->set_id = $nte_data['set_id'];
					$note->source = trim($nte_data['source']);
					$note->comment = str_ireplace("\\.br\\", "\n", $nte_data['comment']);
																			
					$report->notes[] = $note;
					$item = $map[++$i];		
				}
			
				// not used in this implementation !!
				while (substr($item['type'],0,2) == 'TQ' || $item['type'] == 'CTD') {
					$item = $map[++$i];
				}
				
				while ($item['type'] == 'OBX') {
					$obx_data = &$this->OBX[$item['position']];
					
					if ($obx_data['lab_identifier']) {
						if (is_array($obx_data['lab_identifier'])) {
							$lab_code = $obx_data['lab_identifier'][9];
							if (!$lab_code) $lab_code = $obx_data['lab_identifier'][2]; //alternate location
							$lab_code_namespace = $obx_data['lab_identifier'][5];
							$lab_code_type = $obx_data['lab_identifier'][6];
							$lab_name = $obx_data['lab_identifier'][0];
						}
						else {
							$lab_code_namespace = '';
							$lab_code_type = '';
							$lab_code = count($message->labs) + 1;
							$lab_name = $obx_data['lab_identifier'];
						}
						
						if ($message->labs[$lab_name]) { // already on file
							$lab = $message->labs[$lab_name];
						}
						else { // need to file
							$lab = new SegmentHL7v251();

							$lab->code = $lab_code;
							$lab->code_namespace = $lab_code_namespace;
							$lab->code_type = $lab_code_type;
							$lab->name = $lab_name;
							$lab->address = $obx_data['lab_address'];
							$lab->director = $obx_data['lab_director'];
							$lab->phone = $obx_data['lab_phone'];

							$message->labs[$lab->name] = $lab; // only need one instance
						}
					}
													
					$result = new SegmentHL7v251();
					$result->set_id = $obx_data['set_id'];
					$result->value_type = $obx_data['value_type'];
					$result->observation_id = $obx_data['universal_service_id'];
					$result->observation_set = $obx_data['observation_set'];
					$result->observation_value = $obx_data['observation_value'];
					if (is_array($obx_data['observation_value'])) $result->observation_value = $obx_data['observation_value'][1];
					$result->observation_value = str_ireplace("\\.br\\", "\n", $result->observation_value);
					$result->observation_units = $obx_data['observation_units'];
					if (is_array($obx_data['observation_units'])) $result->observation_units = $obx_data['observation_units'][0];
					$result->observation_range = $obx_data['observation_range'];
					if (is_array($result->observation_range)) $result->observation_range = $result->observation_range[0];
					$result->observation_abnormal = $obx_data['observation_abnormal'];
					if (is_array($obx_data['observation_abnormal'])) $result->observation_abnormal = $obx_data['observation_abnormal'][0];
					$result->observation_status = $obx_data['observation_status'];
					$result->producer_id = $lab->code;
						
					if ($obx_data['observation_datetime']) {
						$result->observation_datetime = self::zoneDate($obx_data['observation_datetime'],$zone_offset);
						if (!$message->reported_datetime) $message->reported_datetime = $result->observation_datetime;
					}
					else {
						$result->observation_datetime = $message->reported_datetime;
					}

					// get next HL7 item
					$item = $map[++$i];

					while ($item['type'] == 'NTE') {
						$nte_data = &$this->NTE[$item['position']];
				
						$note = new SegmentHL7v251();
						$note->set_id = $nte_data['set_id'];
						$note->source = trim($nte_data['source']);
						$note->comment = str_ireplace("\\.br\\", "\n", $nte_data['comment']);
																
						$result->notes[] = $note;
						$item = $map[++$i];		
					}
			
					$report->results[] = $result;
				}
				
				// not used in this implementation !!
				while ($item['type'] == 'FTI' || $item['type'] == 'CTI') {
					$item = $map[++$i];
				}
				
				while ($item['type'] == 'SPM') {
					$spm_data = &$this->SPM[$item['position']];
			
					$specimen = new SegmentHL7v251();
					$specimen->set_id = $spm_data['set_id'];
					
					$specimen->collected_datetime = $spm_data['specimen_datetime'];
					if (is_array($spm_data['specimen_datetime'])) $specimen->collected_datetime = $spm_data['specimen_datetime'][0];
					
					if ($specimen->collected_datetime) {
						$specimen->collected_datetime = self::zoneDate($specimen->collected_datetime,$zone_offset);
						if (!$message->specimen_datetime) $message->specimen_datetime = $specimen->specimen_datetime;
					}
						
					$specimen->received_datetime = $spm_data['received_datetime'];
					if (is_array($spm_data['received_datetime'])) $specimen->received_datetime = $spm_data['received_datetime'][0];
					
					if ($specimen->received_datetime) {
						$specimen->received_datetime = self::zoneDate($specimen->received_datetime,$zone_offset);
						if (!$message->received_datetime) $message->received_datetime = $specimen->received_datetime; 
					}

					if (!$specimen->collected_datetime) {
						if ($specimen->received_datetime) $specimen->collected_datetime = $specimen->received_datetime;
						else $specimen->collected_datetime = $message->specimen_datetime;
					}
					
					if (!$specimen->received_datetime) {
						if ($specimen->collected_datetime) $specimen->received_datetime = $specimen->collected_datetime;
						else $specimen->received_datetime = $message->received_datetime;
					}
					
					$specimen->specimen_type = $spm_data['specimen_type'];
					if (is_array($spm_data['specimen_type'])) $specimen->specimen_type = ($spm_data['specimen_type'][8]) ? $spm_data['specimen_type'][8] : $spm_data['specimen_type'][1];
					$specimen->type_modifier = $spm_data['type_modifier'];
					if (is_array($spm_data['type_modifier'])) $specimen->type_modifier = $spm_data['type_modifier'][8];
					$specimen->specimen_additive = $spm_data['specimen_additive'];
					if (is_array($spm_data['specimen_additive'])) $specimen->specimen_additive = $spm_data['specimen_additive'][8];
					$specimen->collection_method = $spm_data['collection_method'];
					if (is_array($spm_data['collection_method'])) $specimen->collection_method = $spm_data['collection_method'][8];
					$specimen->source_site = $spm_data['source_site'];
					if (is_array($spm_data['source_site'])) $specimen->source_site = $spm_data['source_site'][8];
					$specimen->source_quantifier = $spm_data['source_quantifier'];
					if (is_array($spm_data['source_quantifier'])) $specimen->source_quantifier = $spm_data['source_quantifier'][8];
					$specimen->specimen_volume = $spm_data['specimen_volume'];
					if (is_array($spm_data['specimen_volume'])) $specimen->specimen_volume = $spm_data['specimen_volume'][0];
					$specimen->specimen_rejected = $spm_data['specimen_rejected'];
					if (is_array($spm_data['specimen_rejected'])) $specimen->specimen_rejected = $spm_data['specimen_rejected'][8];
					$specimen->specimen_condition = $spm_data['specimen_condition'];
					if (is_array($spm_data['specimen_condition'])) $specimen->specimen_condition = $spm_data['specimen_condition'][8];
						
					if (is_array($spm_data['specimen_id'])) {
						$specimen->specimen_id = explode('&', $spm_data['specimen_id'][1]);
						if (is_array($specimen->specimen_id)) $specimen->specimen_id = $specimen->specimen_id[0];
						if (!$message->specimen_number) $message->specimen_number = $specimen->specimen_id;
					}
					if (!$specimen->specimen_id && is_array($spm_data['specimen_type'])) $specimen->specimen_id = $spm_data['specimen_type'][0];

					// get next HL7 item
					$item = $map[++$i];
	
					while ($item['type'] == 'OBX') {
						$obx_data = &$this->OBX[$item['position']];
				
						$detail = new SegmentHL7v251();
						$detail->set_id = $obx_data['set_id'];
						$detail->value_type = $obx_data['value_type'];
						$detail->observation_id = $obx_data['universal_service_id'];
						$detail->observation_value = $obx_data['observation_value'];
						if (is_array($obx_data['observation_value'])) $detail->observation_value = $obx_data['observation_value'][1];
						$detail->observation_value = str_ireplace("\\.br\\", "\n", $detail->observation_value);
						$detail->observation_units = $obx_data['observation_units'];
						if (is_array($obx_data['observation_units'])) $detail->observation_units = $obx_data['observation_units'][3];
						$detail->observation_range = $obx_data['observation_range'];
						$detail->observation_abnormal = $obx_data['observation_abnormal'];
						if (is_array($obx_data['observation_abnormal'])) $detail->observation_abnormal = $obx_data['observation_abnormal'][0];
						$detail->observation_status = $obx_data['observation_status'];
						$detail->observation_datetime = self::zoneDate($obx_data['observation_datetime'],$zone_offset);
						if (!$detail->observation_datetime) $detail->observation_datetime = $message->reported_datetime;
						
						if (is_array($obx_data['lab_identifier']))
							$detail->producer_id = $message->labs[$obx_data['lab_identifier'][9]]->code;
						else
							$detail->producer_id = $message->labs[$obx_data['lab_identifier']]->code;
													
						// get next HL7 item
						$item = $map[++$i];
	
						$specimen->details[] = $detail;
					}
					
					$report->specimens[] = $specimen;
				}
				
				$message->reports[] = $report;
			}
			/* NOT IN 2.5.1 ----
			while ($item['type'] == 'ZPS') { // place of service
				$zps_data = &$this->ZPS[$item['position']];
				
				$lab = new SegmentHL7v251();
				$lab->set_id = $zps_data['set_id'];
				$lab->code = $zps_data['lab_id'];
				$lab->name = $zps_data['lab_name'];
				$lab->address = $zps_data['lab_address'];
				$lab->phone = $zps_data['lab_phone'];
				$lab->director = $zps_data['lab_director'];
				$lab->clia = $zps_data['lab_clia'];
				
				$message->labs[$zps_data['lab_id']] = $lab; // only need one instance

				// get next HL7 item
				$item = $map[++$i];
			}
			---- */
		}
		
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
		if ($this->options['debug']) {
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
	
} // end class Parser_HL7v251

?>
