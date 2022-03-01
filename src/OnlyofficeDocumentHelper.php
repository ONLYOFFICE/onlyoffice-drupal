<?php

namespace Drupal\onlyoffice_connector;

use \Drupal\onlyoffice_connector\OnlyofficeAppConfig;

class OnlyofficeDocumentHelper {

    public static function getExtension($filename): string {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function getDocumentType($ext) {
        $format = OnlyofficeAppConfig::getSupportedFormats()[$ext];
        if ($format) return $format["type"];

        return null;
    }

    public static function isEditable($ext) {
      $format = OnlyofficeAppConfig::getSupportedFormats()[$ext];

      return isset($format["edit"]) && $format["edit"];
    }

    public static function isFillForms($ext) {
      $format = OnlyofficeAppConfig::getSupportedFormats()[$ext];

      return isset($format["fillForms"]) && $format["fillForms"];
    }
}
