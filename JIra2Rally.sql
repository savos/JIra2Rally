-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.1.8-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win32
-- HeidiSQL Verzija:             9.1.0.4867
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Dumping database structure for jira_rally
CREATE DATABASE IF NOT EXISTS `jira_rally` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `jira_rally`;


-- Dumping structure for table jira_rally.attachments
CREATE TABLE IF NOT EXISTS `attachments` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `jira_object_ID` varchar(16) DEFAULT NULL,
  `jira_attachment_content_url` varchar(255) DEFAULT NULL,
  `jira_attachment_mimeType` varchar(32) DEFAULT NULL,
  `jira_attachment_size` int(11) DEFAULT NULL,
  `jira_attachment_created` varchar(64) DEFAULT NULL,
  `jira_attachment_filename` varchar(64) DEFAULT NULL,
  `rally_object_ID` varchar(16) DEFAULT NULL,
  `rally_object_url` varchar(255) DEFAULT NULL,
  `rally_attachment_content_url` varchar(255) DEFAULT NULL,
  `rally_attachment_mimeType` varchar(255) DEFAULT NULL,
  `rally_attachment_size` varchar(255) DEFAULT NULL,
  `rally_attachment_created` int(11) DEFAULT NULL,
  `rally_attachment_filename` varchar(16) DEFAULT NULL,
  `action` enum('NONE','ADD','DELETE','UPDATE') DEFAULT 'NONE',
  `system` enum('NONE','JIRA','RALLY') DEFAULT 'NONE',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2015 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Dumping data for table jira_rally.attachments: ~0 rows (approximately)
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;


-- Dumping structure for table jira_rally.attributes
CREATE TABLE IF NOT EXISTS `attributes` (
  `jira_parameter` varchar(32) DEFAULT NULL,
  `rally_parameter` varchar(32) DEFAULT NULL,
  `jira_attribute` varchar(255) DEFAULT NULL,
  `rally_attribute` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Dumping data for table jira_rally.attributes: ~22 rows (approximately)
/*!40000 ALTER TABLE `attributes` DISABLE KEYS */;
INSERT INTO `attributes` (`jira_parameter`, `rally_parameter`, `jira_attribute`, `rally_attribute`) VALUES
	('status', 'state', 'Assign to Developer', 'Submitted'),
	('status', 'state', 'In Progress', 'Open'),
	('status', 'state', 'Pending QA Approval', 'QA in Progress'),
	('status', 'state', 'Ready to Release to QA', 'Ready to Ship'),
	('status', 'state', 'Ready to Release to Staging', 'Ready to Ship'),
	('status', 'state', 'Ready to Release to Production', 'Ready to Ship'),
	('status', 'state', 'Released', 'Fixed'),
	('status', 'state', 'Closed', 'Closed'),
	('status', 'state', 'Fixed', 'Fixed'),
	('environment', 'environment', 'None', 'Development'),
	('environment', 'environment', 'QA', 'Test'),
	('environment', 'environment', 'Staging', 'Staging'),
	('environment', 'environment', 'Production', 'Production'),
	('priority', 'priority', 'P1', 'P0 - Emergency'),
	('priority', 'priority', 'P2', 'P1 - Resolve Soon'),
	('priority', 'priority', 'P3', 'P2 - High Attention'),
	('priority', 'priority', 'P4', 'P3 - Normal'),
	('priority', 'priority', 'P5', 'P4 - Low'),
	('severity', 'severity', 'blocker', 'S0 - Critical'),
	('severity', 'severity', 'major', 'S1 - Major'),
	('severity', 'severity', 'None', 'S3 - Minor'),
	('severity', 'severity', 'minor', 'S3 - Minor');
/*!40000 ALTER TABLE `attributes` ENABLE KEYS */;


-- Dumping structure for table jira_rally.jira
CREATE TABLE IF NOT EXISTS `jira` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `jira_object_ID` varchar(16) NOT NULL,
  `jira_object_url` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `project` varchar(32) NOT NULL,
  `status` varchar(64) NOT NULL,
  `attachment` text NOT NULL,
  `environment` text NOT NULL,
  `priority` varchar(64) NOT NULL,
  `severity` varchar(32) NOT NULL,
  `reporter` varchar(64) NOT NULL,
  `created` varchar(64) NOT NULL,
  `updated` varchar(64) NOT NULL,
  `browsers` text NOT NULL,
  `steps_to_reproduce` text NOT NULL,
  `current_results` text NOT NULL,
  `expected_results` text NOT NULL,
  `tested_on_url` varchar(255) NOT NULL,
  `login_information` varchar(255) NOT NULL,
  `exists_on_rally` enum('TRUE','FALSE') NOT NULL,
  `update_on_rally` enum('TRUE','FALSE') NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3760 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Dumping data for table jira_rally.jira: ~0 rows (approximately)
/*!40000 ALTER TABLE `jira` DISABLE KEYS */;
/*!40000 ALTER TABLE `jira` ENABLE KEYS */;


-- Dumping structure for table jira_rally.map
CREATE TABLE IF NOT EXISTS `map` (
  `ID` int(11) DEFAULT NULL,
  `jira_key` varchar(32) DEFAULT NULL,
  `jira_value` varchar(32) DEFAULT NULL,
  `jira_path` varchar(255) DEFAULT NULL,
  `jira_type` enum('ARRAY','VALUE') DEFAULT NULL,
  `transform` enum('NONE','DIRECT','ATTRIBUTE','CODE') DEFAULT NULL,
  `rally_key` varchar(32) DEFAULT NULL,
  `rally_value` varchar(32) DEFAULT NULL,
  `rally_path` varchar(255) DEFAULT NULL,
  `rally_type` enum('ARRAY','VALUE') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Dumping data for table jira_rally.map: ~21 rows (approximately)
/*!40000 ALTER TABLE `map` DISABLE KEYS */;
INSERT INTO `map` (`ID`, `jira_key`, `jira_value`, `jira_path`, `jira_type`, `transform`, `rally_key`, `rally_value`, `rally_path`, `rally_type`) VALUES
	(1, 'jira_object_ID', NULL, 'key', 'VALUE', 'DIRECT', 'jira_object_ID', NULL, NULL, 'VALUE'),
	(2, 'jira_object_url', NULL, 'self', 'VALUE', 'NONE', 'rally_object_url', NULL, 'Defect', 'VALUE'),
	(3, 'summary', 'Summary', 'fields-summary', 'VALUE', 'DIRECT', 'name', 'Name', 'Defect-Name', 'VALUE'),
	(4, 'project', 'Project ', 'fields-project-name', 'VALUE', 'DIRECT', 'project', 'Project', 'Defect-Project-_refObjectName', 'VALUE'),
	(5, 'status', 'Status ', 'fields-status-name', 'VALUE', 'ATTRIBUTE', 'state', 'State', 'Defect-state', 'VALUE'),
	(6, 'attachment', 'Attachments ', 'fields-attachment', 'ARRAY', 'NONE', 'attachments', 'Attachments', 'Defect-Attachments-_ref', 'ARRAY'),
	(7, 'environment', 'Environment', 'fields-customfield_10414-value', 'ARRAY', 'CODE', 'environment', 'Environment', 'Defect-Environment', 'VALUE'),
	(8, 'priority', 'Priority', 'fields-priority-name', 'VALUE', 'ATTRIBUTE', 'priority', 'Priority', 'Defect-Priority', 'VALUE'),
	(9, 'severity', 'Severity', 'fields-customfield_10020-value', 'VALUE', 'ATTRIBUTE', 'severity', 'Severity', 'Defect-Severity', 'VALUE'),
	(10, 'reporter', 'Reporter ', 'fields-reporter-displayName', 'VALUE', 'DIRECT', 'submitted_by', 'Submitted By', 'Defect-SubmittedBy-_ref', 'VALUE'),
	(11, 'created', 'Created', 'fields-created', 'VALUE', 'NONE', 'creation_date', 'Creation Date', 'Defect-CreationDate', 'VALUE'),
	(12, 'updated', 'Updated', 'fields-updated', 'VALUE', 'NONE', 'updated', NULL, 'Defect-LastUpdateDate', 'VALUE'),
	(13, 'browsers', 'Browsers', 'fields-customfield_10416-value', 'ARRAY', 'CODE', 'found_in', 'Found In Build', 'Defect-FoundInBuild', 'VALUE'),
	(14, 'steps_to_reproduce', 'Steps to Reproduce', 'fields-customfield_10010', 'VALUE', 'CODE', 'description', 'Description', 'Defect-Description', 'VALUE'),
	(15, 'current_results', 'Current Results', 'fields-customfield_10055', 'VALUE', 'NONE', 'notes', 'Notes', 'Defect-Notes', 'VALUE'),
	(16, 'expected_results', 'Expected Results', 'fields-customfield_10054', 'VALUE', 'NONE', 'notes', 'Notes', 'Defect-Notes', 'VALUE'),
	(17, 'tested_on_url', 'Tested on URL', 'fields-customfield_11407', 'VALUE', 'NONE', 'notes', 'Notes', 'Defect-Notes', 'VALUE'),
	(18, 'login_information', 'Login information', 'fields-customfield_10059', 'VALUE', 'NONE', 'notes', 'Notes', 'Defect-Notes', 'VALUE'),
	(19, NULL, NULL, NULL, NULL, 'NONE', 'rally_object_id', NULL, 'Defect-ObjectID', NULL),
	(20, 'exists_on_rally', NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL),
	(21, 'update_on_rally', NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL);
/*!40000 ALTER TABLE `map` ENABLE KEYS */;


-- Dumping structure for table jira_rally.rally
CREATE TABLE IF NOT EXISTS `rally` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `jira_object_ID` varchar(16) DEFAULT NULL,
  `rally_object_ID` varchar(255) DEFAULT NULL,
  `rally_object_url` varchar(255) DEFAULT NULL,
  `name` text,
  `project` varchar(255) DEFAULT NULL,
  `state` varchar(64) DEFAULT NULL,
  `attachments` text,
  `environment` text,
  `priority` varchar(64) DEFAULT NULL,
  `severity` varchar(32) DEFAULT NULL,
  `submitted_by` varchar(64) DEFAULT NULL,
  `creation_date` varchar(64) DEFAULT NULL,
  `updated` varchar(64) DEFAULT NULL,
  `found_in` text,
  `description` text,
  `notes` text,
  `exists_in_jira` enum('TRUE','FALSE') DEFAULT NULL,
  `update_in_jira` enum('TRUE','FALSE') DEFAULT NULL,
  `action` enum('NONE','UPDATE','INSERT') NOT NULL,
  `transfer_to_rally` enum('TRUE','FALSE') NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2575 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- Dumping data for table jira_rally.rally: ~0 rows (approximately)
/*!40000 ALTER TABLE `rally` DISABLE KEYS */;
/*!40000 ALTER TABLE `rally` ENABLE KEYS */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
