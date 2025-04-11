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
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides a confirmation form for deleting all submissions of a PDF form.
 */
class OnlyofficeFormSubmissionDeleteForm extends ConfirmFormBase {

  /**
   * The media entity.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $media;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new OnlyofficeFormSubmissionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'onlyoffice_form_submission_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?MediaInterface $media = NULL) {
    $this->media = $media;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all submissions for %form?', [
      '%form' => $this->media->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.onlyoffice_form_submission.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. All submissions for this form will be permanently deleted.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete all submissions');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->media) {
      try {
        // Get all submissions for this form.
        $submission_storage = $this->entityTypeManager->getStorage('onlyoffice_form_submission');
        $query = $submission_storage->getQuery()
          ->condition('media_id', $this->media->id())
          ->accessCheck(FALSE);
        $submission_ids = $query->execute();

        if (!empty($submission_ids)) {
          $submissions = $submission_storage->loadMultiple($submission_ids);
          $submission_storage->delete($submissions);

          $this->messenger->addStatus($this->t('Deleted @count submissions for %form.', [
            '@count' => count($submissions),
            '%form' => $this->media->label(),
          ]));
        }
        else {
          $this->messenger->addStatus($this->t('No submissions found for %form.', [
            '%form' => $this->media->label(),
          ]));
        }
      }
      catch (\Exception $e) {
        $this->messenger->addError($this->t('Error deleting submissions: @error', [
          '@error' => $e->getMessage(),
        ]));
      }
    }

    $form_state->setRedirect('entity.onlyoffice_form_submission.collection');
  }

}
