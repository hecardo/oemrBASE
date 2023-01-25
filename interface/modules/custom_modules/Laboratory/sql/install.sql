-- Add lab_type to the procedure_provider table	










#IfNotTable procedure_batch
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
#EndIf

#IfNotTable procedure_facility
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
#EndIf

