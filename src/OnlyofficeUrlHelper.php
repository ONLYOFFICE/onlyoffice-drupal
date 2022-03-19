<?php

namespace Drupal\onlyoffice_connector;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Firebase\JWT\JWT;

class OnlyofficeUrlHelper {

  public static function getDownloadFileUrl (File $file) {
    $linkParameters = [
      $file->uuid(),
      \Drupal::currentUser()->getAccount()->id()
    ];

    $key = static::signLinkParameters($linkParameters);

    return Url::fromRoute('onlyoffice_connector.download', ['key' => $key])->setAbsolute()->toString();
  }

  private static function signLinkParameters(array $parameters) {
    $payload = \implode('?', $parameters);

    $signature = JWT::sign($payload, Settings::getHashSalt() . \Drupal::service('private_key')->get());

    return JWT::urlsafeB64Encode($signature . '?' . $payload);
  }

  public static function verifyLinkKey($key) {
    $signature = JWT::urlsafeB64Decode($key);

    if ($signature) {
      $segments = \explode('?', $signature);

      $hash = $segments[0];
      $parameters = array_slice($segments, 1);

      if ($hash == JWT::sign(\implode('?', $parameters), Settings::getHashSalt() . \Drupal::service('private_key')->get())) {
          return $parameters;
      }
    }

    return false;
  }
}
