<?php

namespace Drupal\onlyoffice;

/**
 * Copyright (c) Ascensio System SIA 2025.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

use Drupal\Core\Site\Settings;

/**
 * Provides configuration for the ONLYOFFICE module.
 */
class OnlyofficeAppConfig {

  /**
   * Mobile regex from https://github.com/ONLYOFFICE/CommunityServer/blob/v9.1.1/web/studio/ASC.Web.Studio/web.appsettings.config#L35.
   *
   * @var string
   */
  public const USER_AGENT_MOBILE = "/android|avantgo|playbook|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\\/|plucker|pocket|psp|symbian|treo|up\\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i";

  /**
   * URL address to api.js.
   *
   * @var string
   */
  private const DOC_SERVICE_API_URL = "/web-apps/apps/api/documents/api.js";

  /**
   * Data about supported formats.
   *
   * @var array
   */
  private const SUPPORTED_FORMATS = [
    "doc" => ["type" => "word"],
    "docm" => ["type" => "word"],
    "docx" => ["type" => "word", "edit" => TRUE],
    "dot" => ["type" => "word"],
    "dotm" => ["type" => "word"],
    "dotx" => ["type" => "word"],
    "epub" => ["type" => "word"],
    "fb2" => ["type" => "word"],
    "fodt" => ["type" => "word"],
    "htm" => ["type" => "word"],
    "html" => ["type" => "word"],
    "hwp" => ["type" => "word"],
    "hwpx" => ["type" => "word"],
    "md" => ["type" => "word"],
    "mht" => ["type" => "word"],
    "mhtml" => ["type" => "word"],
    "odt" => ["type" => "word"],
    "ott" => ["type" => "word"],
    "pages" => ["type" => "word"],
    "rtf" => ["type" => "word"],
    "stw" => ["type" => "word"],
    "sxw" => ["type" => "word"],
    "txt" => ["type" => "word"],
    "wps" => ["type" => "word"],
    "wpt" => ["type" => "word"],
    "xml" => ["type" => "word"],

    "csv" => ["type" => "cell"],
    "et" => ["type" => "cell"],
    "ett" => ["type" => "cell"],
    "fods" => ["type" => "cell"],
    "numbers" => ["type" => "cell"],
    "ods" => ["type" => "cell"],
    "ots" => ["type" => "cell"],
    "sxc" => ["type" => "cell"],
    "xls" => ["type" => "cell"],
    "xlsb" => ["type" => "cell"],
    "xlsm" => ["type" => "cell"],
    "xlsx" => ["type" => "cell", "edit" => TRUE],
    "xlt" => ["type" => "cell"],
    "xltm" => ["type" => "cell"],
    "xltx" => ["type" => "cell"],

    "dps" => ["type" => "slide"],
    "dpt" => ["type" => "slide"],
    "fodp" => ["type" => "slide"],
    "key" => ["type" => "slide"],
    "odg" => ["type" => "slide"],
    "odp" => ["type" => "slide"],
    "otp" => ["type" => "slide"],
    "pot" => ["type" => "slide"],
    "potm" => ["type" => "slide"],
    "potx" => ["type" => "slide"],
    "pps" => ["type" => "slide"],
    "ppsm" => ["type" => "slide"],
    "ppsx" => ["type" => "slide"],
    "ppt" => ["type" => "slide"],
    "pptm" => ["type" => "slide"],
    "pptx" => ["type" => "slide", "edit" => TRUE],
    "sxi" => ["type" => "slide"],

    "djvu" => ["type" => "pdf"],
    "docxf" => ["type" => "pdf"],
    "oform" => ["type" => "pdf"],
    "oxps" => ["type" => "pdf"],
    "pdf" => ["type" => "pdf", "edit" => TRUE],
    "xps" => ["type" => "pdf"],

    "vsdx" => ["type" => "diagram"],
    "vsdm" => ["type" => "diagram"],
    "vssm" => ["type" => "diagram"],
    "vssx" => ["type" => "diagram"],
    "vstm" => ["type" => "diagram"],
    "vstx" => ["type" => "diagram"],
  ];

  /**
   * Address to api document service.
   */
  public static function getDocServiceApiUrl() {
    return self::DOC_SERVICE_API_URL;
  }

  /**
   * Supported document service formats.
   */
  public static function getSupportedFormats() {
    return self::SUPPORTED_FORMATS;
  }

  /**
   * Authorization header.
   */
  public static function getJwtHeader() {
    return Settings::get('onlyoffice_jwt_header') ? Settings::get('onlyoffice_jwt_header') : "Authorization";
  }

}
