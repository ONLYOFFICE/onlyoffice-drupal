<?php

namespace Drupal\onlyoffice_form\Plugin\Field\FieldFormatter;

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

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Uuid\Php as UuidGenerator;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the 'onlyoffice_form_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "onlyoffice_form_formatter",
 *   label = @Translation("ONLYOFFICE Form"),
 *   field_types = {
 *     "onlyoffice_form"
 *   }
 * )
 */
class OnlyofficeFormFormatter extends FormatterBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The page cache disabling policy.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $pageCacheKillSwitch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstoreFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Construct the OnlyofficeFormFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $page_cache_kill_switch
   *   The page cache disabling policy.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    DateFormatterInterface $date_formatter,
    LanguageManagerInterface $language_manager,
    KillSwitch $page_cache_kill_switch,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    SharedTempStoreFactory $tempstore_factory,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->dateFormatter = $date_formatter;
    $this->languageManager = $language_manager;
    $this->pageCacheKillSwitch = $page_cache_kill_switch;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->currentUser = $current_user;
    $this->tempstoreFactory = $tempstore_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('language_manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('onlyoffice_form'),
      $container->get('tempstore.shared'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'save_submissions' => '1',
      'hide_after_submission' => '1',
      'width_unit' => '%',
      'width' => 100,
      'height_unit' => 'px',
      'height' => 640,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['save_submissions'] = [
      '#type' => 'radios',
      '#title' => $this->t('Save all form submissions'),
      '#default_value' => $this->getSetting('save_submissions'),
      '#description' => $this->t('Select “Yes” to store all the form submissions'),
      '#options' => [
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
    ];
    $form['hide_after_submission'] = [
      '#type' => 'radios',
      '#title' => $this->t('Hide all forms after submitting'),
      '#default_value' => $this->getSetting('hide_after_submission'),
      '#description' => $this->t('Select “Yes” to hide the form fields after submitting'),
      '#options' => [
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
    ];
    $form['width_unit'] = [
      '#type' => 'radios',
      '#title' => $this->t('Width units'),
      '#default_value' => $this->getSetting('width_unit'),
      '#options' => [
        '%' => $this->t('Percents'),
        'px' => $this->t('Pixels'),
      ],
    ];
    $form['width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#default_value' => $this->getSetting('width'),
      '#size' => 5,
      '#maxlength' => 5,
      '#min' => 0,
      '#required' => TRUE,
    ];
    $form['height_unit'] = [
      '#type' => 'radios',
      '#title' => $this->t('Height units'),
      '#default_value' => $this->getSetting('height_unit'),
      '#options' => [
        '%' => $this->t('Percents'),
        'px' => $this->t('Pixels'),
      ],
    ];
    $form['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#default_value' => $this->getSetting('height'),
      '#size' => 5,
      '#maxlength' => 5,
      '#min' => 0,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Width')->render() . ': ' . $this->getSetting('width') . $this->getSetting('width_unit');
    $summary[] = $this->t('Height')->render() . ': ' . $this->getSetting('height') . $this->getSetting('height_unit');

    if ($this->getSetting('save_submissions')) {
      $summary[] = $this->t('Save submissions: Yes');
    }
    else {
      $summary[] = $this->t('Save submissions: No');
    }

    if ($this->getSetting('hide_after_submission')) {
      $summary[] = $this->t('Hide after submission: Yes');
    }
    else {
      $summary[] = $this->t('Hide after submission: No');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $this->pageCacheKillSwitch->trigger();

    $element = [
      '#attached' => [
        'library' => [
          'onlyoffice/onlyoffice.api',
          'onlyoffice/onlyoffice.editor',
        ],
      ],
    ];

    try {
      /** @var \Drupal\file\Entity\File $file */
      foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
        if (!$file) {
          continue;
        }

        // Get the media entity directly from the current item.
        $media = $this->entityTypeManager->getStorage('media')->load($items[$delta]->target_id);

        if (!$media) {
          continue;
        }

        // Generate document key here.
        $documentKey = $file->uuid() . "_" . base64_encode($file->getChangedTime());
        $current_user = $this->currentUser;

        if ($current_user->id()) {
          $documentKey .= "_" . $current_user->id();
        }
        else {
          // For anonymous users, use a unique identifier stored in the session.
          $session = $this->requestStack->getCurrentRequest()->getSession();
          if (!$session->has('onlyoffice.guest.id')) {
            $uuid_generator = new UuidGenerator();
            $session->set('onlyoffice.guest.id', $uuid_generator->generate());
          }
          $guest_id = $session->get('onlyoffice.guest.id');
          $documentKey .= "_guest_" . $guest_id;
        }

        // Check if we should hide this form after submission.
        if ($this->getSetting('hide_after_submission')) {
          // Check if the current user has already submitted this form.
          $query = $this->entityTypeManager->getStorage('onlyoffice_form_submission')->getQuery()
            ->condition('media_id', $media->id())
            ->accessCheck(TRUE);

          // For authenticated users, check by user ID.
          if ($this->currentUser->id()) {
            $query->condition('uid', $this->currentUser->id());
            $submission_ids = $query->execute();

            // If there are submissions by this user, don't show the form.
            if (!empty($submission_ids)) {
              $element[$delta] = [
                '#markup' => $this->t('You have already submitted this form.'),
                '#cache' => [
                  'max-age' => 0,
                ],
              ];
              continue;
            }
          }
          // For anonymous users, check using the document key.
          else {
            // Use Drupal's shared tempstore for cross-session persistence.
            $tempstore = $this->tempstoreFactory->get('onlyoffice_form');

            // Use the document key for the storage key.
            $key = 'submission_' . $media->id() . '_' . $documentKey;

            $has_submitted = $tempstore->get($key);

            // Check if this form has been submitted by the current session.
            if ($has_submitted) {
              $element[$delta] = [
                '#markup' => $this->t('You have already submitted this form.'),
                '#cache' => [
                  'max-age' => 0,
                ],
              ];
              continue;
            }
          }
        }

        $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());

        if (OnlyofficeDocumentHelper::getDocumentType($extension)) {
          $editor_id = sprintf(
                '%s-%s-iframeOnlyofficeEditor',
                $file->getEntityTypeId(),
                $file->id()
            );

          $element[$delta] = [
            '#markup' => sprintf('<div id="%s" class="onlyoffice-editor"></div>', $editor_id),
            '#cache' => [
              'max-age' => 0,
            ],
          ];

          if (!isset($element['#attached']['drupalSettings'])) {
            $element['#attached']['drupalSettings'] = [];
          }

          if (!isset($element['#attached']['drupalSettings']['onlyofficeData'])) {
            $element['#attached']['drupalSettings']['onlyofficeData'] = [];
          }

          try {
            $element['#attached']['drupalSettings']['onlyofficeData'][$editor_id] = [
              'config' => $this->getEditorConfig($file, $media, $documentKey),
            ];
          }
          catch (\Exception $e) {
            $this->logger->error('Error generating editor config: @message', ['@message' => $e->getMessage()]);
            // Provide a fallback display.
            $element[$delta] = [
              '#markup' => $this->t('ONLYOFFICE Form preview unavailable'),
              '#cache' => [
                'max-age' => 0,
              ],
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error rendering ONLYOFFICE Form: @message', ['@message' => $e->getMessage()]);
      // Provide a fallback display.
      $element[0] = [
        '#markup' => $this->t('ONLYOFFICE Form preview unavailable'),
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    return $element;
  }

  /**
   * Method getting configuration for document editor service.
   */
  private function getEditorConfig(File $file, Media $media, $documentKey) {
    $editor_width = $this->getSetting('width') . $this->getSetting('width_unit');
    $editor_height = $this->getSetting('height') . $this->getSetting('height_unit');

    // For ONLYOFFICE forms, we use "fillForms" mode.
    $mode = "fillForms";

    // Check if the current user has permission to edit forms.
    if ($this->currentUser->hasPermission('edit onlyoffice forms')) {
      $mode = "edit";
    }

    // Get the owner's display name, or use the current user if owner is null.
    $owner = $file->getOwner();
    $owner_name = 'Anonymous';
    $user_id = NULL;
    $user_name = "Anonymous";

    if ($owner) {
      $owner_name = $owner->getDisplayName();
    }

    // Try to get current user's name.
    $current_user = $this->currentUser;
    if ($current_user->id()) {
      $user_id = $current_user->id();
      $user_entity = $this->entityTypeManager->getStorage('user')->load($current_user->id());
      if ($user_entity) {
        $owner_name = $user_name = $user_entity->getDisplayName();
      }
    }

    $linkParameters = [
      $media->uuid(),
    ];

    $key = OnlyofficeUrlHelper::signLinkParameters($linkParameters);

    $callbackurl = Url::fromRoute('onlyoffice_form.callback', ['key' => $key])->setAbsolute()->toString();

    return OnlyofficeDocumentHelper::createEditorConfig(
      'embedded',
      $documentKey,
      $file->getFilename(),
      OnlyofficeUrlHelper::getDownloadFileUrl($file),
      $owner_name,
      $this->dateFormatter->format($file->getCreatedTime(), 'short'),
      FALSE,
      $callbackurl,
      $mode,
      $this->languageManager->getCurrentLanguage()->getId(),
      $user_id,
      $user_name,
      NULL,
      $editor_width,
      $editor_height,
      TRUE,
      $this->getSetting('save_submissions')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function needsEntityLoad($item) {
    // Our custom field type is not an EntityReferenceItem
    // so we need to handle it differently.
    return !empty($item->target_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(FieldItemListInterface $items, $langcode) {
    $entities = [];

    // Load media entities manually since our field type is not an EntityReferenceItem.
    foreach ($items as $delta => $item) {
      if (!empty($item->target_id)) {
        try {
          // Load the media entity.
          $media = $this->entityTypeManager->getStorage('media')->load($item->target_id);
          if ($media) {
            // Get the source field from the media.
            $source_config = $media->getSource()->getConfiguration();
            if (isset($source_config['source_field'])) {
              $source_field = $source_config['source_field'];

              // Make sure the source field exists on the media entity.
              if ($media->hasField($source_field)) {
                $file_field = $media->get($source_field);

                // Make sure the field has a target_id.
                if ($file_field && $file_field->target_id) {
                  $file_id = $file_field->target_id;
                  $file = $this->entityTypeManager->getStorage('file')->load($file_id);

                  if ($file) {
                    $entities[$delta] = $file;
                  }
                }
              }
            }
          }
        }
        catch (\Exception $e) {
          // Log the error but continue processing other items.
          $this->logger->error('Error loading file for ONLYOFFICE Form: @message', ['@message' => $e->getMessage()]);
        }
      }
    }

    return $entities;
  }

}
