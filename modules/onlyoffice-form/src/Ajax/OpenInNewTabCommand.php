<?php

namespace Drupal\onlyoffice_form\Ajax;

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

use Drupal\Core\Ajax\CommandInterface;

/**
 * Defines an AJAX command to open a URL in a new tab.
 */
class OpenInNewTabCommand implements CommandInterface {

  /**
   * The URL to open in a new tab.
   *
   * @var string
   */
  protected $url;

  /**
   * Constructs an OpenInNewTabCommand object.
   *
   * @param string $url
   *   The URL to open in a new tab.
   */
  public function __construct($url) {
    $this->url = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'openInNewTab',
      'url' => $this->url,
    ];
  }

}
