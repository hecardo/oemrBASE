-- This query script is executed when the OpenEMR interface's install button is clicked.


ALTER TABLE `patient_data` ADD `p_uuid` CHAR(36) NULL DEFAULT NULL COMMENT 'Veradigm Patient UUID' AFTER `id`;
ALTER TABLE `patient_data` ADD `add2veradigm` TINYINT NULL DEFAULT 0;
ALTER TABLE `patient_data` ADD `patient_type` CHAR(16) NULL DEFAULT NULL;

ALTER TABLE `patient_data` ADD INDEX `idx_PatientUUID` (`p_uuid`);


ALTER TABLE `users` ADD `add2veradigm` TINYINT NULL DEFAULT 0;
ALTER TABLE `users` ADD `veradigmUserUUID` CHAR(36) NULL DEFAULT NULL COMMENT 'Veradigm Provider UUID' AFTER `id`;
ALTER TABLE `users` ADD `veradigm_user_role` CHAR(32) NULL DEFAULT NULL;
ALTER TABLE `users` ADD `license_issue_state` CHAR(2) NULL DEFAULT NULL;
ALTER TABLE `users` ADD `state_expire_date` DATE NULL DEFAULT NULL;
ALTER TABLE `users` ADD `dea_schedule_2` CHAR(1) NULL DEFAULT 'N';
ALTER TABLE `users` ADD `dea_schedule_3` CHAR(1) NULL DEFAULT 'N';
ALTER TABLE `users` ADD `dea_schedule_4` CHAR(1) NULL DEFAULT 'N';
ALTER TABLE `users` ADD `dea_schedule_5` CHAR(1) NULL DEFAULT 'N';
ALTER TABLE `users` ADD `dea_expire_date` DATE NULL DEFAULT NULL;

ALTER TABLE `users` ADD INDEX `idx_users_veradigmUserUUID` (`veradigmUserUUID`);
ALTER TABLE `users` ADD INDEX `idx_veradigm_user_role` (`veradigm_user_role`);
ALTER TABLE `users` ADD INDEX `idx_dea_expire_date` (`dea_expire_date`);
ALTER TABLE `users` ADD INDEX `idx_state_expire_date` (`state_expire_date`);


ALTER TABLE `facility` ADD `add2veradigm` TINYINT NULL DEFAULT 0;
ALTER TABLE `facility` ADD `veradigmSiteUUID` CHAR(36) NULL COMMENT 'Veradigm Facility UUID' AFTER `id`;

ALTER TABLE `facility` ADD INDEX `idx_facility_veradigmSiteUUID` (`veradigmSiteUUID`);

INSERT IGNORE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`) VALUES
	('veradigm_erx_role','erxdoctor','Doctor or Physician','5','0','0','',''),
	('veradigm_erx_role','erxmidlevelWoSupervision','Midlevel Without Supervision','10','0','0','',''),
	('veradigm_erx_role','erxmidlevelWSupervision','Midlevel with Supervision Required','15','0','0','',''),
	('veradigm_erx_role','erxprescriberNoReview','Prescribe on Behalf of (No Review Required)','20','0','0','',''),
	('veradigm_erx_role','erxprescriberSomeReview','Prescribe on Behalf of (Some Review Required)','25','0','0','',''),
	('veradigm_erx_role','erxprescriberFullReview','Prescribe on Behalf of (All Review Required)','30','0','0','',''),
	('veradigm_erx_role','erxstaff','Staff','35','0','0','',''),
	('lists', 'veradigm_erx_role','Veradigm eRx Role','221','0','0','','');
