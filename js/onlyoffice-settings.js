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
  const BANNER_CLOSED_KEY = 'onlyoffice.cloudBanner.closed';

  const widthTargets = new WeakMap();

  const widthObserver = new ResizeObserver(function (entries) {
    entries.forEach(function (entry) {
      let width = entry.target.offsetWidth;
      if (!width) {
        return;
      }

      widthTargets.get(entry.target).forEach(function (target) {
        target.element.style[target.property] = width + 'px';
      });
    });
  });

  const matchAddressWidth = function (element, property) {
    let form = element.closest('form');
    let field = form && form.querySelector('[name="doc_server_url"]');
    if (!field) {
      return;
    }

    if (!widthTargets.has(field)) {
      widthTargets.set(field, []);
      widthObserver.observe(field);
    }
    widthTargets.get(field).push({ element: element, property: property });
  };

  Drupal.behaviors.onlyofficeSecretToggle = {
    attach: function (context) {
      once(
        'onlyoffice-secret-toggle',
        '[data-onlyoffice-secret]',
        context,
      ).forEach(function (input) {
        matchAddressWidth(input, 'width');

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

  Drupal.behaviors.onlyofficeBanner = {
    attach: function (context) {
      once('onlyoffice-banner', '[data-onlyoffice-banner]', context).forEach(
        function (banner) {
          try {
            if (localStorage.getItem(BANNER_CLOSED_KEY)) {
              return;
            }
          } catch {
            // Show the banner when the browser storage is unavailable.
          }

          let button = document.createElement('button');
          button.type = 'button';
          button.className = 'onlyoffice-banner__close';
          button.setAttribute('aria-label', Drupal.t('Close'));
          button.setAttribute('title', Drupal.t('Close'));

          button.addEventListener('click', function () {
            banner.hidden = true;

            let next = banner.nextElementSibling;
            if (next) {
              next.setAttribute('tabindex', '-1');
              next.focus();
            }

            try {
              localStorage.setItem(BANNER_CLOSED_KEY, '1');
            } catch {
              // Keep the banner closed until the page is reloaded.
            }
          });

          matchAddressWidth(banner, 'maxWidth');

          banner.appendChild(button);
          banner.hidden = false;
        },
      );
    },
  };
})(Drupal, once);
