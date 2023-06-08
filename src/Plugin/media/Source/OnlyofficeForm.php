<?php

namespace Drupal\onlyoffice\Plugin\media\Source;

/**
 * Copyright (c) Ascensio System SIA 2023.
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

use Drupal\media\MediaTypeInterface;
use Drupal\media\Plugin\media\Source\File;

/**
 * Onlyoffice Form entity media source.
 *
 * @see \Drupal\file\FileInterface
 *
 * @MediaSource(
 *   id = "onlyoffice_form",
 *   label = @Translation("ONLYOFFICE Form"),
 *   description = @Translation("Use onlyoffice form files for reusable media."),
 *   allowed_field_types = {"file"},
 *   default_thumbnail_filename = "oform.png",
 *   forms = {
 *     "media_library_add" = "\Drupal\media_library\Form\FileUploadForm",
 *   },
 * )
 */
class OnlyofficeForm extends File {

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('settings', ['file_extensions' => 'oform']);
  }

}
