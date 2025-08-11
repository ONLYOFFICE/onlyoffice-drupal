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
use Drupal\onlyoffice_form\Entity\OnlyofficeFormSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a form for deleting a single ONLYOFFICE form submission.
 */
class OnlyofficeFormSubmissionDeleteSingleForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The submission entity.
   *
   * @var \Drupal\onlyoffice_form\Entity\OnlyofficeFormSubmission
   */
  protected $entity;

  /**
   * Constructs a new OnlyofficeFormSubmissionDeleteSingleForm.
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
    return 'onlyoffice_form_submission_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?OnlyofficeFormSubmission $onlyoffice_form_submission = NULL) {
    $this->entity = $onlyoffice_form_submission;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this submission?');
  }

  /**
   * Gets the cancel URL.
   *
   * @return \Drupal\Core\Url
   *   The cancel URL.
   */
  public function getCancelUrl() {
    $media_id = $this->entity->media_id->target_id;

    if ($media_id) {
      return new Url('entity.onlyoffice_form_submission.collection', ['media' => $media_id]);
    }

    return new Url('entity.onlyoffice_form_submission.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the media entity from the submission before deletion.
    $media_id = $this->entity->media_id->target_id;

    // Delete the entity.
    $this->entity->delete();

    $this->messenger()->addStatus($this->t('The submission has been deleted.'));

    // Redirect back to the form's submissions page if we have a media ID.
    if ($media_id) {
      $form_state->setRedirect('entity.onlyoffice_form_submission.collection', ['media' => $media_id]);
    }
    else {
      $form_state->setRedirect('entity.onlyoffice_form_submission.collection');
    }
  }

}
