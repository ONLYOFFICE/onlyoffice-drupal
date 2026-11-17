<?php

namespace Drupal\onlyoffice_form;

/**
 * Copyright (c) Ascensio System SIA 2026.
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

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Set of tools for working with forms.
 */
class OnlyofficeFormDocumentHelper {

  /**
   * Constructs an OnlyofficeFormDocumentHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the file field of the ONLYOFFICE PDF form media type.
   */
  public function getSourceFieldDefinition(): ?FieldDefinitionInterface {
    $types = $this->entityTypeManager->getStorage('media_type')->loadByProperties(['source' => 'onlyoffice_pdf_form']);
    $type = reset($types);

    return $type ? $type->getSource()->getSourceFieldDefinition($type) : NULL;
  }

  /**
   * Returns the directory for new PDF forms, as set in their file field.
   */
  public function getUploadLocation(): string {
    $definition = $this->getSourceFieldDefinition();

    if (!$definition) {
      throw new \RuntimeException('The ONLYOFFICE PDF form media type is missing.');
    }

    return $this->entityTypeManager->getStorage('media')
      ->create(['bundle' => $definition->getTargetBundle()])
      ->get($definition->getName())
      ->appendItem()
      ->getUploadLocation();
  }

  /**
   * Checking pdf onlyoffice form by file content.
   *
   * @param string $fileContent
   *   File content.
   *
   * @return bool
   *   TRUE if the file is a PDF form, FALSE otherwise.
   */
  public function isOnlyofficeForm($fileContent) {
    $onlyofficeFormMetaTag = "ONLYOFFICEFORM";

    $indexFirst = strpos($fileContent, "%\xCD\xCA\xD2\xA9\x0D");
    if ($indexFirst === FALSE) {
      return FALSE;
    }

    $pFirst = substr($fileContent, $indexFirst + 6);
    if (!str_starts_with($pFirst, "1 0 obj\n<<\n")) {
      return FALSE;
    }

    $pFirst = substr($pFirst, 11);

    $indexStream = strpos($pFirst, "stream\x0D\x0A");
    $indexMeta = strpos($pFirst, $onlyofficeFormMetaTag);

    if ($indexStream === FALSE || $indexMeta === FALSE || $indexStream < $indexMeta) {
      return FALSE;
    }

    $pMeta = substr($pFirst, $indexMeta);
    $pMeta = substr($pMeta, strlen($onlyofficeFormMetaTag) + 3);

    $indexMetaLast = strpos($pMeta, " ");
    if ($indexMetaLast === FALSE) {
      return FALSE;
    }

    $pMeta = substr($pMeta, $indexMetaLast + 1);

    $indexMetaLast = strpos($pMeta, " ");
    if ($indexMetaLast === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

}
