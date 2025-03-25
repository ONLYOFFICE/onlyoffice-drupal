<?php

namespace Drupal\onlyoffice_form;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a listing of ONLYOFFICE form entities.
 */
class OnlyofficeFormListBuilder extends ControllerBase {

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
   * Search state.
   *
   * @var string
   */
  protected $state;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user storage object.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new OnlyofficeFormListBuilder.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    LoggerInterface $logger,
    Connection $database,
  ) {
    $this->request = $request_stack->getCurrentRequest();
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->logger = $logger;
    $this->database = $database;
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
      $container->get('form_builder'),
      $container->get('logger.factory')->get('onlyoffice_form'),
      $container->get('database'),
    );
  }

  /**
   * Initialize OnlyofficeFormListBuilder object.
   */
  protected function initialize() {
    $query = $this->request->query;

    $this->keys = ($query->has('search')) ? $query->get('search') : '';
    $this->state = ($query->has('state')) ? $query->get('state') : '';

    $this->userStorage = $this->entityTypeManager->getStorage('user');
  }

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   */
  public function buildHeader() {
    $header['title'] = [
      'data' => $this->t('Title'),
      'specifier' => 'title',
      'field' => 'title',
      'sort' => 'asc',
    ];
    $header['author'] = [
      'data' => $this->t('Author'),
      'class' => ['priority-low'],
    ];
    $header['type'] = [
      'data' => $this->t('Type'),
      'class' => ['priority-low'],
    ];
    $header['size'] = [
      'data' => $this->t('Size'),
      'class' => ['priority-low'],
    ];
    $header['results'] = [
      'data' => $this->t('Results'),
      'class' => ['priority-medium'],
    ];
    $header['operations'] = [
      'data' => $this->t('Operations'),
    ];
    return $header;
  }

  /**
   * Main page callback for the ONLYOFFICE forms list.
   *
   * @return array
   *   A render array for the list page.
   */
  public function render() {
    $build = [];

    // Add button to create new form with modal dialog.
    $build['add_form'] = [
      '#type' => 'link',
      '#title' => $this->t('Create & Upload'),
      '#url' => Url::fromRoute('entity.onlyoffice_form.create_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 700,
          'dialogClass' => 'onlyoffice-form-ui-dialog',
        ]),
      ],
    ];

    // Filter form.
    $build['filter_form'] = $this->buildFilterForm();

    // Display info.
    $build['info'] = $this->buildInfo();

    // Table.
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('No ONLYOFFICE forms available.'),
      '#sticky' => TRUE,
      '#attributes' => [
        'class' => ['onlyoffice-forms'],
      ],
    ];

    // Add PDF forms from the database.
    $this->addPdfFormsToTable($build);

    // Attach libraries for dialog functionality.
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $build['#attached']['library'][] = 'onlyoffice_form/onlyoffice_form.dialog';
    $build['#attached']['library'][] = 'onlyoffice_form/onlyoffice_form.admin';

    // Add bulk operations form.
    if ($this->currentUser->hasPermission('administer onlyoffice forms')) {
      $build['table'] = $this->formBuilder->getForm('\Drupal\onlyoffice_form\Form\OnlyofficeFormBulkForm', $build['table']);
    }

    return $build;
  }

  /**
   * Add PDF forms from the database to the table.
   *
   * @param array $build
   *   The build array containing the table.
   */
  protected function addPdfFormsToTable(array &$build) {
    try {
      // Get PDF forms as media entities.
      $media_storage = $this->entityTypeManager->getStorage('media');
      $query = $media_storage->getQuery();
      $query->accessCheck(FALSE);
      $query->condition('bundle', 'onlyoffice_pdf_form');

      // Apply search filter if provided.
      if (!empty($this->keys)) {
        $group = $query->orConditionGroup()
          ->condition('name', '%' . $this->keys . '%', 'LIKE');
        $query->condition($group);
      }

      $media_ids = $query->execute();

      if (!empty($media_ids)) {
        $media_entities = $media_storage->loadMultiple($media_ids);

        foreach ($media_entities as $media_id => $media) {
          $row = [];

          // Title.
          $row['title']['data']['title'] = [
            '#type' => 'link',
            '#title' => $media->label(),
            '#url' => $media->toUrl('edit-form'),
          ];

          // Author.
          $uid = 0;
          if (method_exists($media, 'getOwnerId')) {
            $uid = $media->getOwnerId();
          }

          $user = $uid ? $this->entityTypeManager->getStorage('user')->load($uid) : NULL;
          $author = $user ? $user->getDisplayName() : $this->t('Anonymous');
          $row['author'] = $author;

          // Type (always PDF)
          $row['type'] = 'PDF';

          // Get the source field from media configuration.
          if (method_exists($media, 'getSource')) {
            $source_config = $media->getSource()->getConfiguration();
            if (isset($source_config['source_field'])) {
              $source_field = $source_config['source_field'];

              if ($media->hasField($source_field) && !$media->get($source_field)->isEmpty()) {
                $fid = $media->get($source_field)->target_id;
                if ($fid) {
                  $file = $this->entityTypeManager->getStorage('file')->load($fid);
                  if ($file) {
                    $file_size = $file->getSize();
                  }
                }
              }
            }
          }

          // Format file size manually.
          if (isset($file_size)) {
            if ($file_size < 1024) {
              $row['size'] = $file_size . ' B';
            }
            elseif ($file_size < 1048576) {
              $row['size'] = number_format($file_size / 1024, 2) . ' KB';
            }
            else {
              $row['size'] = number_format($file_size / 1048576, 2) . ' MB';
            }
          }
          else {
            $row['size'] = '';
          }

          // Results (placeholder for now)
          $row['results'] = '0';

          // Operations.
          $operations = [];

          $operations['edit'] = [
            'title' => $this->t('Edit'),
            'url' => OnlyofficeUrlHelper::getEditorUrl($media),
            'attributes' => [
              'target' => '_blank',
            ],
          ];
          $operations['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('entity.media.delete_form', ['media' => $media_id]),
          ];

          $row['operations']['data'] = [
            '#type' => 'operations',
            '#links' => $operations,
            '#prefix' => '<div class="onlyoffice-form-dropbutton">',
            '#suffix' => '</div>',
          ];

          $build['table']['#rows'][$media_id] = $row;
        }
      }
    }
    catch (\Exception $e) {
      // If there's an error loading the media entities, just continue without them.
    }
  }

  /**
   * Build the filter form.
   *
   * @return array
   *   A render array representing the filter form.
   */
  protected function buildFilterForm() {
    $state_options = [
      '' => $this->t('All forms'),
      'active' => $this->t('Active forms'),
      'inactive' => $this->t('Inactive forms'),
    ];

    return $this->formBuilder->getForm('\Drupal\onlyoffice_form\Form\OnlyofficeFormFilterForm', $this->keys, $this->state, $state_options);
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
      // Count PDF forms as media entities.
      $pdf_form_count = 0;
      try {
        // Use a direct database query as a fallback approach.
        $query = $this->database->select('media', 'm')
          ->condition('m.bundle', 'onlyoffice_pdf_form');
        $query->addExpression('COUNT(m.mid)', 'count');
        $result = $query->execute()->fetchField();
        $pdf_form_count = (int) $result;
      }
      catch (\Exception $e) {
        // If there's an error, just use 0 as the count.
        $this->logger->error('Error counting PDF forms: @message', ['@message' => $e->getMessage()]);
      }

      return [
        '#markup' => $this->formatPlural($pdf_form_count, '@count form', '@count forms'),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }
    else {
      return [];
    }
  }

}
