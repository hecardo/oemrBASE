--
-- Table structure for table `procedure_batch`
-- Labs 4.0 - Medical Technology Services
--
CREATE TABLE IF NOT EXISTS `procedure_batch` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `pid` bigint(20) DEFAULT NULL,
  `user` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `groupname` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `order_number` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `order_date` datetime DEFAULT NULL,
  `report_date` datetime DEFAULT NULL,
  `lab_id` bigint(20) DEFAULT NULL,
  `facility_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `provider_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `provider_npi` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pat_dob` date DEFAULT NULL,
  `pat_first` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pat_middle` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pat_last` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lab_number` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lab_status` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `hl7_message` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Table structure for table `procedure_facility`
-- Labs 4.0 - Medical Technology Services
--
CREATE TABLE IF NOT EXISTS `procedure_facility` (
  `code` varchar(31) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `type` varchar(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `namespace` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `name` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `street` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `street2` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `state` char(2) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `zip` varchar(12) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `phone` varchar(25) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `director` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `npi` varchar(31) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `clia` varchar(25) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lab_id` bigint(20) NOT NULL COMMENT 'procedure_provider.ppid',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Table structure for table `procedure_order`
--
CREATE TABLE IF NOT EXISTS `procedure_order` (
  `procedure_order_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uuid` binary(16) DEFAULT NULL,
  `provider_id` bigint(20) DEFAULT 0 COMMENT 'references users.id, the ordering provider',
  `patient_id` bigint(20) DEFAULT NULL COMMENT 'references patient_data.pid',
  `encounter_id` bigint(20) DEFAULT 0 COMMENT 'references form_encounter.encounter',
  `date_collected` datetime DEFAULT NULL COMMENT 'time specimen collected',
  `date_ordered` datetime DEFAULT NULL,
  `date_pending` date DEFAULT NULL,
  `order_priority` varchar(31) DEFAULT '',
  `order_status` varchar(31) DEFAULT '' COMMENT 'pending,routed,complete,canceled',
  `patient_instructions` text DEFAULT NULL,
  `activity` tinyint(1) DEFAULT 1 COMMENT '0 if deleted',
  `control_id` varchar(255) DEFAULT '' COMMENT 'This is the CONTROL ID that is sent back from lab',
  `lab_id` bigint(20) DEFAULT 0 COMMENT 'references procedure_providers.ppid',
  `specimen_type` varchar(31) DEFAULT '' COMMENT 'from the Specimen_Type list',
  `specimen_location` varchar(31) DEFAULT '' COMMENT 'from the Specimen_Location list',
  `specimen_volume` varchar(30) DEFAULT '' COMMENT 'from a text input field',
  `date_transmitted` datetime DEFAULT NULL COMMENT 'time of order transmission, null if unsent',
  `clinical_hx` varchar(255) DEFAULT '' COMMENT 'clinical history text that may be relevant to the order',
  `external_id` varchar(20) DEFAULT NULL,
  `history_order` enum('0','1') DEFAULT '0' COMMENT 'references order is added for history purpose only.',
  `order_diagnosis` text DEFAULT '' COMMENT 'primary order diagnosis',
  `billing_type` varchar(4) DEFAULT NULL,
  `specimen_fasting` varchar(31) DEFAULT NULL,
  `order_psc` tinyint(4) DEFAULT NULL,
  `order_abn` varchar(31) DEFAULT 'not_required',
  `collector_id` bigint(11) DEFAULT 0,
  `account` varchar(60) DEFAULT NULL,
  `account_facility` int(11) DEFAULT NULL,
  `provider_number` varchar(30) DEFAULT NULL,
  `procedure_order_type` varchar(32) DEFAULT 'laboratory_test',
  PRIMARY KEY (`procedure_order_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `datepid` (`date_ordered`,`patient_id`),
  KEY `patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Table structure for table `procedure_order_code`
--
CREATE TABLE IF NOT EXISTS `procedure_order_code` (
  `procedure_order_id` bigint(20) NOT NULL COMMENT 'references procedure_order.procedure_order_id',
  `procedure_order_seq` int(11) NOT NULL COMMENT 'Supports multiple tests per order. Procedure_order_seq, incremented in code',
  `procedure_code` varchar(64) NOT NULL DEFAULT '' COMMENT 'like procedure_type.procedure_code',
  `procedure_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'descriptive name of the procedure code',
  `procedure_source` char(1) DEFAULT '1' COMMENT '1=original order, 2=added after order sent',
  `diagnoses` text DEFAULT NULL COMMENT 'diagnoses and maybe other coding (e.g. ICD9:111.11)',
  `do_not_send` tinyint(1) DEFAULT 0 COMMENT '0 = normal, 1 = do not transmit to lab',
  `procedure_order_title` varchar(255) DEFAULT NULL,
  `procedure_type` varchar(31) DEFAULT NULL,
  `transport` varchar(31) DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `reason_code` varchar(31) DEFAULT NULL,
  `reason_description` text DEFAULT NULL,
  `reason_date_low` datetime DEFAULT NULL,
  `reason_date_high` datetime DEFAULT NULL,
  `reason_status` varchar(31) DEFAULT NULL,
  PRIMARY KEY (`procedure_order_id`,`procedure_order_seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
  `login` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `orders_path` varchar(255) NOT NULL DEFAULT '',
  `results_path` varchar(255) NOT NULL DEFAULT '',
  `notes` text DEFAULT NULL,
  `lab_director` bigint(20) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `type` char(1) DEFAULT NULL,
  PRIMARY KEY (`ppid`),
  UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
  PRIMARY KEY (`lab_id`,`procedure_code`,`question_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
  `date_end` datetime DEFAULT NULL COMMENT 'lab-provided end date specific to this result',
  PRIMARY KEY (`procedure_result_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `procedure_report_id` (`procedure_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Table structure for table `procedure_type`
--
CREATE TABLE IF NOT EXISTS `procedure_type` (
  `procedure_type_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `parent` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references procedure_type.procedure_type_id',
  `name` varchar(63) NOT NULL DEFAULT '' COMMENT 'name for this category, procedure or result type',
  `lab_id` bigint(20) NOT NULL DEFAULT 0 COMMENT 'references procedure_providers.ppid, 0 means default to parent',
  `procedure_code` varchar(64) NOT NULL DEFAULT '' COMMENT 'code identifying this procedure',
  `procedure_type` varchar(31) NOT NULL DEFAULT '' COMMENT 'see list proc_type',
  `body_site` varchar(31) DEFAULT '' COMMENT 'where to do injection, e.g. arm, buttock',
  `specimen` varchar(31) DEFAULT '' COMMENT 'blood, urine, saliva, etc.',
  `route_admin` varchar(31) DEFAULT '' COMMENT 'oral, injection',
  `laterality` varchar(31) DEFAULT '' COMMENT 'left, right, ...',
  `description` varchar(255) DEFAULT '' COMMENT 'descriptive text for procedure_code',
  `standard_code` varchar(255) DEFAULT '' COMMENT 'industry standard code type and code (e.g. CPT4:12345)',
  `related_code` varchar(255) DEFAULT '' COMMENT 'suggested code(s) for followup services if result is abnormal',
  `units` varchar(31) DEFAULT '' COMMENT 'default for procedure_result.units',
  `range` varchar(255) DEFAULT '' COMMENT 'default for procedure_result.range',
  `seq` int(11) DEFAULT 0 COMMENT 'sequence number for ordering',
  `activity` tinyint(1) DEFAULT 1 COMMENT '1=active, 0=inactive',
  `notes` varchar(255) DEFAULT '' COMMENT 'additional notes to enhance description',
  `transport` varchar(31) DEFAULT NULL,
  `procedure_type_name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`procedure_type_id`),
  KEY `parent` (`parent`),
  KEY `ptype_procedure_code` (`procedure_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;



--
-- Insert default lists and list values
--
REPLACE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`) VALUES
('lists', 'Lab_Billing', 'Lab Billing', 306, 1, 0, '', NULL, '', 0, 0, 1, '', 1, '2023-01-21 17:25:52'),
('lists', 'Lab_Category', 'Lab Category', 0, 1, 0, '', 'ID=appt code TITLE=orphan results', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_CPL_Accounts', 'Lab CPL Accounts', 307, 1, 0, '', NULL, '', 0, 0, 1, '', 1, '2023-01-26 14:19:45'),
('lists', 'Lab_Diagnosis', 'Lab Diagnosis', 0, 1, 0, '', 'Quick List', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Ethnicity', 'Lab Ethnicity', 0, 1, 0, '', 'ID=OEMR code TITLE=HL7 code', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Form_Status', 'Lab Form Status', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Label_Printers', 'Lab Label Printers', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Notification', 'Lab Notification', 0, 1, 0, '', 'Notify <nurse_username> instead of <doc_username>', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Quest_Accounts', 'Lab Quest Accounts', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Quest_Printers', 'Lab Quest Printers', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Quest_Sites', 'Lab Quest Sites', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Race', 'Lab Race', 0, 1, 0, '', 'ID=OEMR code TITLE=HL7 code', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Lab_Transport', 'Lab Transport', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'One_to_Nine', 'One to Nine', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32');

REPLACE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`) VALUES
('Lab_Billing', 'C', 'Bill Clinic', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-21 17:26:50'),
('Lab_Billing', 'P', 'Bill Patient', 10, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-21 17:26:50'),
('Lab_Billing', 'T', 'Bill Third-Party', 30, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-21 17:26:50'),
('Lab_Category', '12', 'Lab Results', 0, 0, 0, '', 'ID=appt code TITLE=orphan results', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_CPL_Accounts', '1', '89088', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-26 14:21:50'),
('Lab_Diagnosis', 'V70.6', 'Default', 0, 0, 0, '', 'ID=code TITLE=tab name NOTES=alternate name', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Ethnicity', 'hisp_or_latin', 'H', 1, 0, 0, '', 'ID = OEMR value', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Ethnicity', 'not_hisp_or_latino', 'N', 2, 0, 0, '', 'TITLE = HL7 value', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Ethnicity', 'unknown', 'U', 3, 0, 0, '', 'not provided', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 'f', 'Results Abnormal', 6, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 'g', 'Order Processed', 3, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 'i', 'Order Incomplete', 1, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 'n', 'Results Notification', 7, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 's', 'Order Submitted', 2, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 'v', 'Results Reviewed', 6, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 'x', 'Results Partial', 4, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Form_Status', 'z', 'Results Final', 5, 0, 0, '', 'DO NOT CHANGE', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Label_Printers', 'file', 'Print to file', 10, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-24 16:54:37'),
('Lab_Notification', 'admin', 'nurse', 0, 0, 0, '', 'Admin -> Lab Nurse', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Quest_Accounts', '12345678', 'General', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Quest_Printers', '127.0.0.2', 'Some Location', 20, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Quest_Sites', '3', '123456', 0, 1, 0, '', 'Primary Clinic', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Race', 'amer_ind_or_a', 'I', 1, 0, 0, '', 'ID = OEMR value', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Race', 'asian', 'A', 2, 0, 0, '', 'TITLE = HL7 value', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Race', 'black_or_afri_amer', 'B', 3, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Race', 'native_hawai_or_pac_island', 'C', 4, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Race', 'unknown', 'X', 6, 1, 0, '', 'not provided', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Race', 'white', 'C', 5, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'A', 'Ambient', 10, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'F', 'Frozen', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'FR/FZ', 'Frozen', 30, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'FZ', 'Frozen', 140, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'G', 'Refrigerated', 40, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'H', 'Handwritten', 50, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'M', 'Multiple Types', 60, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'PAP', 'Pathology', 150, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'R', 'Room Temperature', 70, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'RF', 'Refrigerated', 130, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'RT', 'Room Temperature', 120, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'S', 'Split Requisition', 80, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'W', 'Wet Ice', 100, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Lab_Transport', 'Z', 'Pain Management', 110, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'1', '1', 0, 0, 0, '', '', ''),
('One_to_Nine',	'2', '2', 0, 0, 0, '', '', ''),
('One_to_Nine',	'3', '3', 0, 0, 0, '', '', ''),
('One_to_Nine',	'4', '4', 0, 0, 0, '', '', ''),
('One_to_Nine',	'5', '5', 0, 0, 0, '', '', ''),
('One_to_Nine',	'6', '6', 0, 0, 0, '', '', ''),
('One_to_Nine',	'7', '7', 0, 0, 0, '', '', ''),
('One_to_Nine',	'8', '8', 0, 0, 0, '', '', ''),
('One_to_Nine',	'9', '9', 0, 0, 0, '', '', '');

--
-- VALIDATE THE FOLLOWING BY HAND!!!
--
--INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`) VALUES
--('lists', 'proc_type', 'Procedure Type', 0, 1, 0, '', '', ''),
--('proc_type', 'det', 'Item Details', 40, 0, 0, '', 'Discription of test', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('proc_type', 'fav', 'Favorite', 50, 0, 0, '', 'Display in favorite tab', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('proc_type', 'fgp', 'Custom Favorite Group', 50, 0, 0, '', NULL, '', 0, 0, 1, '', 1, '2023-01-11 21:50:07'),
--('proc_type', 'for', 'Custom Favorite Item', 60, 0, 0, '', NULL, '', 0, 0, 1, '', 1, '2023-01-11 21:50:07'),
--('proc_type', 'grp', 'Group Title', 10, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('proc_type', 'ord', 'Procedure', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('proc_type', 'pro', 'Profile Panel', 30, 0, 0, '', 'Panel (multiple tests)', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('proc_type', 'rec', 'Recommendation', 70, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('proc_type', 'res', 'Discrete Result', 60, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32');

--INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`) VALUES
--('lists', 'Processor_protocol', 'Processor_Protocol', 0, 1, 0, '', '', ''),
--('Processor_Protocol', 'FC2', 'sFTP Client 2.5.1', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Protocol', 'FS', 'Filesystem Standard', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Protocol', 'FS2', 'sFTP Server 2.5.1', 10, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Protocol', 'FSC', 'sFTP Client Custom', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Protocol', 'FSS', 'sFTP Server Custom', 10, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Protocol', 'INT', 'Internal Only', 90, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Protocol', 'SFTP', 'sFTP Client Standard', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Protocol', 'WS', 'Web Service', 50, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32');

--INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`) VALUES
--('lists', 'Processor_Type', 'Processor_Type', 0, 1, 0, '', '', ''),
--('Processor_Type', 'G', 'Generic Laboratory', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Type', 'I', 'Internal Laboratory', 10, 0, 0, '', 'In-House Procedures', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Type', 'L', 'LabCorp Laboratory', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
--('Processor_Type', 'Q', 'Quest Diagnostics', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32');

