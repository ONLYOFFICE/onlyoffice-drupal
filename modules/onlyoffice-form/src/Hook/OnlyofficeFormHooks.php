<?php

namespace Drupal\onlyoffice_form\Hook;

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
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Field\FieldTypeCategoryManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;

/**
 * Hook implementations for the ONLYOFFICE form module.
 */
class OnlyofficeFormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new OnlyofficeFormHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.onlyoffice_form':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The ONLYOFFICE form module provides integration with ONLYOFFICE forms.') . '</p>';
        return $output;
    }

    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'onlyoffice_form_file_upload' => [
        'render element' => 'element',
        'initial preprocess' => static::class . ':preprocessFileUpload',
      ],
    ];
  }

  /**
   * Prepares variables for onlyoffice form file upload templates.
   *
   * Default template: onlyoffice-form-file-upload.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: A render element representing the file.
   */
  public function preprocessFileUpload(array &$variables): void {
    $element = $variables['element'];

    $variables['attributes'] = [];
    if (isset($element['#id'])) {
      $variables['attributes']['id'] = $element['#id'];
    }
    if (!empty($element['#attributes']['class'])) {
      $variables['attributes']['class'] = (array) $element['#attributes']['class'];
    }

    $fid = NULL;
    if (isset($element['#value']) && !empty($element['#value'])) {
      $fid = $element['#value'];
    }

    if (is_array($fid)) {
      // Check if it's in the format used by managed_file.
      if (isset($fid['fids']) && is_array($fid['fids']) && !empty($fid['fids'])) {
        $fid = reset($fid['fids']);
      }
      else {
        $fid = reset($fid);
      }
    }

    $file = $fid ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;

    $variables['has_file'] = !empty($fid) && !empty($file);
    $variables['file'] = NULL;

    if ($file instanceof FileInterface) {
      $variables['file'] = [
        'id' => $file->id(),
        'name' => $file->getFilename(),
      ];
    }

    $variables['description'] = $element['#description'] ?? '';

    // Pass the hidden elements to the template.
    $variables['element'] = $element;

    // Add module path for image URLs.
    $module_path = $this->extensionPathResolver->getPath('module', 'onlyoffice_form');
    $variables['module_path'] = base_path() . $module_path;
  }

  /**
   * Implements hook_field_type_category_info_alter().
   */
  #[Hook('field_type_category_info_alter')]
  public function fieldTypeCategoryInfoAlter(array &$definitions): void {
    // The `onlyoffice_form` field type belongs in the `general` category, so
    // the libraries need to be attached using an alter hook.
    $definitions[FieldTypeCategoryManagerInterface::FALLBACK_CATEGORY]['libraries'][] = 'onlyoffice_form/onlyoffice_form.onlyoffice_form-icon';
  }

  /**
   * Implements hook_locale_translation_projects_alter().
   */
  #[Hook('locale_translation_projects_alter')]
  public function localeTranslationProjectsAlter(array &$projects): void {
    if (!isset($projects['onlyoffice_form'])) {
      return;
    }

    $path = $this->extensionPathResolver->getPath('module', 'onlyoffice_form');
    $projects['onlyoffice_form']['info']['interface translation server pattern'] = $path . '/translations/onlyoffice_form.%language.po';
  }

}
