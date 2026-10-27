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

/**
 * The document status sent by the document editing service in a callback.
 */
enum OnlyofficeCallbackStatus: int {

  case NotFound = 0;
  case Editing = 1;
  case MustSave = 2;
  case Corrupted = 3;
  case Closed = 4;
  case MustForceSave = 6;
  case CorruptedForceSave = 7;

  /**
   * Returns the status for a callback value, or NULL if it is not valid.
   */
  public static function fromCallback(mixed $status): ?self {
    return is_int($status) ? self::tryFrom($status) : NULL;
  }

}
