<?php

namespace Drupal\onlyoffice_connector;

use Drupal\Core\Site\Settings;

class OnlyofficeAppConfig {

  /**
   *  URL address to api.js
   *
   * @var string
   */
  private const DOC_SERVICE_API_URL = "web-apps/apps/api/documents/api.js";

  /**
   *  Data about supported formats
   *
   * @var array
   */
   private const SUPPORTED_FORMATS = [
    "djvu" => [ "type" => "word" ],
    "doc" => [ "type" => "word" ],
    "docm" => [ "type" => "word" ],
    "docx" => [ "type" => "word", "edit" => true ],
    "docxf" => [ "type" => "word", "edit" => true ],
    "dot" => [ "type" => "word" ],
    "dotm" => [ "type" => "word" ],
    "dotx" => [ "type" => "word" ],
    "epub" => [ "type" => "word" ],
    "fb2" => [ "type" => "word" ],
    "fodt" => [ "type" => "word" ],
    "html" => [ "type" => "word" ],
    "mht" => [ "type" => "word" ],
    "odt" => [ "type" => "word" ],
    "ott" => [ "type" => "word" ],
    "oxps" => [ "type" => "word" ],
    "pdf" => [ "type" => "word" ],
    "rtf" => [ "type" => "word" ],
    "txt" => [ "type" => "word" ],
    "xps" => [ "type" => "word" ],
    "xml" => [ "type" => "word" ],
    "oform" => [ "type" => "word", "fillForms" => true ],

    "csv" => [ "type" => "cell" ],
    "fods" => [ "type" => "cell" ],
    "ods" => [ "type" => "cell" ],
    "ots" => [ "type" => "cell" ],
    "xls" => [ "type" => "cell" ],
    "xlsm" => [ "type" => "cell" ],
    "xlsx" => [ "type" => "cell" ],
    "xlt" => [ "type" => "cell" ],
    "xltm" => [ "type" => "cell" ],
    "xltx" => [ "type" => "cell", "edit" => true ],

    "fodp" => [ "type" => "slide" ],
    "odp" => [ "type" => "slide" ],
    "otp" => [ "type" => "slide" ],
    "pot" => [ "type" => "slide" ],
    "potm" => [ "type" => "slide" ],
    "potx" => [ "type" => "slide" ],
    "pps" => [ "type" => "slide" ],
    "ppsm" => [ "type" => "slide" ],
    "ppsx" => [ "type" => "slide" ],
    "ppt" => [ "type" => "slide" ],
    "pptm" => [ "type" => "slide" ],
    "pptx" => [ "type" => "slide", "edit" => true ]
  ];

  public static function getDocServiceApiUrl() {
    return self::DOC_SERVICE_API_URL;
  }

  public static function getSupportedFormats() {
    return self::SUPPORTED_FORMATS;
  }

  public static function getJwtHeader() {
    return Settings::get('onlyoffice_jwt_header') ? Settings::get('onlyoffice_jwt_header') : "Authorization";
  }
}
