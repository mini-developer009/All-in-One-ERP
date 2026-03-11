# module structure upgrade as of 21/01/2023
ALTER TABLE `0_employee` ADD COLUMN `national_id` varchar(100) DEFAULT NULL AFTER `emp_birthdate`;
ALTER TABLE `0_employee` ADD COLUMN `passport` varchar(100) DEFAULT NULL AFTER `national_id`;
ALTER TABLE `0_employee` ADD COLUMN `bank_account` varchar(100) DEFAULT NULL AFTER `passport`;
ALTER TABLE `0_employee` ADD COLUMN `tax_number` varchar(100) DEFAULT NULL AFTER `bank_account`;
ALTER TABLE `0_employee`
	CHANGE `salary_scale_id` `position_id` int(11) NOT NULL DEFAULT '0',
	DROP KEY `salary_scale_id`,
	ADD  KEY `position_id` (`position_id`);
ALTER TABLE `0_employee` ADD COLUMN `grade_id` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' AFTER `position_id`;
ALTER TABLE `0_employee` ADD COLUMN `personal_salary` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `grade_id`;

ALTER TABLE `0_department` ADD COLUMN `basic_account`  varchar(15) NULL AFTER `dept_name`;

ALTER TABLE  `0_salaryscale`;
ALTER TABLE  `0_salaryscale` CHANGE `scale_id` `position_id` int(11) NOT NULL AUTO_INCREMENT,
     DROP PRIMARY KEY,
     ADD PRIMARY KEY (`position_id`);
ALTER TABLE  `0_salaryscale` CHANGE `scale_name` `position_name` text NOT NULL;
ALTER TABLE `0_salaryscale` RENAME `0_position`;

ALTER TABLE `0_attendance` CHANGE `hours_no` `hours_no` float(5) UNSIGNED NOT NULL;

ALTER TABLE `0_employee_trans` CHANGE `trans_no` `trans_no` int(11) UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `0_employee_trans` CHANGE `payslip_no` `payslip_no` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `0_payroll_account` CHANGE `account_id` `element_id` int(11) NOT NULL AUTO_INCREMENT,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`element_id`),
    ADD UNIQUE KEY (`account_code`);

ALTER TABLE `0_payroll_account` ADD `element_name` varchar(100) NOT NULL DEFAULT '' AFTER `element_id`;
ALTER TABLE `0_payroll_account` CHANGE `account_code` `account_code` varchar(15) NOT NULL;
ALTER TABLE `0_payroll_account` RENAME `0_pay_element`;


ALTER TABLE `0_payroll_structure` CHANGE `salary_scale_id`  `position_id` int(11) NOT NULL,
    DROP KEY `salary_scale_id`,
    ADD KEY `position_id` (`position_id`);

ALTER TABLE `0_salary_structure` CHANGE `salary_scale_id`  `position_id` int(11) NOT NULL;
ALTER TABLE `0_salary_structure` ADD COLUMN `grade_id` tinyint(2) NOT NULL DEFAULT '0' AFTER `position_id`;

DROP TABLE IF EXISTS `0_personal_salary_structure`;
CREATE TABLE IF NOT EXISTS `0_personal_salary_structure` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`date` date NOT NULL,
	`emp_id` int(11) NOT NULL,
	`pay_rule_id` varchar(15) NOT NULL,
	`pay_amount` double NOT NULL,
	`type` tinyint(1) NOT NULL COMMENT '0 for credit, 1 for debit',
	`is_basic` tinyint(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB;

ALTER TABLE `0_employee_trans` ADD COLUMN `trans_type` smallint(6) UNSIGNED NOT NULL DEFAULT '0' AFTER `trans_no`;

DROP TABLE IF EXISTS `0_employee_advance`;
CREATE TABLE IF NOT EXISTS `0_employee_advance` (
	`emp_trans_no` int(11) NOT NULL,
	`emp_id` int(11) NOT NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `0_employee_advance_allocation`;
CREATE TABLE IF NOT EXISTS `0_employee_advance_allocation` (
	`trans_no_from` int(11) DEFAULT NULL,
	`trans_no_to` int(11) DEFAULT NULL,
	`amount` double UNSIGNED DEFAULT NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `0_document_types`;
CREATE TABLE IF NOT EXISTS `0_document_types` (
	`type_id` int(11) NOT NULL AUTO_INCREMENT,
	`type_name` varchar(100) NOT NULL,
	`notify_before` smallint(5) UNSIGNED NOT NULL,
	`inactive` tinyint(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`type_id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `0_employee_docs`;
CREATE TABLE IF NOT EXISTS `0_employee_docs` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`emp_id` int(11) NOT NULL,
	`type_id` int(11) NOT NULL,
	`description` varchar(60) NOT NULL default '',
	`issue_date` date DEFAULT NULL,
	`expiry_date` date DEFAULT NULL,
	`alert` tinyint(1) NOT NULL DEFAULT '0',
	`unique_name` varchar(60) NOT NULL default '',
	`filename` varchar(60) NOT NULL default '',
	`filesize` int(11) NOT NULL default '0',
	`filetype` varchar(60) NOT NULL default '',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `0_grade_table`;
CREATE TABLE IF NOT EXISTS `0_grade_table` (
	`grade_id` tinyint(2) NOT NULL,
	`position_id` int(11) NOT NULL,
	`amount` double NOT NULL DEFAULT '0',
	PRIMARY KEY (`grade_id`, `position_id`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `0_leave`;
CREATE TABLE IF NOT EXISTS `0_leave` (
	`emp_id` int(11) NOT NULL,
	`leave_id` int(11) NOT NULL,
	`pay_rate` float(5) NOT NULL DEFAULT '1',
	`date` date NOT NULL,
	PRIMARY KEY (`emp_id`,`leave_id`,`date`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `0_leave_type`;
CREATE TABLE IF NOT EXISTS `0_leave_type` (
	`leave_id` int(11) NOT NULL AUTO_INCREMENT,
	`leave_name` varchar(100) NOT NULL,
	`leave_code` varchar(3) NOT NULL,
	`pay_rate` float(5) NOT NULL,
	`inactive` tinyint(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`leave_id`)
) ENGINE=InnoDB;

INSERT IGNORE INTO `0_sys_prefs` VALUES
 ('payroll_dept_based', NULL, 'tinyint', 1, 0),
 ('payroll_grades', NULL, 'tinyint', 2, 5);
