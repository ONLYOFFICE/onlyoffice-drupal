# Change Log

## Changed
- minimum supported drupal version is 10.3
- ONLYOFFICE Docs secret key must be at least 32 characters long
- ONLYOFFICE Docs address and secret key settings renamed and given descriptions
- fixed opening media in the editor without access to view it
- PDF forms are stored in the file system and directory of the file field of the ONLYOFFICE PDF form media type, and submitted forms in the file system of their form
- fixed the files of deleted form submissions left in the file system
- fixed the author of the revision saved after co-editing: it is the user who changed the document last, not the one who closed it last
- fixed PHP warnings and errors on callbacks from ONLYOFFICE Docs with the token only in the header or with missing or malformed fields
- fixed the missing configuration schema of the preview formatter, the ONLYOFFICE PDF form media source and the ONLYOFFICE form field

## Added
- addresses for internal requests between Drupal and ONLYOFFICE Docs in the advanced server settings
- authorization header setting
- connection check to ONLYOFFICE Docs when saving the settings
- welcome description with useful links on the settings page
- ONLYOFFICE Docs Cloud banner on the settings page
- expiration time for the download links
- saving a copy of the document from the editor
- plugins, macros and document protection settings
- editor customization settings: chat, compact header, feedback, help and theme
- setting to disable certificate verification of ONLYOFFICE Docs
- support for firebase/php-jwt 7
- status report warning about a short ONLYOFFICE Docs secret key
- status report warning when submitted forms are stored in the public file system
- support for drupal 12

## 3.0.0
## Added
- support for drupal 11

## 2.0.0
## Added
- new ONLYOFFICE Form submodule
- new ONLYOFFICE Form media type
- new ONLYOFFICE Form field type, widget and formatter
- creating and managing ONLYOFFICE forms and submissions
- editing pdf forms

## Changed
- fixed the problem when the file was saved empty if there was an error getting the file from the document server (https://www.drupal.org/project/onlyoffice/issues/3362004)
- remove support for drupal 9

## 1.0.5
## Changed
- code refactoring after Drupal review

## 1.0.4
## Changed
- code refactoring after Drupal review

## 1.0.3
## Changed
- update composer file

## 1.0.2
## Changed
- update dependency

## 1.0.1
## Changed
- refactoring code according to Drupal practice

## 1.0.0
## Added
- configuration page of plugin
- coediting docx, xlsx, pptx by authors
- embedded view djvu, doc, docm, dot, dotm, dotx, epub, fb2, fodt, html, mht,
  odt, ott, oxps, pdf, rtf, txt, xps, xml, csv, fods, ods, ots, xls, xlsm, xlt,
  xltm, xltx, fodp, odp, otp, pot, potm, potx, pps, ppsm, ppsx, ppt,
  pptm on public page
- JWT support
- detecting mobile browser
- set favicon on editor page
- add goBack url for document editor
