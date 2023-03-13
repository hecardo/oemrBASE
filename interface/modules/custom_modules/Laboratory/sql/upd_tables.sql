--
-- Define procedure used to modify standard tables
--
DROP PROCEDURE IF EXISTS lab_table_update;

DELIMITER $$
CREATE PROCEDURE lab_table_update (
	IN in_database VARCHAR(32),
	IN in_table VARCHAR(32), 
	IN in_column VARCHAR(32), 
	IN in_alter VARCHAR(255)
)
BEGIN
	-- does column exist 
	SET @query = CONCAT("SELECT COUNT(*) INTO @output FROM information_schema.columns WHERE `table_schema` = '",in_database,"' AND `table_name` = '",in_table,"' AND `column_name` = '",in_column,"'");
	PREPARE statement FROM @query;
	EXECUTE statement;
	DEALLOCATE PREPARE statement;
	
	-- create updates
	IF @output = 0 
	THEN SET @update = CONCAT("ALTER TABLE `",in_table,"` ADD `",in_column,"` ",in_alter);
	ELSE SET @update = CONCAT("ALTER TABLE `",in_table,"` MODIFY `",in_column,"` ",in_alter);
	END IF;
	PREPARE statement FROM @update;
	EXECUTE statement;
	DEALLOCATE PREPARE statement;
END
$$

DELIMITER ;

--
-- Update standard tables as required
--
SET @db = database();

-- pocedure_answers
CALL lab_table_update(@db,"procedure_answers","procedure_code","VARCHAR(31)");

-- pocedure_orders
CALL lab_table_update(@db,"procedure_order","provider_id","BIGINT(20) DEFAULT 0");
CALL lab_table_update(@db,"procedure_order","patient_id","BIGINT(20) DEFAULT 0");
CALL lab_table_update(@db,"procedure_order","encounter_id","BIGINT(20) DEFAULT 0");
CALL lab_table_update(@db,"procedure_order","order_priority","varchar(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_order","order_status","varchar(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_order","control_id","VARCHAR(25) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","lab_id","BIGINT(20) DEFAULT 0");
CALL lab_table_update(@db,"procedure_order","specimen_type","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","specimen_volume","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","specimen_fasting","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","specimen_duration","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","specimen_transport","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","specimen_source","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","specimen_location","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","clinical_hx","VARCHAR(255) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_order","order_abn","VARCHAR(31) DEFAULT 'not required'");
CALL lab_table_update(@db,"procedure_order","collector_id","BIGINT(20) DEFAULT 0");
CALL lab_table_update(@db,"procedure_order","procedure_order_type","VARCHAR(31) DEFAULT 'laboratory_test'");
CALL lab_table_update(@db,"procedure_order","date_pending","DATE DEFAULT NULL");

-- proceduer_order_code
CALL lab_table_update(@db,"procedure_order_code","procedure_source","CHAR(1) DEFAULT '1'");
CALL lab_table_update(@db,"procedure_order_code","do_not_send","tinyint(1) DEFAULT 0");

-- procedure_providers
CALL lab_table_update(@db,"procedure_providers","remote_port","VARCHAR(5) DEFAULT '22'");

-- procedure_type
CALL lab_table_update(@db,"procedure_type","body_site","VARCHAR(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","specimun","VARCHAR(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","route_admin","VARCHAR(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","laterality","VARCHAR(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","description","VARCHAR(255) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","standard_code","VARCHAR(255) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","related_code","VARCHAR(255) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","units","VARCHAR(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","range","VARCHAR(31) DEFAULT ''");
CALL lab_table_update(@db,"procedure_type","seq","INT(11) DEFAULT 0");
CALL lab_table_update(@db,"procedure_type","activity","TINYINT(1) DEFAULT 1");
CALL lab_table_update(@db,"procedure_type","transport","VARCHAR(31) DEFAULT NULL");
CALL lab_table_update(@db,"procedure_type","notes","TEXT DEFAULT NULL");

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
('lists', 'One_to_Nine', 'One to Nine', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Processor_protocol', 'Processor_Protocol', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('lists', 'Processor_Type', 'Processor_Type', 0, 1, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32');

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
('One_to_Nine',	'1', '1', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'2', '2', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'3', '3', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'4', '4', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'5', '5', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'6', '6', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'7', '7', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'8', '8', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('One_to_Nine',	'9', '9', 0, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'FC2', 'sFTP Client 2.5.1', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'FS', 'Filesystem Standard', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'FS2', 'sFTP Server 2.5.1', 10, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'FSC', 'sFTP Client Custom', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'FSS', 'sFTP Server Custom', 10, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'INT', 'Internal Only', 90, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'SFTP', 'sFTP Client Standard', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Protocol', 'WS', 'Web Service', 50, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Type', 'G', 'Generic Laboratory', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Type', 'I', 'Internal Laboratory', 10, 0, 0, '', 'In-House Procedures', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Type', 'L', 'LabCorp Laboratory', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32'),
('Processor_Type', 'Q', 'Quest Diagnostics', 20, 0, 0, '', '', '', 0, 0, 1, '', 1, '2023-01-18 15:15:32');

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


