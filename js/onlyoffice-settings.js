/*
 * (c) Copyright Ascensio System SIA 2026
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

(function (Drupal, once) {
  Drupal.behaviors.onlyofficeSecretToggle = {
    attach: function (context) {
      once(
        'onlyoffice-secret-toggle',
        '[data-onlyoffice-secret]',
        context,
      ).forEach(function (input) {
        // Keep the original width, so the padding for the toggle does not stretch the field.
        if (input.offsetWidth) {
          input.style.width = input.offsetWidth + 'px';
        }

        let wrapper = document.createElement('span');
        wrapper.className = 'onlyoffice-secret';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        let button = document.createElement('button');
        button.type = 'button';
        button.className = 'onlyoffice-secret__toggle';

        let update = function () {
          let hidden = input.type === 'password';
          let label = hidden ? Drupal.t('Show') : Drupal.t('Hide');
          button.setAttribute('aria-label', label);
          button.setAttribute('title', label);
          button.setAttribute('aria-pressed', hidden ? 'false' : 'true');
        };

        button.addEventListener('click', function () {
          input.type = input.type === 'password' ? 'text' : 'password';
          update();
        });

        update();
        wrapper.appendChild(button);
      });
    },
  };
})(Drupal, once);
