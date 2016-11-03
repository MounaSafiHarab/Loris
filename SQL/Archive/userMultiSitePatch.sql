DROP TABLE IF EXISTS `user_psc_site`;
CREATE TABLE `user_psc_site` (
  `UserID` int(10) unsigned NOT NULL default '0',
  `CenterID` tinyint(2) unsigned default NULL,
  PRIMARY KEY  (`UserID`,`CenterID`),
  KEY `FK_user_psc_site_2` (`CenterID`),
--  CONSTRAINT `FK_user_psc_site_2` FOREIGN KEY (`CenterID`) REFERENCES `psc` (`CenterID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_user_psc_site_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user_psc_site`
--

LOCK TABLES `user_psc_site` WRITE, `psc` READ, `users` READ;
/*!40000 ALTER TABLE `user_psc_site` DISABLE KEYS */;
INSERT INTO `user_psc_site` (UserID, CenterID) SELECT ID, CenterID FROM users;
/*!40000 ALTER TABLE `user_psc_site` ENABLE KEYS */;
UNLOCK TABLES;

