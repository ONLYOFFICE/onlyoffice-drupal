<?php

namespace Drupal\onlyoffice_form\Form;

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

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media\Entity\Media;

/**
 * Provides a confirmation form for deleting multiple ONLYOFFICE forms.
 */
class OnlyofficeFormBulkDeleteConfirmForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The media IDs to delete.
   *
   * @var array
   */
  protected $mediaIds = [];

  /**
   * Constructs a new OnlyofficeFormBulkDeleteConfirmForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'onlyoffice_form_bulk_delete_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $count = count($this->mediaIds);
    if ($count === 1) {
      return $this->t('Are you sure you want to delete this form?');
    }
    return $this->t('Are you sure you want to delete these @count forms?', ['@count' => $count]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.onlyoffice_form.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $count = count($this->mediaIds);
    if ($count === 1) {
      return $this->t('This action cannot be undone.');
    }
    return $this->t('This action cannot be undone. All @count forms will be deleted.', ['@count' => $count]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $ids = NULL) {
    if ($ids) {
      $this->mediaIds = explode(',', $ids);
    }
    else {
      // If no IDs are provided, redirect back to the forms page
      return $this->redirect('entity.onlyoffice_form.collection');
    }
    
    // Show a list of forms that will be deleted
    $form['forms'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('The following forms will be deleted:'),
      '#items' => [],
    ];
    
    foreach ($this->mediaIds as $media_id) {
      $media = Media::load($media_id);
      if ($media) {
        $form['forms']['#items'][] = $media->label();
      }
    }
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!empty($this->mediaIds)) {
      $media_storage = $this->entityTypeManager->getStorage('media');
      $media_entities = $media_storage->loadMultiple($this->mediaIds);
      
      $count = count($media_entities);
      
      // Delete the media entities
      $media_storage->delete($media_entities);
      
      // Set success message
      $this->messenger()->addStatus($this->formatPlural(
        $count,
        '1 form has been deleted.',
        '@count forms have been deleted.'
      ));
    }
    
    $form_state->setRedirect('entity.onlyoffice_form.collection');
  }

}
