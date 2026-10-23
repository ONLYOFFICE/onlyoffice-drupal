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

use Drupal\Core\Field\FieldTypeCategoryManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the ONLYOFFICE form module.
 */
class OnlyofficeFormHooks {

  use StringTranslationTrait;

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
      ],
    ];
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

}
