--
-- Table structure for table `form_quest`
-- Labs 3.0 - Medical Technology Services
--
CREATE TABLE IF NOT EXISTS `form_laboratory` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `pid` bigint(20) NOT NULL,
  `user` varchar(255) NOT NULL,
  `groupname` varchar(255) DEFAULT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `status` varchar(16) DEFAULT NULL,
  `priority` varchar(16) DEFAULT NULL,
  `order_number` varchar(225) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `lab_id` varchar(25) DEFAULT NULL,
  `ins_primary` bigint(20) DEFAULT 0,
  `ins_secondary` bigint(20) DEFAULT 0,
  `order_type` varchar(255) DEFAULT NULL,
  `order_notes` text DEFAULT NULL,
  `work_flag` char(1) DEFAULT NULL,
  `work_insurance` varchar(25) DEFAULT NULL,
  `work_date` date DEFAULT NULL,
  `work_employer` varchar(25) DEFAULT NULL,
  `work_case` varchar(25) DEFAULT NULL,
  `received_datetime` datetime DEFAULT NULL,
  `report_datetime` datetime DEFAULT NULL,
  `result_abnormal` int(5) DEFAULT 0,
  `reviewed_datetime` datetime DEFAULT NULL,
  `reviewed_id` bigint(20) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `notified_datetime` datetime DEFAULT NULL,
  `notified_id` bigint(20) DEFAULT NULL,
  `notified_person` varchar(255) DEFAULT NULL,
  `patient_notes` text DEFAULT NULL,
  `order_abn_id` varchar(255) DEFAULT NULL,
  `order_req_id` varchar(255) DEFAULT NULL,
  `result_doc_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_idx` (`order_number`),
  KEY `pid_idx` (`pid`),
  KEY `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `form_orphans`
-- Labs 3.0 - Medical Technology Services
--
CREATE TABLE IF NOT EXISTS `form_orphans` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `pid` bigint(20) NOT NULL,
  `user` varchar(255) NOT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `lab_id` varchar(25) DEFAULT NULL,
  `pat_fname` varchar(255) DEFAULT NULL,
  `pat_mname` varchar(255) DEFAULT NULL,
  `pat_lname` varchar(255) DEFAULT NULL,
  `pat_DOB` date DEFAULT NULL COMMENT 'used in result matching',
  `pat_sex` varchar(8) DEFAULT NULL,
  `pat_race` varchar(31) DEFAULT NULL,
  `pat_ss` varchar(31) DEFAULT NULL,
  `pat_pubpid` varchar(31) DEFAULT NULL,
  `doc_npi` varchar(255) DEFAULT NULL,
  `doc_lname` varchar(255) DEFAULT NULL,
  `doc_fname` varchar(255) DEFAULT NULL,
  `doc_mname` varchar(255) DEFAULT NULL,
  `date_ordered` datetime DEFAULT NULL,
  `date_collected` datetime DEFAULT NULL,
  `date_transmitted` datetime DEFAULT NULL,
  `account` varchar(32) DEFAULT NULL,
  `billing_type` varchar(16) DEFAULT NULL,
  `order_number` varchar(225) NOT NULL,
  `request_notes` text DEFAULT NULL,
  `result_doc_id` varchar(255) DEFAULT NULL,
  `received_datetime` datetime DEFAULT NULL,
  `report_datetime` datetime DEFAULT NULL,
  `control_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_idx` (`order_number`),
  KEY `pid_idx` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `procedure_batch`
-- Labs 3.0 - Medical Technology Services
--
CREATE TABLE IF NOT EXISTS `procedure_batch` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `pid` bigint(20) DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `groupname` varchar(255) DEFAULT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `order_number` varchar(60) DEFAULT NULL,
  `order_date` datetime DEFAULT NULL,
  `report_date` datetime DEFAULT NULL,
  `lab_id` bigint(20) DEFAULT NULL,
  `facility_id` varchar(255) DEFAULT NULL,
  `provider_id` varchar(255) DEFAULT NULL,
  `provider_npi` varchar(255) DEFAULT NULL,
  `pat_dob` date DEFAULT NULL,
  `pat_first` varchar(255) DEFAULT NULL,
  `pat_middle` varchar(255) DEFAULT NULL,
  `pat_last` varchar(255) DEFAULT NULL,
  `lab_number` varchar(60) DEFAULT NULL,
  `lab_status` varchar(20) DEFAULT NULL,
  `hl7_message` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `procedure_facility`
-- Labs 3.0 - Medical Technology Services
--
CREATE TABLE IF NOT EXISTS `procedure_facility` (
  `code` varchar(31) NOT NULL,
  `type` varchar(32) DEFAULT NULL,
  `namespace` varchar(255) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `street` varchar(100) DEFAULT NULL,
  `street2` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` char(2) DEFAULT NULL,
  `zip` varchar(12) DEFAULT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `director` varchar(100) DEFAULT NULL,
  `npi` varchar(31) DEFAULT NULL,
  `clia` varchar(25) DEFAULT NULL,
  `lab_id` bigint(20) NOT NULL COMMENT 'procedure_provider.ppid',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `procedure_answers`
--
CREATE TABLE IF NOT EXISTS `procedure_answers` (
  `procedure_order_id` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references procedure_order.procedure_order_id',
  `procedure_order_seq` int(11) NOT NULL DEFAULT 0 COMMENT 'references procedure_order_code.procedure_order_seq',
  `question_code` varchar(31) NOT NULL DEFAULT '' COMMENT 'references procedure_questions.question_code',
  `answer_seq` int(11) NOT NULL COMMENT 'supports multiple-choice questions. answer_seq, incremented in code',
  `answer` varchar(255) NOT NULL DEFAULT '' COMMENT 'answer data',
  `procedure_code` varchar(31) DEFAULT NULL,
  PRIMARY KEY (`procedure_order_id`,`procedure_order_seq`,`question_code`,`answer_seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `procedure_order`
--
CREATE TABLE IF NOT EXISTS `procedure_order` (
  `procedure_order_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uuid` binary(16) DEFAULT NULL,
  `provider_id` bigint(20) DEFAULT 0 COMMENT 'references users.id, the ordering provider',
  `patient_id` bigint(20) NOT NULL COMMENT 'references patient_data.pid',
  `encounter_id` bigint(20) DEFAULT 0 COMMENT 'references form_encounter.encounter',
  `date_collected` datetime DEFAULT NULL COMMENT 'time specimen collected',
  `date_ordered` datetime DEFAULT NULL,
  `order_priority` varchar(31) DEFAULT '',
  `order_status` varchar(31) DEFAULT '' COMMENT 'pending,routed,complete,canceled',
  `patient_instructions` text DEFAULT NULL,
  `activity` tinyint(1) DEFAULT 1 COMMENT '0 if deleted',
  `control_id` varchar(255) DEFAULT '' COMMENT 'This is the CONTROL ID that is sent back from lab',
  `lab_id` bigint(20) DEFAULT 0 COMMENT 'references procedure_providers.ppid',
  `specimen_draw` varchar(8) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `specimen_type` varchar(31) DEFAULT NULL,
  `specimen_location` varchar(31) DEFAULT NULL,
  `specimen_volume` varchar(31) DEFAULT NULL,
  `date_pending` datetime DEFAULT NULL COMMENT '** LABS 3.0 **',
  `date_transmitted` datetime DEFAULT NULL COMMENT 'time of order transmission, null if unsent',
  `clinical_hx` varchar(255) DEFAULT NULL,
  `specimen_fasting` varchar(31) DEFAULT NULL,
  `specimen_duration` varchar(31) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `specimen_transport` varchar(31) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `specimen_source` varchar(31) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `external_id` varchar(20) DEFAULT NULL,
  `history_order` enum('0','1') DEFAULT '0',
  `portal_flag` tinyint(1) DEFAULT 0 COMMENT '** LABS 3.0 **',
  `tav_done` tinyint(1) DEFAULT 0 COMMENT '** LABS 3.0 **',
  `order_diagnosis` varchar(255) DEFAULT NULL,
  `billing_type` varchar(4) DEFAULT NULL,
  `order_psc` tinyint(4) DEFAULT NULL,
  `order_abn` varchar(31) DEFAULT NULL,
  `collector_id` bigint(11) DEFAULT NULL,
  `account` varchar(60) DEFAULT NULL,
  `account_facility` bigint(11) DEFAULT NULL,
  `provider_number` varchar(30) DEFAULT NULL,
  `procedure_order_type` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`procedure_order_id`),
  KEY `date_pid` (`date_ordered`,`patient_id`),
  KEY `patient_id` (`patient_id`),
  KEY `provider_idx` (`provider_id`),
  KEY `encounter_idx` (`encounter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `procedure_order_code`
--
CREATE TABLE IF NOT EXISTS `procedure_order_code` (
  `procedure_order_id` bigint(20) NOT NULL COMMENT 'references procedure_order.procedure_order_id',
  `procedure_order_seq` int(11) NOT NULL COMMENT 'Supports multiple tests per order. Procedure_order_seq incremented in code',
  `procedure_code` varchar(31) NOT NULL DEFAULT '' COMMENT 'like procedure_type.procedure_code',
  `procedure_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'descriptive name of the procedure code',
  `procedure_source` char(1) NOT NULL DEFAULT '1' COMMENT '1=original order, 2=added after order sent',
  `diagnoses` text DEFAULT NULL,
  `do_not_send` tinyint(1) DEFAULT 0 COMMENT '0 = normal, 1 = do not transmit to lab',
  `procedure_order_title` varchar(255) DEFAULT NULL,
  `procedure_type` varchar(31) DEFAULT NULL,
  `transport` varchar(31) DEFAULT NULL,
  `reflex_code` varchar(31) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `reflex_set` varchar(31) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `reflex_name` varchar(31) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `labcorp_zseg` varchar(31) DEFAULT NULL COMMENT '** LABS 3.0 **',
  `lab_id` int(11) DEFAULT NULL COMMENT '** LABS 3.0 **',
  PRIMARY KEY (`procedure_order_id`,`procedure_order_seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `procedure_providers`
--
CREATE TABLE IF NOT EXISTS `procedure_providers` (
  `ppid` bigint(20) NOT NULL AUTO_INCREMENT,
  `uuid` binary(16) DEFAULT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `npi` varchar(15) NOT NULL DEFAULT '',
  `send_app_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Sending application ID (MSH-3.1)',
  `send_fac_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Sending facility ID (MSH-4.1)',
  `recv_app_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Receiving application ID (MSH-5.1)',
  `recv_fac_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Receiving facility ID (MSH-6.1)',
  `DorP` char(1) NOT NULL DEFAULT 'D' COMMENT 'Debugging or Production (MSH-11)',
  `direction` char(1) NOT NULL DEFAULT 'B' COMMENT 'Bidirectional or Results-only',
  `protocol` varchar(15) NOT NULL DEFAULT 'DL',
  `remote_host` varchar(255) NOT NULL DEFAULT '',
  `remote_port` varchar(6) DEFAULT '22' COMMENT '** Labs 3.0 **',
  `login` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `orders_path` varchar(255) NOT NULL DEFAULT '',
  `results_path` varchar(255) NOT NULL DEFAULT '',
  `notes` text DEFAULT NULL,
  `lab_director` bigint(20) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(31) DEFAULT NULL,
  PRIMARY KEY (`ppid`),
  UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `procedure_questions`
--
CREATE TABLE IF NOT EXISTS `procedure_questions` (
  `lab_id` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references procedure_providers.ppid to identify the lab',
  `procedure_code` varchar(31) NOT NULL DEFAULT '' COMMENT 'references procedure_type.procedure_code to identify this order type',
  `question_code` varchar(31) NOT NULL DEFAULT '' COMMENT 'code identifying this question',
  `seq` int(11) NOT NULL DEFAULT 0 COMMENT 'sequence number for ordering',
  `question_text` varchar(255) NOT NULL DEFAULT '' COMMENT 'descriptive text for question_code',
  `required` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = required, 0 = not',
  `maxsize` int(11) NOT NULL DEFAULT 0 COMMENT 'maximum length if text input field',
  `fldtype` char(1) NOT NULL DEFAULT 'T' COMMENT 'Text, Number, Select, Multiselect, Date, Gestational-age',
  `options` text DEFAULT NULL COMMENT 'choices for fldtype S and T',
  `tips` varchar(255) NOT NULL DEFAULT '' COMMENT 'Additional instructions for answering the question',
  `activity` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = inactive',
  `section` varchar(32) DEFAULT NULL COMMENT '** Labs 3.0 **',
  PRIMARY KEY (`lab_id`,`procedure_code`,`question_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `procedure_report`
--
CREATE TABLE IF NOT EXISTS `procedure_report` (
  `procedure_report_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uuid` binary(16) DEFAULT NULL,
  `procedure_order_id` bigint(20) DEFAULT NULL COMMENT 'references procedure_order.procedure_order_id',
  `procedure_order_seq` int(11) NOT NULL DEFAULT 1 COMMENT 'references procedure_order_code.procedure_order_seq',
  `date_collected` datetime DEFAULT NULL,
  `date_collected_tz` varchar(5) DEFAULT '' COMMENT '+-hhmm offset from UTC',
  `date_report` datetime DEFAULT NULL,
  `date_report_tz` varchar(5) DEFAULT '' COMMENT '+-hhmm offset from UTC',
  `source` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references users.id, who entered this data',
  `specimen_num` varchar(63) NOT NULL DEFAULT '',
  `report_status` varchar(31) NOT NULL DEFAULT '' COMMENT 'received,complete,error',
  `review_status` varchar(31) NOT NULL DEFAULT 'received' COMMENT 'pending review status: received,reviewed',
  `report_notes` text DEFAULT NULL COMMENT 'notes from the lab',
  PRIMARY KEY (`procedure_report_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `procedure_order_id` (`procedure_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `procedure_result`
--
CREATE TABLE IF NOT EXISTS `procedure_result` (
  `procedure_result_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uuid` binary(16) DEFAULT NULL,
  `procedure_report_id` bigint(20) NOT NULL COMMENT 'references procedure_report.procedure_report_id',
  `result_data_type` char(1) NOT NULL DEFAULT 'S' COMMENT 'N=Numeric, S=String, F=Formatted, E=External, L=Long text as first line of comments',
  `result_code` varchar(31) NOT NULL DEFAULT '' COMMENT 'LOINC code, might match a procedure_type.procedure_code',
  `result_text` varchar(255) NOT NULL DEFAULT '' COMMENT 'Description of result_code',
  `date` datetime DEFAULT NULL COMMENT 'lab-provided date specific to this result',
  `facility` varchar(255) NOT NULL DEFAULT '' COMMENT 'lab-provided testing facility ID',
  `units` varchar(31) NOT NULL DEFAULT '',
  `result` varchar(255) NOT NULL DEFAULT '',
  `range` varchar(255) NOT NULL DEFAULT '',
  `abnormal` varchar(31) NOT NULL DEFAULT '' COMMENT 'no,yes,high,low',
  `comments` text DEFAULT NULL COMMENT 'comments from the lab',
  `document_id` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references documents.id if this result is a document',
  `result_status` varchar(31) NOT NULL DEFAULT '' COMMENT 'preliminary, cannot be done, final, corrected, incomplete...etc.',
  `result_set` varchar(31) DEFAULT NULL,
  PRIMARY KEY (`procedure_result_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `procedure_report_id` (`procedure_report_id`),
  KEY `result_idx` (`result`(10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `procedure_type`
--
CREATE TABLE IF NOT EXISTS `procedure_type` (
  `procedure_type_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `parent` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references procedure_type.procedure_type_id',
  `name` varchar(63) NOT NULL DEFAULT '' COMMENT 'name for this category, procedure or result type',
  `lab_id` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references procedure_providers.ppid, 0 means default to parent',
  `procedure_code` varchar(31) NOT NULL DEFAULT '' COMMENT 'code identifying this procedure',
  `procedure_type` varchar(31) NOT NULL DEFAULT '' COMMENT 'see list proc_type',
  `body_site` varchar(31) NOT NULL DEFAULT '' COMMENT 'where to do injection, e.g. arm, buttock',
  `specimen` varchar(31) NOT NULL DEFAULT '' COMMENT 'blood, urine, saliva, etc.',
  `route_admin` varchar(31) NOT NULL DEFAULT '' COMMENT 'oral, injection',
  `laterality` varchar(31) NOT NULL DEFAULT '' COMMENT 'left, right, ...',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT 'descriptive text for procedure_code',
  `standard_code` varchar(255) NOT NULL DEFAULT '' COMMENT 'industry standard code type and code (e.g. CPT4:12345)',
  `related_code` varchar(255) NOT NULL DEFAULT '' COMMENT 'suggested code(s) for followup services if result is abnormal',
  `units` varchar(31) NOT NULL DEFAULT '' COMMENT 'default for procedure_result.units',
  `range` varchar(255) NOT NULL DEFAULT '' COMMENT 'default for procedure_result.range',
  `seq` int(11) NOT NULL DEFAULT 0 COMMENT 'sequence number for ordering',
  `activity` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `notes` varchar(255) NOT NULL DEFAULT '' COMMENT 'additional notes to enhance description',
  `transport` varchar(31) DEFAULT NULL,
  `procedure_type_name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`procedure_type_id`),
  KEY `parent` (`parent`),
  KEY `ptype_procedure_code` (`procedure_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Insert default lists and list values
--

REPLACE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`) VALUES  
('lists', 	'Processor_Protocol', 	'Processor Protocol', 	0, 1, 0, '', '', ''),
('lists', 	'Processor_Type', 		'Processor Type', 		0, 1, 0, '', '', ''),
('lists', 	'Lab_Form_Status', 		'Lab Form Status', 		0, 1, 0, '', '', ''),
('lists', 	'Lab_Notification', 	'Lab Notification', 	0, 1, 0, '', 'Notify <nurse_username> instead of <doc_username>', ''),
('lists', 	'Lab_Diagnosis', 		'Lab Diagnosis', 		0, 1, 0, '', 'Quick List', ''),
('lists', 	'Lab_Category', 		'Lab Category', 		0, 1, 0, '', 'ID=appt code TITLE=orphan results', ''),
('lists', 	'Lab_Race', 			'Lab Race', 			0, 1, 0, '', 'ID=OEMR code TITLE=HL7 code', ''),
('lists', 	'Lab_Ethnicity',		'Lab Ethnicity', 		0, 1, 0, '', 'ID=OEMR code TITLE=HL7 code', ''),
('lists', 	'Processor_Billing', 	'Processor Billing', 	0, 1, 0, '', '', ''),
('lists', 	'One_to_Nine', 			'One to Nine', 			0, 1, 0, '', '', ''),
('lists', 	'Lab_Transport', 		'Lab Transport',		0, 1, 0, '', '', ''),
('lists', 	'Lab_Label_Printers',	'Lab Label Printers',	0, 1, 0, '', '', '');

REPLACE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`) VALUES  
('proc_type', 			'det', 			'Item Details', 			40, 0, 0, '', 'Discription of test', ''),
('proc_type', 			'fav', 			'Favorite', 				50, 0, 0, '', 'Display in favorite tab', ''),
('proc_type', 			'grp', 			'Group Title', 				10, 0, 0, '', '', ''),
('proc_type', 			'ord', 			'Procedure', 				20, 0, 0, '', '', ''),
('proc_type', 			'pro', 			'Profile Panel', 			30, 0, 0, '', 'Panel (multiple tests)', ''),
('proc_type', 			'rec', 			'Recommendation', 			70, 0, 0, '', '', ''),
('proc_type', 			'res', 			'Discrete Result', 			60, 0, 0, '', '', ''),
('Lab_Diagnosis', 		'V70.6', 		'Default', 					0, 0, 0, '', 'ID=code TITLE=tab name NOTES=alternate name', ''),
('Lab_Notification', 	'admin', 		'nurse', 					0, 0, 0, '', 'Admin -> Lab Nurse', ''),
('Lab_Category', 		'12', 			'Lab Results', 				0, 0, 0, '', 'ID=appt code TITLE=orphan results', ''),
('Processor_Protocol', 	'FS', 			'Filesystem Standard',		10, 0, 0, '', '', ''),
('Processor_Protocol', 	'SFTP',			'sFTP Client Standard',		20, 0, 0, '', '', ''),
('Processor_Protocol', 	'FSC', 			'sFTP Client Custom', 		30, 0, 0, '', '', ''),
('Processor_Protocol', 	'FC2', 			'sFTP Client 2.5.1', 		40, 0, 0, '', '', ''),
('Processor_Protocol', 	'FSS', 			'sFTP Server Custom', 		50, 0, 0, '', '', ''),
('Processor_Protocol', 	'FS2', 			'sFTP Server 2.5.1', 		60, 0, 0, '', '', ''),
('Processor_Protocol', 	'INT', 			'Internal Only', 			70, 0, 0, '', '', ''),
('Processor_Protocol', 	'WS', 			'Web Service', 				80, 0, 0, '', '', ''),
('Processor_Type', 		'I', 			'Internal Laboratory', 		10, 0, 0, '', 'In-House Procedures', ''),
('Processor_Type', 		'G',		 	'Generic Laboratory', 		20, 0, 0, '', '', ''),
('Processor_Type', 		'Q',	 		'Quest Diagnostics', 		20, 0, 0, '', '', ''),
('Processor_Type', 		'L',	 		'LabCorp Laboratory',	 	20, 0, 0, '', '', ''),
('Lab_Form_Status', 	'g', 			'Order Processed', 			3, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Form_Status', 	'i', 			'Order Incomplete', 		1, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Form_Status', 	'n', 			'Results Notification', 	7, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Form_Status', 	's', 			'Order Submitted', 			2, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Form_Status', 	'v', 			'Results Reviewed', 			6, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Form_Status', 	'x', 			'Results Partial', 			4, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Form_Status', 	'z', 			'Results Final', 			5, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Form_Status', 	'f', 			'Results Abnormal',			6, 0, 0, '', 'DO NOT CHANGE', ''),
('Lab_Race', 			'amer_ind_or_a', 'I', 						1, 0, 0, '', 'ID = OEMR value', ''),
('Lab_Race', 			'asian', 		'A', 						2, 0, 0, '', 'TITLE = HL7 value', ''),
('Lab_Race', 			'black_or_afri_amer', 'B', 					3, 0, 0, '', '', ''),
('Lab_Race', 			'native_hawai_or_pac_island', 'C', 			4, 0, 0, '', '', ''),
('Lab_Race', 			'unknown', 		'X', 						6, 1, 0, '', 'not provided', ''),
('Lab_Race', 			'white', 		'C', 						5, 0, 0, '', '', ''),
('Lab_Ethnicity', 		'hisp_or_latin', 'H', 						1, 0, 0, '', 'ID = OEMR value', ''),
('Lab_Ethnicity', 		'not_hisp_or_latino', 'N', 					2, 0, 0, '', 'TITLE = HL7 value', ''),
('Lab_Ethnicity', 		'unknown', 		'U', 						3, 0, 0, '', 'not provided', ''),
('Procedure_Billing', 	'C', 			'Bill Clinic', 				30, 0, 0, '', '', ''),
('Procedure_Billing', 	'P', 			'Self Pay', 				20, 0, 0, '', '', ''),
('Procedure_Billing', 	'T', 			'Third-Party', 				10, 1, 0, '', '', ''),
('One_to_Nine', 		'1', 			'1', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'2', 			'2', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'3', 			'3', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'4', 			'4', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'5', 			'5', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'6', 			'6', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'7', 			'7', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'8', 			'8', 						0, 0, 0, '', '', ''),
('One_to_Nine', 		'9', 			'9', 						0, 0, 0, '', '', ''),
('Lab_Transport', 		'A', 			'Ambient', 					10, 0, 0, '', '', ''),
('Lab_Transport', 		'F', 			'Frozen', 					20, 0, 0, '', '', ''),
('Lab_Transport', 		'FR/FZ', 		'Frozen', 					30, 0, 0, '', '', ''),
('Lab_Transport', 		'FZ', 			'Frozen', 					140, 0, 0, '', '', ''),
('Lab_Transport', 		'G', 			'Refrigerated', 			40, 0, 0, '', '', ''),
('Lab_Transport', 		'H', 			'Handwritten', 				50, 0, 0, '', '', ''),
('Lab_Transport', 		'M', 			'Multiple Types', 			60, 0, 0, '', '', ''),
('Lab_Transport', 		'PAP', 			'Pathology', 				150, 0, 0, '', '', ''),
('Lab_Transport', 		'R', 			'Room Temperature', 		70, 0, 0, '', '', ''),
('Lab_Transport', 		'RF', 			'Refrigerated', 			130, 0, 0, '', '', ''),
('Lab_Transport', 		'RT', 			'Room Temperature', 		120, 0, 0, '', '', ''),
('Lab_Transport', 		'S', 			'Split Requisition', 		80, 0, 0, '', '', ''),
('Lab_Transport', 		'W', 			'Wet Ice', 					100, 0, 0, '', '', ''),
('Lab_Transport', 		'Z', 			'Pain Management', 			110, 0, 0, '', '', '');

--
-- Insert Quest data into table `list_options`
--

REPLACE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`) VALUES
('lists', 	'Lab_Quest_Printers', 	'Lab Quest Printers', 	0, 1, 0, '', ''),
('lists', 	'Lab_Quest_Sites', 		'Lab Quest Sites', 	0, 1, 0, '', ''),
('lists', 	'Lab_Quest_Accounts', 	'Lab Quest Accounts', 			0, 1, 0, '', '');

REPLACE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`) VALUES
('Lab_Quest_Accounts', 	'12345678', 	'General', 			0, 1, 0, '', ''),
('Lab_Quest_Printers', 	'127.0.0.2', 	'Some Location', 	20, 1, 0, '', ''),
('Lab_Quest_Sites', 	'3', 			'123456', 			0, 1, 0, '', 'Primary Clinic');

