DROP TABLE IF EXISTS `phantom_identification`;
CREATE TABLE `phantom_identification` (
    `ID` INT(2) NOT NULL AUTO_INCREMENT,
    `Name` VARCHAR(255) NULL,
    `Type` ENUM('', 'Human', 'Lego', 'Test') NULL,
    `DoB` date default NULL,
    `Gender` enum('Male','Female') default NULL,
    PRIMARY KEY (`PhantomID`)
)ENGINE = InnoDB  DEFAULT CHARSET=utf8;

INSERT INTO phantom_identification (ID, Name, Type, DoB, Gender) VALUES (01, 'Phantom 1', 'Human','1970-01-01' , M);
INSERT INTO phantom_identification (ID, Name, Type, DoB, Gender) VALUES (02, 'Phantom 2', 'Human','1972-01-02' , F);
INSERT INTO phantom_identification (ID, Name, Type) VALUES (03, 'Lego SerialX', 'Lego');
INSERT INTO phantom_identification (ID, Name, Type) VALUES (04, 'Test 1', 'Test');

-- Move sub-items to refer to the proper Parent column which will be shifted by 1 to the left 
UPDATE LorisMenu SET Parent=Parent+1 WHERE Parent=(SELECT * FROM (SELECT ID FROM LorisMenu WHERE Label='Admin') as L);
UPDATE LorisMenu SET Parent=Parent+1 WHERE Parent=(SELECT * FROM (SELECT ID FROM LorisMenu WHERE Label='Tools') as L);
UPDATE LorisMenu SET Parent=Parent+1 WHERE Parent=(SELECT * FROM (SELECT ID FROM LorisMenu WHERE Label='Reports') as L);
UPDATE LorisMenu SET Parent=Parent+1 WHERE Parent=(SELECT * FROM (SELECT ID FROM LorisMenu WHERE Label='Imaging') as L);
UPDATE LorisMenu SET Parent=Parent+1 WHERE Parent=(SELECT * FROM (SELECT ID FROM LorisMenu WHERE Label='Clinical') as L);
UPDATE LorisMenu SET Parent=Parent+1 WHERE Parent=(SELECT * FROM (SELECT ID FROM LorisMenu WHERE Label='Members Portal') as L);

-- Move items in LorisMenu by 1 so Phantoms can be inserted at OrderNumber 2, now that child links are moved
UPDATE LorisMenu SET OrderNumber=OrderNUmber+1 WHERE Label='Admin';
UPDATE LorisMenu SET OrderNumber=OrderNumber+1 WHERE Label='Tools';
UPDATE LorisMenu SET OrderNumber=OrderNUmber+1 WHERE Label='Reports';
UPDATE LorisMenu SET OrderNumber=OrderNUmber+1 WHERE Label='Imaging';
UPDATE LorisMenu SET OrderNumber=OrderNUmber+1 WHERE Label='Clinical';
UPDATE LorisMenu SET OrderNumber=OrderNUmber+1 WHERE Label='Members Portal';


INSERT INTO LorisMenu (Label, OrderNumber) VALUES ('Phantom', 2);
INSERT INTO LorisMenu (Label, Link, Parent, OrderNumber) VALUES
    ('New Phantom Profile', 'new_phantom_profile/', (SELECT ID FROM LorisMenu as L WHERE Label='Phantom'), 1),
    ('Access Phantom Profile', 'phantom_list/', (SELECT ID FROM LorisMenu as L WHERE Label='Phantom'), 2);

--INSERT INTO permissions (code,description,categoryID) VALUES ('candidate_parameter_view','View Candidate Parameters',2);
--INSERT INTO permissions (code,description,categoryID) VALUES ('candidate_parameter_edit','Edit Candidate Parameters',2);

INSERT INTO LorisMenuPermissions (MenuID, PermID) 
    SELECT m.ID, p.PermID FROM permissions p CROSS JOIN LorisMenu m WHERE p.code='candidate_parameter_view' AND m.Label='Phantom';
INSERT INTO LorisMenuPermissions (MenuID, PermID) 
    SELECT m.ID, p.PermID FROM permissions p CROSS JOIN LorisMenu m WHERE p.code='candidate_parameter_edit' AND m.Label='Phantom';


ALTER TABLE candidate ADD COLUMN ScannerID int(6) default NULL;

INSERT INTO ConfigSettings (Name, Description, Visible, AllowMultiple, DataType, Parent, Label, OrderNumber) SELECT 'PhantomsFrontEndInsertion', 'Allows inserting phantoms from the front-end Phantom module', 1, 0, 'boolean', ID, 'Phantoms Front End Creation', 23 FROM ConfigSettings WHERE Name="study";
INSERT INTO Config (ConfigID, Value) SELECT ID, 0 FROM ConfigSettings WHERE Name="PhantomsFrontEndInsertion";

