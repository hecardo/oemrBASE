--
-- Table structure for table `form_quest`
-- Labs 4.0 - Medical Technology Services
--
CREATE TABLE IF NOT EXISTS `form_laboratory` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `pid` bigint(20) NOT NULL,
  `user` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `groupname` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `status` varchar(16) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `order_number` varchar(225) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `lab_id` varchar(25) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `ins_primary` bigint(20) DEFAULT 0,
  `ins_secondary` bigint(20) DEFAULT 0,
  `order_type` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `order_notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `work_flag` char(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `work_insurance` varchar(25) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `work_date` date DEFAULT NULL,
  `work_employer` varchar(25) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `work_case` varchar(25) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `received_datetime` datetime DEFAULT NULL,
  `report_datetime` datetime DEFAULT NULL,
  `result_abnormal` int(5) DEFAULT 0,
  `reviewed_datetime` datetime DEFAULT NULL,
  `reviewed_id` bigint(20) DEFAULT NULL,
  `review_notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `notified_datetime` datetime DEFAULT NULL,
  `notified_id` bigint(20) DEFAULT NULL,
  `notified_person` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `patient_notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `order_abn_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `order_req_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `result_doc_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_idx` (`order_number`),
  KEY `pid_idx` (`pid`),
  KEY `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Table structure for table `form_orphans`
-- Labs 4.0 - Medical Technology Services
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


