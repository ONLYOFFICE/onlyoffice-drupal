<?php

namespace Drupal\onlyoffice;

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

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Handles the callback statuses shared by the ONLYOFFICE callback controllers.
 */
trait OnlyofficeCallbackTrait {

  /**
   * Logs users connecting to and disconnecting from co-editing.
   */
  protected function processEditing(object $body, array $context): JsonResponse {
    match ($body->actions[0]->type ?? NULL) {
      0 => $this->getLogger('onlyoffice')->notice('Disconnected from the media @type %label co-editing.', $context),
      1 => $this->getLogger('onlyoffice')->notice('Connected to the media @type %label co-editing.', $context),
      default => NULL,
    };

    return new JsonResponse(['error' => 0], 200);
  }

}
