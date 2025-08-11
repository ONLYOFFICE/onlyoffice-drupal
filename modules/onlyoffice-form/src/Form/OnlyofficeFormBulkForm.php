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

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Provides the ONLYOFFICE form bulk form.
 */
class OnlyofficeFormBulkForm extends FormBase {

  use MessengerTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new OnlyofficeFormBulkForm.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'onlyoffice_form_bulk_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $table = []) {
    $form['#attributes']['class'][] = 'onlyoffice-form-bulk-form';

    // Operations.
    $form['header']['operations'] = [
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
      '#weight' => -100,
    ];

    $form['header']['operations']['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $this->getBulkOptions(),
      '#empty_option' => $this->t('- Select operation -'),
    ];

    $form['header']['operations']['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
    ];

    // Table select.
    $form['items'] = [
      '#type' => 'tableselect',
      '#header' => $table['#header'],
      '#options' => [],
      '#empty' => $table['#empty'],
      '#attributes' => ['class' => ['onlyoffice-forms-tableselect']],
    ];

    // Convert the rows to options for the tableselect.
    if (!empty($table['#rows'])) {
      foreach ($table['#rows'] as $id => $row) {
        // Create a new row with the same structure.
        $options[$id] = [];

        // Copy each column's data.
        foreach ($row as $key => $column) {
          if ($key === 'operations') {
            // For operations, we need to render the dropbutton.
            $options[$id][$key] = [
              'data' => $column,
            ];
          }
          elseif (is_array($column) && isset($column['data'])) {
            // For columns with render arrays.
            $options[$id][$key] = $column;
          }
          else {
            // For simple columns.
            $options[$id][$key] = ['data' => ['#markup' => $column]];
          }
        }
      }

      $form['items']['#options'] = $options;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue('action');
    if (empty($action)) {
      $form_state->setErrorByName('action', $this->t('No operation selected.'));
    }

    $entity_ids = array_filter($form_state->getValue('items'));
    if (empty($entity_ids)) {
      $form_state->setErrorByName('items', $this->t('No items selected.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue('action');
    $selected = array_filter($form_state->getValue('items'));

    if (empty($selected)) {
      $this->messenger()->addError($this->t('No items selected.'));
      return;
    }

    if ($action === 'delete') {
      // Load and delete the selected media entities.
      $media_ids = array_keys($selected);
      $media_storage = $this->entityTypeManager->getStorage('media');
      $media_entities = $media_storage->loadMultiple($media_ids);

      if (!empty($media_entities)) {
        $count = count($media_entities);
        $media_storage->delete($media_entities);

        $this->messenger()->addStatus($this->formatPlural(
          $count,
          '1 form has been deleted.',
          '@count forms have been deleted.'
        ));
      }

      // Redirect back to the forms page.
      $form_state->setRedirect('entity.onlyoffice_form.collection');
      return;
    }

    // Add more actions here as needed.
  }

  /**
   * Returns the available operations for this form.
   *
   * @return array
   *   An associative array of operations.
   */
  protected function getBulkOptions() {
    return [
      'delete' => $this->t('Delete'),
    ];
  }

}
