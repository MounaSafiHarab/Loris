# Imaging Uploader

## Purpose

The imaging uploader is intended to allow users to upload, browse, and track 
pipeline insertion progress.


## Intended Users

The primary users are MRI technicians or site coordinators to
upload imaging scans for registered LORIS candidates and timepoints.

## Scope

The imaging uploader has the following built-in capabilities to facilitate 
timely scan insertion into the LORIS database. Specifically, it allows to browse
uploaded scans using the `Browse` tab, upload imaging scans using the `Upload` 
tab, and track scan insertion status through the `Progress` column and a 
`Log Viewer` which displays in either `Summary` or `Detailed` mode relevant 
feedback messages about insertion success or failure causes.

Uploaded scans can be overwritten if insertion status is `Not Started` or 
`Failed` (i.e. if the `Progress` is neither `In Progress` nor `Success`). 


NOT in scope:

The imaging uploader does NOT read the DICOM files within the uploaded scans. 
As such, it does not check if the files within the uploaded scan are of the 
expected DICOM type, nor whether the  PatientName and/or PatientID DICOM headers 
are properly de-identified according to the LORIS convention. This check is 
however done as the first step on the LORIS-MRI side; i.e. as soon as the 
insertion pipeline is triggered.

## Imaging Uploader Requirements

For a successful upload:
- The uploaded file is expected to be of one of the following types: 
`.tgz`, `.tar.gz` or `.zip`.
- The filename should follow the:
`PSCID_CandID_VisitLabel_OptionalSuffix` naming convention
- It is expected that the candidate and visit are already created in the 
database.


## Permissions

#### Module Permission

The imaging uploader module uses one permission called `imaging_uploader` that 
is necessary to have access to the module and gives the user the ability to 
upload and browse all scans uploaded to the database.

#### Filesystem Permission

The path on the filesystem where the uploaded file go 
(see section [Database Configuration](#Database Configurations)) should be 
readable and writable by the web server. The LORIS-MRI install process makes it 
automatically group owned by the web server.


## Configurations

The imaging uploader has the following configurations that affect its usage:

#### Install Configurations

To enable the Imaging Uploader to handle large files, please update the 
`php.ini` apache configuration file with the following sample values: 

```
session.gc_maxlifetime = 10800
max_input_time = 10800
max_execution_time = 10800
upload_max_filesize = 1024M
post_max_size = 1024M
```

#### Database Configurations

ImagingUploaderAutoLaunch - This setting determines whether the insertion 
        pipeline that archives the images and is triggered automatically or 
        manually.

MRIUploadIncomingPath - This setting determines where on the filesystem the 
        uploader is to place the uploaded file. Default location is 
        `/data/incoming/`. This directory is created during the installation of 
        LORIS-MRI.


## Interactions with LORIS

- The `TarchiveInfo` column links to the DICOM Archive module for that scan
- The `Number of MincInserted` column links to the Imaging Browser module for 
that candidate's session 
- The `Number of MincCreated` column links to the MRI Violated scans module if
violated scans (i.e. scans that violate the MRI protocol as defined by the 
study) are present.
