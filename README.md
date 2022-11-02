# ONLYOFFICE Connector module for Drupal

Contents of this file
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Usage
 * Installing ONLYOFFICE Docs
 
## Introduction

The ONLYOFFICE module enables users to edit files in the Media module from [Drupal](https://www.drupal.org) using ONLYOFFICE Docs packaged as Document Server - [Community or Enterprise Edition](#installing-onlyoffice-docs).

The module allows to:

* Edit text documents, spreadsheets, and presentations.
* Preview files on public pages.
* Collaborate on documents using two co-editing modes (real-time and 
paragraph-locking).

Supported formats:

* For editing: DOCX, XLSX, PPTX.
* For viewing only: DJVU, DOC, DOCM, DOT, DOTM, DOTX, EPUB, FB2, FODT, HTML, 
MHT, ODT, OTT, OXPS, PDF, RTF, TXT, XPS, XML, CSV, FODS, ODS, OTS, XLS, 
XLSM, XLT, XLTM, XLTX, FODP, ODP, OTP, POT, POTM, POTX, PPS, PPSM, PPSX,
PPT, PPTM.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/onlyoffice

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/onlyoffice .
   Alternatively, you can contact ONLYOFFICE team on [forum.onlyoffice.com](https://forum.onlyoffice.com/).

## Requirements

This module requires no modules outside of Drupal core.

## Installation

**Step 1: Add the module**

First way: Add a module using [Drupal's User Interface (easy)](https://www.drupal.org/docs/extending-drupal/installing-modules#s-add-a-module-using-drupals-user-interface-easy).

1. On the Admin toolbar project page on drupal.org 
(https://www.drupal.org/project), scroll to the Downloads section 
at the bottom of the page.
2. Copy the address of the tar.gz link. Depending on your device and 
browser, you might do this by right clicking and selecting Copy link address.
3. In the Manage administrative menu, navigate to Extend (admin/modules).
The Extend page appears.
4. Click Install new module. The Install new module page appears.
5. In the field Install from a URL, paste the copied download link.
6. Click Install to upload and unpack the new module on the server. 
The files are being downloaded to the modules directory.

Second way: Add a module with [Composer](https://www.drupal.org/docs/extending-drupal/installing-modules#s-add-a-module-with-composer).

Enter the following command at the root of your site:

```
composer require drupal/onlyoffice
```

**Step 2: Enable the module**

First way: Using the Drupal User Interface (easy).
1. Navigate to the Extend page (admin/modules) via the Manage 
administrative menu.
2. Locate the ONLYOFFICE Connector module and check the box.
3. Click Install to enable.

Second way: Using the command line (advanced, but very efficient).
1. Run the following Drush command, giving the project name as a parameter:

```
drush pm:enable onlyoffice
```
2. Follow the instructions on the screen.

## Configuration

In Drupal, open the `~/config/system/onlyoffice-settings` page with 
administrative settings for **ONLYOFFICE** section.
Enter the address to connect ONLYOFFICE Document Server:

```
https://<documentserver>/
```

Where **documentserver** is the name of the server with the ONLYOFFICE 
Document Server installed.
The address must be accessible for the user browser and from the Drupal server.
The Drupal server address must also be accessible from ONLYOFFICE 
Document Server for correct work.

Starting from version 7.2, JWT is enabled by default and the secret key is generated automatically to restrict the access to ONLYOFFICE Docs and for security reasons and data integrity. 
Specify your own **Secret key** in the Drupal administrative configuration. 
In the ONLYOFFICE Docs [config file](https://api.onlyoffice.com/editors/signature/), specify the same secret key and enable the validation.

## Usage

**Edit files already uploaded to Drupal**

All office files added to Media can be opened for editing. In the last
table column, call the drop-down list and select the Edit in ONLYOFFICE action. 
The editor opens in the same tab. Users with Administrator rights are able 
to co-edit files using ONLYOFFICE Docs. All changes are saved in the same file.

**Create new posts**

When creating a post, you can add the new ONLYOFFICE element.

1. Go to Structure -> Content types -> Manage fields. On the opened page, click
*Add field*. Add a new field: File or Media. Set the label and save.

2. For the added File field, specify the file extensions. Go to Structure ->
Content types -> Manage fields. In the *Allowed file extensions* field, specify
the file formats that will be shown in the editors (docx,xlsx,pptx).

   For the added Media field, click the Document checkbox.

3. Go to Structure -> Media types -> Document -> Manage display.

   For the Document field, specify the *ONLYOFFICE Preview* format. By clicking 
on the gear symbol, you can specify the dimensions of the embedded editor 
window.

When you are done with the pre-settings, you can create posts on the Content 
tab. Click on the *Add Content* button and select the created content. 

Specify title and select a file (if the content contains File fields).

For Media section, specify the name of the previously uploaded file.

Your site visitors will also be able to view the created page
(People -> Permissions -> View published content).

## Installing ONLYOFFICE Docs

You will need an instance of ONLYOFFICE Docs (Document Server) that 
is resolvable and connectable both from Drupal and any end clients. 
ONLYOFFICE Document Server must also be able to POST to Drupal directly.

You can install free Community version of ONLYOFFICE Docs or scalable
Enterprise Edition.

To install free Community version, use [Docker](https://github.com/onlyoffice/Docker-DocumentServer) (recommended) or follow [these instructions](https://helpcenter.onlyoffice.com/installation/docs-community-install-ubuntu.aspx) for Debian, Ubuntu, or derivatives.

To install Enterprise Edition, follow the instructions [here](https://helpcenter.onlyoffice.com/installation/docs-enterprise-index.aspx).

**ONLYOFFICE Docs** packaged as Document Server: 

* Community Edition (`onlyoffice-documentserver` package)
* Enterprise Edition (`onlyoffice-documentserver-ee` package)

The table below will help you make the right choice.

| Pricing and licensing | Community Edition | Enterprise Edition |
| ------------- | ------------- | ------------- |
| | [Get it now](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubDrupal#docs-community)  | [Start Free Trial](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubDrupal#docs-enterprise)  |
| Cost  | FREE  | [Go to the pricing page](https://www.onlyoffice.com/docs-enterprise-prices.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubDrupal)  |
| Simultaneous connections | up to 20 maximum  | As in chosen pricing plan |
| Number of users | up to 20 recommended | As in chosen pricing plan |
| License | GNU AGPL v.3 | Proprietary |
| **Support** | **Community Edition** | **Enterprise Edition** |
| Documentation | [Help Center](https://helpcenter.onlyoffice.com/installation/docs-community-index.aspx) | [Help Center](https://helpcenter.onlyoffice.com/installation/docs-enterprise-index.aspx) |
| Standard support | [GitHub](https://github.com/ONLYOFFICE/DocumentServer/issues) or paid | One year support included |
| Premium support | [Contact us](mailto:sales@onlyoffice.com) | [Contact us](mailto:sales@onlyoffice.com) |
| **Services** | **Community Edition** | **Enterprise Edition** |
| Conversion Service                | + | + |
| Document Builder Service          | + | + |
| **Interface** | **Community Edition** | **Enterprise Edition** |
| Tabbed interface                       | + | + |
| Dark theme                             | + | + |
| 125%, 150%, 175%, 200% scaling         | + | + |
| White Label                            | - | - |
| Integrated test example (node.js)      | + | + |
| Mobile web editors                     | - | +* |
| **Plugins & Macros** | **Community Edition** | **Enterprise Edition** |
| Plugins                           | + | + |
| Macros                            | + | + |
| **Collaborative capabilities** | **Community Edition** | **Enterprise Edition** |
| Two co-editing modes              | + | + |
| Comments                          | + | + |
| Built-in chat                     | + | + |
| Review and tracking changes       | + | + |
| Display modes of tracking changes | + | + |
| Version history                   | + | + |
| **Document Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Adding Content control          | + | + | 
| Editing Content control         | + | + | 
| Layout tools                    | + | + |
| Table of contents               | + | + |
| Navigation panel                | + | + |
| Mail Merge                      | + | + |
| Comparing Documents             | + | + |
| **Spreadsheet Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Functions, formulas, equations  | + | + |
| Table templates                 | + | + |
| Pivot tables                    | + | + |
| Data validation           | + | + |
| Conditional formatting          | + | + |
| Sparklines                   | + | + |
| Sheet Views                     | + | + |
| **Presentation Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Transitions                     | + | + |
| Presenter mode                  | + | + |
| Notes                           | + | + |
| **Form creator features** | **Community Edition** | **Enterprise Edition** |
| Adding form fields           | + | + |
| Form preview                    | + | + |
| Saving as PDF                   | + | + |
| | [Get it now](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubDrupal#docs-community)  | [Start Free Trial](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubDrupal#docs-enterprise)  |

\* If supported by DMS.
