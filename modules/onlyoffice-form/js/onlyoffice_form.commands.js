/**
 * @file
 * Defines custom Ajax commands for the ONLYOFFICE form module.
 */

/*
 * (c) Copyright Ascensio System SIA 2025
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

(function (Drupal) {
  'use strict';

  /**
   * Command to open a URL in a new tab.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal Ajax object.
   * @param {object} response
   *   The Ajax response.
   * @param {string} response.url
   *   The URL to open in a new tab.
   */
  Drupal.AjaxCommands.prototype.openInNewTab = function (ajax, response) {
    window.open(response.url, '_blank');
  };

})(Drupal);
