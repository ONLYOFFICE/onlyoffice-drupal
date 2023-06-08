/*
 * (c) Copyright Ascensio System SIA 2023
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
  if (typeof DocsAPI !== 'undefined') {

    let editors = document.getElementsByClassName("onlyoffice-editor");

    let count = editors.length;
    for (let i = 0; i < count; i++) {
      let dataId = editors[0].id;
      editors[0].id = editors[0].id + "_" + i;
      new DocsAPI.DocEditor(editors[0].id, drupalSettings.onlyofficeData[dataId].config);
    }
  }
})(Drupal);
