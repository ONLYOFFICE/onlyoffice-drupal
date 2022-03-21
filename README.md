# ![](images/icon.png) Module Drupal ONLYOFFICE connector

This module enables users to edit files in module Media from [Drupal](https://www.drupal.org) using ONLYOFFICE Docs packaged as Document Server - [Community or Enterprise Edition](#onlyoffice-docs-editions).

## Features

The module allows to:

* Edit text documents, spreadsheets, and presentations.
* File preview on public pages.
* Co-edit documents in real-time: use two co-editing modes (Fast and Strict).

Supported formats:

* For editing: DOCX, XLSX, PPTX, DOCXF.
* For filling out form: OFORM.
* For viewing only: DJVU, DOC, DOCM, DOT, DOTM, DOTX, EPUB, FB2, FODT, HTML, MHT, ODT, OTT, OXPS, PDF, RTF, TXT, XPS, XML, CSV, FODS, ODS, OTS, XLS, XLSM, XLSX, XLT, XLTM, FODP, ODP, OTP, POT, POTM, POTX, PPS, PPSM, PPSX, PPT, PPTM.

## Installing ONLYOFFICE Docs

You will need an instance of ONLYOFFICE Docs (Document Server) that is resolvable and connectable both from Nextcloud and any end clients. ONLYOFFICE Document Server must also be able to POST to Nextcloud directly.

ONLYOFFICE Document Server and Nextcloud can be installed either on different computers, or on the same machine. If you use one machine, set up a custom port for Document Server as by default both ONLYOFFICE Document Server and Nextcloud work on port 80.

You can install free Community version of ONLYOFFICE Docs or scalable Enterprise Edition with pro features.

To install free Community version, use [Docker](https://github.com/onlyoffice/Docker-DocumentServer) (recommended) or follow [these instructions](https://helpcenter.onlyoffice.com/installation/docs-community-install-ubuntu.aspx) for Debian, Ubuntu, or derivatives.

To install Enterprise Edition, follow instructions [here](https://helpcenter.onlyoffice.com/installation/docs-enterprise-index.aspx).

Community Edition vs Enterprise Edition comparison can be found [here](#onlyoffice-docs-editions).

To use ONLYOFFICE behind a proxy, please refer to [this article](https://helpcenter.onlyoffice.com/installation/docs-community-proxy.aspx).

You can also use our **[Docker installation](https://github.com/ONLYOFFICE/docker-onlyoffice-nextcloud)** to install pre-configured Document Server (free version) and Nextcloud with a couple of commands.

## Installing module ONLYOFFICE connector

Step 1: Add the module

First way: Add a module using Drupal's User Interface (easy) https://www.drupal.org/docs/extending-drupal/installing-modules#s-add-a-module-using-drupals-user-interface-easy

1. On the Admin toolbar project page on drupal.org (https://www.drupal.org/project), scroll to the Downloads section at the bottom of the page.
2. Copy the address of the tar.gz link. Depending on your device and browser, you might do this by right clicking and selecting Copy link address.
3. In the Manage administrative menu, navigate to Extend (admin/modules). The Extend page appears.
4. Click Install new module. The Install new module page appears.
5. In the field Install from a URL, paste the copied download link.
6. Click Install to upload and unpack the new module on the server. The files are being downloaded to the modules directory.

Second way: Add a module with Composer. https://www.drupal.org/docs/extending-drupal/installing-modules#s-add-a-module-with-composer
1. Enter the following command at the root of your site
```
composer require drupal/onlyoffice_connector
```

Step 2: Enable the module

First way: Using the Drupal User Interface (easy)
1. Navigate to the Extend page (admin/modules) via the Manage administrative menu
2. Locate the module ONLYOFFICE Connector and check the box
3. Click Install to enable

Second way: Or use the command line (advanced, but very efficient).
1. Run the following Drush command, giving the project name as a parameter:
```
drush pm:enable onlyoffice_connector
```
2. Follow the instructions on the screen.

## Configure a module ONLYOFFICE connector

In Drupal open the `~/config/system/onlyoffice-connector` page with administrative settings for **ONLYOFFICE** section.
Enter the address to connect ONLYOFFICE Document Server:

```
https://<documentserver>/
```

Where the **documentserver** is the name of the server with the ONLYOFFICE Document Server installed.
The address must be accessible for the user browser and from the Drupal server.
The Drupal server address must also be accessible from ONLYOFFICE Document Server for correct work.

To restrict the access to ONLYOFFICE Document Server and for security reasons and data integrity the encrypted signature is used.
Specify the _Secret key_ in the Drupal administrative configuration.
In the ONLYOFFICE Document Server [config file](https://api.onlyoffice.com/editors/signature/) specify the same secret key and enable the validation.

## How it works

The ONLYOFFICE integration follows the API documented here https://api.onlyoffice.com/editors/basic:

* When creating a new file, the user navigates to a document folder within Nextcloud and clicks the **Document**, **Spreadsheet** or **Presentation** item in the _new_ (+) menu.

* The browser invokes the `create` method in the `/lib/Controller/EditorController.php` controller.
This method adds the copy of the file from the assets folder to the folder the user is currently in.

* Or, when opening an existing file, the user navigates to it within Nextcloud and selects the **Open in ONLYOFFICE** menu option.

* A new browser tab is opened and the `index` method of the `/lib/Controller/EditorController.php` controller is invoked.

* The app prepares a JSON object with the following properties:

  * **url** - the URL that ONLYOFFICE Document Server uses to download the document;
  * **callbackUrl** - the URL that ONLYOFFICE Document Server informs about status of the document editing;
  * **documentServerUrl** - the URL that the client needs to respond to ONLYOFFICE Document Server (can be set at the administrative settings page);
  * **key** - the etag to instruct ONLYOFFICE Document Server whether to download the document again or not;

* Nextcloud takes this object and constructs a page from `templates/editor.php` template, filling in all of those values so that the client browser can load up the editor.

* The client browser makes a request for the javascript library from ONLYOFFICE Document Server and sends ONLYOFFICE Document Server the DocEditor configuration with the above properties.

* Then ONLYOFFICE Document Server downloads the document from Nextcloud and the user begins editing.

* ONLYOFFICE Document Server sends a POST request to the _callbackUrl_ to inform Nextcloud that a user is editing the document.

* When all users and client browsers are done with editing, they close the editing window.

* After [10 seconds](https://api.onlyoffice.com/editors/save#savedelay) of inactivity, ONLYOFFICE Document Server sends a POST to the _callbackUrl_  letting Nextcloud know that the clients have finished editing the document and closed it.

* Nextcloud downloads the new version of the document, replacing the old one.


## ONLYOFFICE Docs editions

ONLYOFFICE offers different versions of its online document editors that can be deployed on your own servers.

* Community Edition (`onlyoffice-documentserver` package)
* Enterprise Edition (`onlyoffice-documentserver-ee` package)

The table below will help you to make the right choice.

| Pricing and licensing | Community Edition | Enterprise Edition |
| ------------- | ------------- | ------------- |
| | [Get it now](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-community)  | [Start Free Trial](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-enterprise)  |
| Cost  | FREE  | [Go to the pricing page](https://www.onlyoffice.com/docs-enterprise-prices.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud)  |
| Simultaneous connections | up to 20 maximum  | As in chosen pricing plan |
| Number of users | up to 20 recommended | As in chosen pricing plan |
| License | GNU AGPL v.3 | Proprietary |
| **Support** | **Community Edition** | **Enterprise Edition** |
| Documentation | [Help Center](https://helpcenter.onlyoffice.com/installation/docs-community-index.aspx) | [Help Center](https://helpcenter.onlyoffice.com/installation/docs-enterprise-index.aspx) |
| Standard support | [GitHub](https://github.com/ONLYOFFICE/DocumentServer/issues) or paid | One year support included |
| Premium support | [Buy Now](https://www.onlyoffice.com/support.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud) | [Buy Now](https://www.onlyoffice.com/support.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud) |
| **Services** | **Community Edition** | **Enterprise Edition** |
| Conversion Service                | + | + |
| Document Builder Service          | + | + |
| **Interface** | **Community Edition** | **Enterprise Edition** |
| Tabbed interface                       | + | + |
| Dark theme                             | + | + |
| 150% scaling                           | + | + |
| White Label                            | - | - |
| Integrated test example (node.js)*     | - | + |
| Mobile web editors | - | + |
| Access to pro features via desktop     | - | + |
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
| Adding Content control          | - | + |
| Editing Content control         | + | + |
| Layout tools                    | + | + |
| Table of contents               | + | + |
| Navigation panel                | + | + |
| Mail Merge                      | + | + |
| Comparing Documents             | - | +* |
| **Spreadsheet Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Functions, formulas, equations  | + | + |
| Table templates                 | + | + |
| Pivot tables                    | + | + |
| Data validation                 | + | + |
| Conditional formatting  for viewing | +** | +** |
| **Presentation Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Transitions                     | + | + |
| Presenter mode                  | + | + |
| Notes                           | + | + |
| | [Get it now](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-community)  | [Start Free Trial](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-enterprise)  |

\*  It's possible to add documents for comparison from your local drive, from URL and from Nextcloud storage.

\** Support for all conditions and gradient. Adding/Editing capabilities are coming soon

