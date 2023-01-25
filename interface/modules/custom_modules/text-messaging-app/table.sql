
CREATE TABLE IF NOT EXISTS `text_message_module` (
`id` int NOT NULL,
`fromnumber` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
`text` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
`date` datetime(6) NOT NULL
);
ALTER TABLE `text_message_module` ADD PRIMARY KEY(`id`);
ALTER TABLE `text_message_module` CHANGE `id` `id` INT NOT NULL AUTO_INCREMENT;
ALTER TABLE `text_message_module` ADD `provider_id` INT(5) NULL AFTER `id`;

CREATE TABLE IF NOT EXISTS `text_notification_log` (
`iLogId` int(11) NOT NULL,
`pid` bigint(20) NOT NULL,
`pc_eid` int(11) UNSIGNED DEFAULT NULL,
`sms_gateway_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`smsgateway_info` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`message` text COLLATE utf8mb4_unicode_ci,
`email_sender` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`email_subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`type` enum('SMS','Email') COLLATE utf8mb4_unicode_ci NOT NULL,
`patient_info` text COLLATE utf8mb4_unicode_ci,
`pc_eventDate` date NOT NULL,
`pc_endDate` date NOT NULL,
`pc_startTime` time NOT NULL,
`pc_endTime` time NOT NULL,
`dSentDateTime` datetime NOT NULL
);
ALTER TABLE `text_notification_log` ADD PRIMARY KEY(`iLogId`);
ALTER TABLE `text_notification_log` CHANGE `iLogId` `iLogId` INT NOT NULL AUTO_INCREMENT;

INSERT INTO `background_services` (`name`, `title`, `active`, `running`, `next_run`, `execute_interval`, `function`, `require_once`, `sort_order`) VALUES
    ('SMS_REMINDERS', 'SMS Appointment Reminders', 0, 0, '2022-01-18 11:25:10', 1440, 'start_sms_reminders', '/interface/modules/custom_modules/text-messaging-app/lib/sms_appointment_service.php', 100);

CREATE TABLE IF NOT EXISTS `text_notification_messages` (
    `id` int(5) NOT NULL,
    `cdr_category` int(3) NULL,
    `language` varchar(10) NULL,
    `message_content` varchar(255) NULL
);
ALTER TABLE `text_notification_messages` ADD PRIMARY KEY(`id`);
ALTER TABLE `text_notification_messages` CHANGE `id` `id` INT NOT NULL AUTO_INCREMENT;
