<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Drupal\onlyoffice_connector;

use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Firebase\JWT\JWT;

class OnlyofficeUrlHelper
{

  public static function getEditorUrl(Media $media) {
    return Url::fromRoute('onlyoffice_connector.editor', ['media' => $media->id()]);
  }

  public static function getEditorLink(Media $media) {
    $title = t("View in ONLYOFFICE");

    if (OnlyofficeDocumentHelper::isEditable($media)) {
      $title = t("Edit in ONLYOFFICE");
    } elseif (OnlyofficeDocumentHelper::isFillForms($media)) {
      $title = t("Fill in form in ONLYOFFICE");
    }

    return new Link(
      $title,
      Url::fromRoute('onlyoffice_connector.editor', ['media' => $media->id()])
    );
  }

  public static function getCallbackUrl (Media $media) {
    $linkParameters = [
      $media->uuid()
    ];

    $key = static::signLinkParameters($linkParameters);

    return Url::fromRoute('onlyoffice_connector.callback', ['key' => $key])->setAbsolute()->toString();
  }

  public static function getDownloadFileUrl (File $file) {
    $linkParameters = [
      $file->uuid(),
      \Drupal::currentUser()->getAccount()->id()
    ];

    $key = static::signLinkParameters($linkParameters);

    return Url::fromRoute('onlyoffice_connector.download', ['key' => $key])->setAbsolute()->toString();
  }

  public static function getGoBackUrl(Media $media) {
    $url = Url::fromRoute('entity.media.collection')->setAbsolute();

    if ($media->hasField('directory') && $media->get('directory')->getString()) {
      $url->setRouteParameter('directory', $media->get('directory')->getString());
    }

    return $url->toString();
  }

  private static function signLinkParameters(array $parameters) {
    $payload = \implode('?', $parameters);

    $signature = JWT::sign($payload, Settings::getHashSalt() . \Drupal::service('private_key')->get(), 'HS256');

    return JWT::urlsafeB64Encode($signature . '?' . $payload);
  }

  public static function verifyLinkKey($key) {
    $signature = JWT::urlsafeB64Decode($key);

    if ($signature) {
      $segments = \explode('?', $signature);

      $hash = $segments[0];
      $parameters = array_slice($segments, 1);

      if ($hash == JWT::sign(\implode('?', $parameters), Settings::getHashSalt() . \Drupal::service('private_key')->get(), 'HS256')) {
          return $parameters;
      }
    }

    return false;
  }
}
