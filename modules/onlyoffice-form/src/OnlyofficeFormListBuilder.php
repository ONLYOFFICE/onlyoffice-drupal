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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    $this->keys = $this->request->query->get('search') ?? '';
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
      'specifier' => 'uid',
      'field' => 'uid',
    ];
    $header['type'] = [
      'data' => $this->t('Type'),
      'class' => ['priority-low'],
    ];
    $header['size'] = [
      'data' => $this->t('Size'),
      'class' => ['priority-low'],
      'specifier' => 'filesize',
      'field' => 'filesize',
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
      '#tableselect' => FALSE,
      '#tabledrag' => FALSE,
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

      // Get the sort field and direction from the table header.
      $header = $this->buildHeader();
      $order = $this->request->query->get('order', '');
      $sort = $this->request->query->get('sort', 'asc');

      // Default sort is by title ascending.
      $field = 'name';
      $direction = 'ASC';

      // Map the requested sort field to the actual database field.
      if (!empty($order)) {
        // Find which field was selected for sorting.
        foreach ($header as $info) {
          if (is_array($info) && isset($info['data']) && $order === (string) $info['data']) {
            // If this field has a 'specifier', use that for sorting.
            if (isset($info['specifier'])) {
              switch ($info['specifier']) {
                case 'title':
                  $field = 'name';
                  break;

                case 'uid':
                  $field = 'uid';
                  break;

                case 'filesize':
                  // Special case - we'll sort this after loading the entities.
                  $field = 'filesize';
                  break;

                default:
                  $field = $info['specifier'];
                  break;
              }
            }
            break;
          }
        }

        $direction = ($sort === 'desc') ? 'DESC' : 'ASC';
      }

      // Apply sorting in the query if it's not a special case like filesize.
      if ($field !== 'filesize') {
        $query->sort($field, $direction);
      }

      $media_ids = $query->execute();

      if (!empty($media_ids)) {
        $media_entities = $media_storage->loadMultiple($media_ids);
        $rows = [];

        foreach ($media_entities as $media_id => $media) {
          $row = [];
          $file_size = 0;

          // Title.
          $row['title']['data']['title'] = [
            '#type' => 'link',
            '#title' => $media->label(),
            '#url' => OnlyofficeUrlHelper::getEditorUrl($media),
            '#attributes' => [
              'target' => '_blank',
            ],
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
          if ($file_size) {
            if ($file_size < 1024) {
              $row['size'] = $file_size . ' B';
            }
            elseif ($file_size < 1048576) {
              $row['size'] = number_format($file_size / 1024, 2) . ' KB';
            }
            else {
              $row['size'] = number_format($file_size / 1048576, 2) . ' MB';
            }
            // Store the raw file size for sorting.
            $row['#file_size'] = $file_size;
          }
          else {
            $row['size'] = '';
            $row['#file_size'] = 0;
          }

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

          $rows[$media_id] = $row;
        }

        // Handle file size sorting if needed.
        if ($field === 'filesize') {
          // Sort by file size.
          uasort($rows, function ($a, $b) use ($direction) {
            if ($direction === 'ASC') {
              return $a['#file_size'] <=> $b['#file_size'];
            }
            else {
              return $b['#file_size'] <=> $a['#file_size'];
            }
          });
        }

        $build['table']['#rows'] = $rows;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error loading media entities: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Build the filter form.
   *
   * @return array
   *   A render array representing the filter form.
   */
  protected function buildFilterForm() {
    return $this->formBuilder->getForm('\Drupal\onlyoffice_form\Form\OnlyofficeFormFilterForm', $this->keys);
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
