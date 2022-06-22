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

namespace Drupal\onlyoffice;

use Drupal\onlyoffice\OnlyofficeAppConfig;
use Drupal\media\Entity\Media;
use Firebase\JWT\JWT;

class OnlyofficeDocumentHelper
{

    public static function getEditingKey($file, $preview = false)
    {
        $key = $file->uuid() . "_" .  base64_encode($file->getChangedTime());

        if ($preview) {
            $key = $key . "_preview";
        }

        return $key;
    }

    public static function getExtension($filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function getDocumentType($ext)
    {
        $format = OnlyofficeAppConfig::getSupportedFormats()[$ext] ?? null;
        if ($format) {
            return $format["type"];
        }

        return null;
    }

    public static function isEditable(Media $media)
    {
        $file = $media->get(static::getSourceFieldName($media))->entity;
        $extension = static::getExtension($file->getFilename());

        $format = OnlyofficeAppConfig::getSupportedFormats()[$extension] ?? null;

        return isset($format["edit"]) && $format["edit"];
    }

    public static function getSourceFieldName(Media $media)
    {
        return $media->getSource()
        ->getSourceFieldDefinition($media->bundle->entity)
        ->getName();
    }

    public static function createEditorConfig(
        $editor_type,
        $document_key,
        $document_title,
        $document_url,
        $document_info_owner = null,
        $document_info_uploaded = null,
        $document_permissions_download = true,
        $document_permissions_edit = false,
        $editorConfig_callbackUrl = 'null',
        $editorConfig_mode = 'view',
        $editorConfig_lang = 'en',
        $editorConfig_user_id = null,
        $editorConfig_user_name = null,
        $editorConfig_customization_goback_url = null,
        $editor_width = "100%",
        $editor_height = "100%"
    ) {

        $document_fileType = static::getExtension($document_title);

        $config = [
        'type' => $editor_type,
        'width' => $editor_width,
        'height' => $editor_height,
        'documentType' => static::getDocumentType($document_fileType),
        'document' => [
          'title' => $document_title,
          'url' => $document_url,
          'fileType' => $document_fileType,
          'key' => $document_key,
          'info' => [
            'owner' => $document_info_owner,
            'uploaded' => $document_info_uploaded
          ],
          'permissions' => [
            'download' => $document_permissions_download,
            'edit' => $document_permissions_edit
          ]
        ],
        'editorConfig' => [
          'callbackUrl' => $editorConfig_callbackUrl,
          'mode' => $editorConfig_mode,
          'lang' => $editorConfig_lang,
          'user' => [
            'id' => $editorConfig_user_id,
            'name' => $editorConfig_user_name
          ],
          'customization' => [
            'goback' => [
              'url' => $editorConfig_customization_goback_url
            ]
          ]
        ]
        ];

        $options = \Drupal::config('onlyoffice.settings');

        if ($options->get('doc_server_jwt')) {
            $token = JWT::encode($config, $options->get('doc_server_jwt'), 'HS256');
            $config["token"] = $token;
        }

        return $config;
    }
}
