<?php

namespace Drupal\onlyoffice_form;

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

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\media\MediaInterface;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Psr\Log\LoggerInterface;

/**
 * Provides a listing of ONLYOFFICE form submission entities.
 */
class OnlyofficeFormSubmissionListBuilder extends ControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Search keys.
   *
   * @var string
   */
  protected $keys;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new OnlyofficeFormSubmissionListBuilder.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    FileUrlGeneratorInterface $file_url_generator,
    Connection $database,
    FormBuilderInterface $form_builder,
    LoggerInterface $logger,
  ) {
    $this->request = $request_stack->getCurrentRequest();
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->fileUrlGenerator = $file_url_generator;
    $this->database = $database;
    $this->formBuilder = $form_builder;
    $this->logger = $logger;
    $this->initialize();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('file_url_generator'),
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('logger.factory')->get('onlyoffice_form')
    );
  }

  /**
   * Initialize OnlyofficeFormSubmissionListBuilder object.
   */
  protected function initialize() {
    $query = $this->request->query;
    $this->keys = ($query->has('search')) ? $query->get('search') : '';
  }

  /**
   * Builds the header row for the entity listing.
   *
   * @param bool $by_form
   *   Whether to show headers for the by form view.
   *
   * @return array
   *   A render array structure of header strings.
   */
  public function buildHeader($by_form = FALSE) {
    $header = [];

    if ($by_form) {
      // Headers for individual form submissions view.
      $header['title'] = [
        'data' => $this->t('Title'),
        'class' => ['priority-medium'],
      ];
      $header['form'] = [
        'data' => $this->t('Form'),
        'class' => ['priority-medium'],
      ];
      $header['type'] = [
        'data' => $this->t('Type'),
        'class' => ['priority-medium'],
      ];
      $header['size'] = [
        'data' => $this->t('Size'),
        'class' => ['priority-medium'],
      ];
      $header['submitter'] = [
        'data' => $this->t('Submitter'),
        'class' => ['priority-medium'],
      ];
      $header['submitted'] = [
        'data' => $this->t('Submitted'),
        'class' => ['priority-medium'],
      ];
      $header['operations'] = [
        'data' => $this->t('Operations'),
      ];
    }
    else {
      // Headers for grouped submissions view.
      $header['form'] = [
        'data' => $this->t('Form'),
        'specifier' => 'form',
        'field' => 'form',
        'sort' => 'asc',
      // Add a class for CSS targeting.
        'class' => ['form'],
      // Make the form column take up half the table width.
        'style' => 'width: 50%;',
      ];
      $header['submissions'] = [
        'data' => $this->t('Submissions'),
        'class' => ['priority-medium'],
      ];
      $header['operations'] = [
        'data' => $this->t('Operations'),
      ];
    }

    return $header;
  }

  /**
   * Main page callback for the ONLYOFFICE form submissions list.
   *
   * @param \Drupal\media\MediaInterface|null $media
   *   The media entity, if viewing submissions for a specific form.
   *
   * @return array
   *   A render array for the list page.
   */
  public function render(?MediaInterface $media = NULL) {
    $build = [];

    // Add the tabs for navigation.
    $build['tabs'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['onlyoffice-form-tabs'],
      ],
    ];

    if ($media) {
      // We're viewing submissions for a specific form.
      // Filter form for individual form submissions.
      $build['filter_form'] = $this->buildFilterForm();

      // Display info for this specific form.
      $build['info'] = $this->buildInfoByForm($media);

      // Table for this specific form.
      $build['table'] = [
        '#type' => 'table',
        '#header' => $this->buildHeader(TRUE),
        '#rows' => [],
        '#empty' => $this->t('No submissions available for this form.'),
        '#sticky' => TRUE,
        '#attributes' => [
          'class' => ['onlyoffice-form-submissions'],
        ],
      ];

      // Add form submissions for this specific form.
      $this->addSubmissionsByFormToTable($build, $media);
    }
    else {
      // We're viewing all submissions grouped by form.
      // Filter form.
      $build['filter_form'] = $this->buildFilterForm();

      // Display info for all forms.
      $build['info'] = $this->buildInfo();

      // Table for all forms.
      $build['table'] = [
        '#type' => 'table',
        '#header' => $this->buildHeader(),
        '#rows' => [],
        '#empty' => $this->t('No PDF forms with submissions available.'),
        '#sticky' => TRUE,
        '#attributes' => [
          'class' => ['onlyoffice-form-submissions'],
        ],
      ];

      // Add form submissions grouped by PDF form.
      $this->addGroupedSubmissionsToTable($build);
    }

    // Attach libraries for styling.
    $build['#attached']['library'][] = 'onlyoffice_form/onlyoffice_form.admin';

    return $build;
  }

  /**
   * Render submissions for a specific form.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array
   *   A render array for the list page.
   */
  public function renderByForm(MediaInterface $media) {
    return $this->render($media);
  }

  /**
   * Add grouped form submissions to the table.
   *
   * @param array $build
   *   The build array containing the table.
   */
  protected function addGroupedSubmissionsToTable(array &$build) {
    try {
      // Get form submissions grouped by media_id.
      $submission_storage = $this->entityTypeManager->getStorage('onlyoffice_form_submission');
      $query = $submission_storage->getQuery();
      $query->accessCheck(FALSE);

      $submission_ids = $query->execute();

      if (!empty($submission_ids)) {
        $submissions = $submission_storage->loadMultiple($submission_ids);

        // Group submissions by form (media_id)
        $grouped_submissions = [];
        foreach ($submissions as $submission) {
          $media_id = $submission->media_id->target_id ?? NULL;
          if ($media_id) {
            if (!isset($grouped_submissions[$media_id])) {
              $grouped_submissions[$media_id] = [];
            }
            $grouped_submissions[$media_id][] = $submission;
          }
        }

        // Only show forms that have submissions.
        if (!empty($grouped_submissions)) {
          foreach ($grouped_submissions as $media_id => $form_submissions) {
            $media = $this->entityTypeManager->getStorage('media')->load($media_id);
            if (!$media) {
              continue;
            }

            $media_name = $media->label();
            $submission_count = count($form_submissions);

            // Skip if we're filtering and this form doesn't match.
            if (!empty($this->keys) && stripos($media_name, $this->keys) === FALSE) {
              continue;
            }

            $row = [];

            // Form name.
            $row['form']['data']['form'] = [
              '#markup' => '<a href="' . Url::fromRoute('entity.onlyoffice_form_submission.collection', ['media' => $media->id()])->toString() . '">' . $media_name . '</a>',
              '#prefix' => '<div style="width: 100%; overflow: hidden; text-overflow: ellipsis;">',
              '#suffix' => '</div>',
            ];

            // Submission count.
            $row['submissions'] = $submission_count;

            // Operations.
            $operations = [];

            // Edit form.
            $operations['edit_form'] = [
              'title' => $this->t('Edit form'),
              'url' => OnlyofficeUrlHelper::getEditorUrl($media),
            ];

            // Delete all submissions for this form.
            $operations['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.onlyoffice_form_submission.delete_by_form', ['media' => $media->id()]),
            ];

            $row['operations']['data'] = [
              '#type' => 'operations',
              '#links' => $operations,
              '#prefix' => '<div class="onlyoffice-form-dropbutton">',
              '#suffix' => '</div>',
            ];

            $build['table']['#rows'][] = $row;
          }
        }
      }
    }
    catch (\Exception $e) {
      // If there's an error loading the submissions, just continue without them.
    }
  }

  /**
   * Add form submissions for a specific form to the table.
   *
   * @param array $build
   *   The build array containing the table.
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   */
  protected function addSubmissionsByFormToTable(array &$build, MediaInterface $media) {
    try {
      // Get form submissions for this specific form.
      $submission_storage = $this->entityTypeManager->getStorage('onlyoffice_form_submission');
      $query = $submission_storage->getQuery();
      $query->condition('media_id', $media->id());
      $query->accessCheck(FALSE);
      // Show newest submissions first.
      $query->sort('created', 'DESC');

      $submission_ids = $query->execute();

      if (!empty($submission_ids)) {
        $submissions = $submission_storage->loadMultiple($submission_ids);

        foreach ($submissions as $submission) {
          $row = [];

          // Get file information first since we need it for multiple fields.
          $file_id = $submission->file_id->target_id ?? NULL;
          $file = NULL;
          $title = $this->t('Submission @id', ['@id' => $submission->id()]);
          $file_size = 0;

          if ($file_id) {
            $file = $this->entityTypeManager->getStorage('file')->load($file_id);
            if ($file) {
              // Get the filename of the file for title.
              $file_uri = $file->getFileUri();
              $filename = pathinfo($file_uri, PATHINFO_FILENAME);
              $title = $filename;

              // Get file size.
              $file_size = $file->getSize();
            }
          }

          // Skip if we're filtering and this submission doesn't match.
          if (!empty($this->keys)) {
            $uid = $submission->uid->target_id ?? NULL;
            $user = NULL;
            if ($uid) {
              $user = $this->entityTypeManager->getStorage('user')->load($uid);
            }
            $submitter = $user ? $user->getAccountName() : $this->t('Anonymous');

            // Check if the title or submitter matches the search term.
            if (stripos($title, $this->keys) === FALSE &&
                stripos($submitter, $this->keys) === FALSE) {
              continue;
            }
          }

          // Title.
          $row['title']['data'] = [
            '#markup' => '<a target="_blank" href="' . Url::fromRoute('entity.onlyoffice_form_submission.view', ['onlyoffice_form_submission' => $submission->id()])->toString() . '">' . $title . '</a>',
          ];

          // Form - Link to the form.
          $form_media = $media;
          $row['form']['data']['form'] = [
            '#markup' => '<a target="_blank" href="' . OnlyofficeUrlHelper::getEditorUrl($form_media)->toString() . '">' . $form_media->label() . '</a>',
          ];

          // Type - Always PDF for now.
          $row['type'] = 'PDF';

          // Size - Format file size.
          if ($file_size < 1024) {
            $row['size'] = $file_size . ' B';
          }
          elseif ($file_size < 1048576) {
            $row['size'] = number_format($file_size / 1024, 2) . ' KB';
          }
          else {
            $row['size'] = number_format($file_size / 1048576, 2) . ' MB';
          }

          // Submitter.
          $uid = $submission->uid->target_id ?? NULL;
          $user = NULL;
          if ($uid) {
            $user = $this->entityTypeManager->getStorage('user')->load($uid);
          }
          $submitter = $user ? $user->getAccountName() : $this->t('Anonymous');
          $row['submitter'] = $submitter;

          // Submitted date.
          $created_time = $submission->created->value ?? time();
          $row['submitted'] = $this->dateFormatter->format($created_time, 'short');

          // Operations.
          $operations = [];

          if ($file) {
            // Download the submitted file.
            $operations['download'] = [
              'title' => $this->t('Download'),
              'url' => Url::fromRoute('entity.onlyoffice_form_submission.download', [
                'onlyoffice_form_submission' => $submission->id(),
              ]),
            ];
          }

          // Delete individual submission.
          $operations['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('entity.onlyoffice_form_submission.delete_form', ['onlyoffice_form_submission' => $submission->id()]),
          ];

          $row['operations']['data'] = [
            '#type' => 'operations',
            '#links' => $operations,
            '#prefix' => '<div class="onlyoffice-form-dropbutton">',
            '#suffix' => '</div>',
          ];

          $build['table']['#rows'][] = $row;
        }
      }
    }
    catch (\Exception $e) {
      // If there's an error loading the submissions, just continue without them.
    }
  }

  /**
   * Build the filter form.
   *
   * @return array
   *   A render array representing the filter form.
   */
  protected function buildFilterForm() {
    return $this->formBuilder->getForm('\Drupal\onlyoffice_form\Form\OnlyofficeFormSubmissionFilterForm', $this->keys);
  }

  /**
   * Build information summary.
   *
   * @return array
   *   A render array representing the information summary.
   */
  protected function buildInfo() {
    // Display info.
    if ($this->currentUser->hasPermission('administer onlyoffice forms')) {
      // Count form submissions.
      $submission_count = 0;
      try {
        $query = $this->database->select('onlyoffice_form_submission', 's');
        $query->addExpression('COUNT(s.id)', 'count');
        $result = $query->execute()->fetchField();
        $submission_count = (int) $result;
      }
      catch (\Exception $e) {
        // If there's an error, just use 0 as the count.
        $this->logger->error('Error counting submissions: @message', ['@message' => $e->getMessage()]);
      }

      return [
        '#markup' => $this->formatPlural($submission_count, '@count submission', '@count submissions'),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }
    else {
      return [];
    }
  }

  /**
   * Build information summary for a specific form.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array
   *   A render array representing the information summary.
   */
  protected function buildInfoByForm(MediaInterface $media) {
    // Display info.
    if ($this->currentUser->hasPermission('administer onlyoffice forms')) {
      // Count form submissions for this specific form.
      $submission_count = 0;
      try {
        $query = $this->database->select('onlyoffice_form_submission', 's');
        $query->condition('s.media_id', $media->id());
        $query->addExpression('COUNT(s.id)', 'count');
        $result = $query->execute()->fetchField();
        $submission_count = (int) $result;

        // If we're filtering, show a different message.
        if (!empty($this->keys)) {
          // We can't get an exact filtered count from the database query
          // since filtering happens in PHP, so we'll just indicate that
          // results are filtered.
          return [
            '#markup' => $this->t('Showing filtered results for %form. <a href="@reset_url">Clear filter</a>', [
              '%form' => $media->label(),
              '@reset_url' => Url::fromRoute('entity.onlyoffice_form_submission.collection', ['media' => $media->id()])->toString(),
            ]),
            '#prefix' => '<div>',
            '#suffix' => '</div>',
          ];
        }
      }
      catch (\Exception $e) {
        // If there's an error, just use 0 as the count.
        $this->logger->error('Error counting submissions for form: @message', ['@message' => $e->getMessage()]);
      }

      return [
        '#markup' => $this->formatPlural($submission_count, '@count submission for %form', '@count submissions for %form', ['%form' => $media->label()]),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }
    else {
      return [];
    }
  }

}
