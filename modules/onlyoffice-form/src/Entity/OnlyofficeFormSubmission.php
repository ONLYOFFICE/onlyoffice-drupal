<?php

namespace Drupal\onlyoffice_form\Entity;

/**
 * Copyright (c) Ascensio System SIA 2025.
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

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the ONLYOFFICE Form Submission entity.
 *
 * @ContentEntityType(
 *   id = "onlyoffice_form_submission",
 *   label = @Translation("ONLYOFFICE Form Submission"),
 *   label_collection = @Translation("ONLYOFFICE Form Submissions"),
 *   label_singular = @Translation("ONLYOFFICE form submission"),
 *   label_plural = @Translation("ONLYOFFICE form submissions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ONLYOFFICE form submission",
 *     plural = "@count ONLYOFFICE form submissions",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "form" = {
 *       "delete" = "Drupal\onlyoffice_form\Form\OnlyofficeFormSubmissionDeleteSingleForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "onlyoffice_form_submission",
 *   data_table = "onlyoffice_form_submission_field_data",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/onlyoffice-form/submission/{onlyoffice_form_submission}",
 *     "delete-form" = "/admin/structure/onlyoffice-form/submission/{onlyoffice_form_submission}/delete",
 *     "collection" = "/admin/structure/onlyoffice-form/submissions"
 *   }
 * )
 */
class OnlyofficeFormSubmission extends ContentEntityBase implements EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['media_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(\Drupal::translation()->translate('PDF Form'))
      ->setDescription(\Drupal::translation()->translate('The PDF form this submission belongs to.'))
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'onlyoffice_pdf_form' => 'onlyoffice_pdf_form',
        ],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['file_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(\Drupal::translation()->translate('Submitted File'))
      ->setDescription(\Drupal::translation()->translate('The submitted form file.'))
      ->setSetting('target_type', 'file')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(\Drupal::translation()->translate('Created'))
      ->setDescription(\Drupal::translation()->translate('The time that the submission was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(\Drupal::translation()->translate('Changed'))
      ->setDescription(\Drupal::translation()->translate('The time that the submission was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * Gets the media entity this submission belongs to.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media entity or NULL if not found.
   */
  public function getMedia() {
    $media_id = $this->get('media_id')->target_id;
    if (!$media_id) {
      return NULL;
    }
    return \Drupal::entityTypeManager()->getStorage('media')->load($media_id);
  }

  /**
   * Gets the file entity for this submission.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or NULL if not found.
   */
  public function getFile() {
    $file_id = $this->get('file_id')->target_id;
    if (!$file_id) {
      return NULL;
    }
    return \Drupal::entityTypeManager()->getStorage('file')->load($file_id);
  }

  /**
   * Sets the file entity for this submission.
   *
   * @param int $file_id
   *   The file entity ID.
   *
   * @return $this
   */
  public function setFileId($file_id) {
    $this->set('file_id', $file_id);
    return $this;
  }

}
