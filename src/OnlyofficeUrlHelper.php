<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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

    $signature = JWT::urlsafeB64Encode(JWT::sign($payload, Settings::getHashSalt() . \Drupal::service('private_key')->get(), 'HS256'));

    return JWT::urlsafeB64Encode($signature . '?' . $payload);
  }

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

    return false;
  }
}
