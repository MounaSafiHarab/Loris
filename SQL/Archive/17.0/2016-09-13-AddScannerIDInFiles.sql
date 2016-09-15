-- Add ScannerCandID column to files, back-populate the newly added column from parameter_file table, then add foreign key constraints 
ALTER TABLE files ADD `ScannerID` int(11) DEFAULT NULL;
CREATE TEMPORARY TABLE ScannerIDs AS SELECT pf.FileID, pf.Value AS ScannerIDs FROM parameter_file AS pf LEFT JOIN parameter_type AS pt ON pf.ParameterTypeID=pt.ParameterTypeID WHERE pt.Name='ScannerID';
UPDATE files AS f, ScannerIDs AS S SET f.FileID=S.FileID where f.FileID=S.FileID;
ALTER TABLE files ADD CONSTRAINT `files_scannerID_fk` FOREIGN KEY (`ScannerID`) REFERENCES mri_scanner (`ID`);
