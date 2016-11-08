-- add this to the main schema
DROP TABLE IF EXISTS `NotifyAPI`;
CREATE TABLE IF NOT EXISTS `NotifyAPI` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `owner` varchar(255) NOT NULL,
    `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ProjectID` varchar(16) NOT NULL,
    `CandID` varchar(25) NOT NULL,
    `PSCID` varchar(256) NOT NULL,
    `Event` varchar(25) NOT NULL,
    `SourceIP` varchar(12) NOT NULL,
    `Status` varchar(8) NOT NULL,
    PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Dumping data for table `NotifyAPI`
--

LOCK TABLES `NotifyAPI` WRITE;
/*!40000 ALTER TABLE `NotifyAPI` DISABLE KEYS */;
/*!40000 ALTER TABLE `NotifyAPI` ENABLE KEYS */;
UNLOCK TABLES;

-- to config
INSERT INTO ConfigSettings (Name, Description, Visible, AllowMultiple, DataType, Parent, Label, OrderNumber) SELECT 'AcceptedExternalIP', 'Allows these comma separated IP addresses to issue a GET or a POST request', 1, 0, 'text', ID, 'Accepted IP addresses', 24 FROM ConfigSettings WHERE Name="study";
 
INSERT INTO Config (ConfigID, Value) SELECT ID, "132.206.37.36, 132.206.37.36" FROM ConfigSettings WHERE Name="AcceptedEXternalIP";
