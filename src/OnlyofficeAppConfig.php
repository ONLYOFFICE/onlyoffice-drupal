<?php

namespace Drupal\onlyoffice;

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
  private const DOC_SERVICE_API_URL = "web-apps/apps/api/documents/api.js";

  /**
   * Data about supported formats.
   *
   * @var array
   */
  private const SUPPORTED_FORMATS = [
    "djvu" => ["type" => "word"],
    "doc" => ["type" => "word"],
    "docm" => ["type" => "word"],
    "docx" => ["type" => "word", "edit" => TRUE],
    "dot" => ["type" => "word"],
    "dotm" => ["type" => "word"],
    "dotx" => ["type" => "word"],
    "epub" => ["type" => "word"],
    "fb2" => ["type" => "word"],
    "fodt" => ["type" => "word"],
    "html" => ["type" => "word"],
    "mht" => ["type" => "word"],
    "odt" => ["type" => "word"],
    "ott" => ["type" => "word"],
    "oxps" => ["type" => "word"],
    "pdf" => ["type" => "word"],
    "rtf" => ["type" => "word"],
    "txt" => ["type" => "word"],
    "xps" => ["type" => "word"],
    "xml" => ["type" => "word"],

    "csv" => ["type" => "cell"],
    "fods" => ["type" => "cell"],
    "ods" => ["type" => "cell"],
    "ots" => ["type" => "cell"],
    "xls" => ["type" => "cell"],
    "xlsm" => ["type" => "cell"],
    "xlsx" => ["type" => "cell", "edit" => TRUE],
    "xlt" => ["type" => "cell"],
    "xltm" => ["type" => "cell"],
    "xltx" => ["type" => "cell"],

    "fodp" => ["type" => "slide"],
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
