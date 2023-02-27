<?php

namespace Drupal\onlyoffice;

use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Firebase\JWT\JWT;

/**
 * URL generation toolkit.
 */
class OnlyofficeUrlHelper {

  /**
   * Return URL to Document Editor.
   */
  public static function getEditorUrl(Media $media) {
    return Url::fromRoute('onlyoffice.editor', ['media' => $media->id()]);
  }

  /**
   * Return Link to Document Editor.
   */
  public static function getEditorLink(Media $media) {
    $title = t("View in ONLYOFFICE");

    if (OnlyofficeDocumentHelper::isEditable($media)) {
      $title = t("Edit in ONLYOFFICE");
    }

    return new Link(
          $title,
          Url::fromRoute('onlyoffice.editor', ['media' => $media->id()])
      );
  }

  /**
   * Return URL to callback for saving document.
   */
  public static function getCallbackUrl(Media $media) {
    $linkParameters = [
      $media->uuid(),
    ];

    $key = static::signLinkParameters($linkParameters);

    return Url::fromRoute('onlyoffice.callback', ['key' => $key])->setAbsolute()->toString();
  }

  /**
   * Return URL to download document.
   */
  public static function getDownloadFileUrl(File $file) {
    $linkParameters = [
      $file->uuid(),
      \Drupal::currentUser()->getAccount()->id(),
    ];

    $key = static::signLinkParameters($linkParameters);

    return Url::fromRoute('onlyoffice.download', ['key' => $key])->setAbsolute()->toString();
  }

  /**
   * Return URL to document in manager documents.
   */
  public static function getGoBackUrl(Media $media) {
    $url = Url::fromRoute('entity.media.collection')->setAbsolute();

    if ($media->hasField('directory') && $media->get('directory')->getString()) {
      $url->setRouteParameter('directory', $media->get('directory')->getString());
    }

    return $url->toString();
  }

  /**
   * Sign a query parameters with a given key and algorithm.
   */
  private static function signLinkParameters(array $parameters) {
    $payload = \implode('?', $parameters);

    $signature = JWT::urlsafeB64Encode(JWT::sign($payload, Settings::getHashSalt() . \Drupal::service('private_key')->get(), 'HS256'));

    return JWT::urlsafeB64Encode($signature . '?' . $payload);
  }

  /**
   * Key Validation and getting query parameters.
   */
  public static function verifyLinkKey($key) {
    $signature = JWT::urlsafeB64Decode($key);

    if ($signature) {
      $segments = \explode('?', $signature);

      $hash = $segments[0];
      $parameters = array_slice($segments, 1);

      if ($hash == JWT::urlsafeB64Encode(JWT::sign(\implode('?', $parameters), Settings::getHashSalt() . \Drupal::service('private_key')->get(), 'HS256'))) {
        return $parameters;
      }
    }

    return FALSE;
  }

}
