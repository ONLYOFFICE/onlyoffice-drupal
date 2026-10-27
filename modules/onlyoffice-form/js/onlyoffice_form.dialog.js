/**
 * @file
 * JavaScript behaviors for ONLYOFFICE form dialogs.
 */

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

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Attaches the dialog behaviors.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches event listeners for dialogs.
   */
  Drupal.behaviors.onlyofficeFormDialog = {
    attach: function (context) {
      // Ensure our dialog has proper styling when opened
      $(once('onlyoffice-form-dialog', 'body', context)).on(
        'dialogopen',
        function (event) {
          var $dialog = $(event.target);
          if ($dialog.find('#onlyoffice-form-create-form-wrapper').length) {
            // Add our custom class to the dialog
            $dialog.parent().addClass('onlyoffice-form-ui-dialog');
          }
        },
      );

      // Custom file upload handling
      $(
        once(
          'onlyoffice-form-file-upload',
          '.onlyoffice-form-file-upload',
          context,
        ),
      ).each(function () {
        var $wrapper = $(this).closest('.onlyoffice-form-file-upload-wrapper');

        // Create a custom file input element
        var $fileInput = $(
          '<input type="file" accept=".pdf" style="display:none;" />',
        );
        $wrapper.append($fileInput);

        // Handle the "Choose File" button click
        $wrapper
          .find('.onlyoffice-form-upload-button')
          .on('click', function (e) {
            e.preventDefault();
            $fileInput.trigger('click');
            return false;
          });

        // Handle file selection
        $fileInput.on('change', function () {
          if (this.files && this.files.length > 0) {
            var file = this.files[0];

            // Show loading state
            showLoadingState($wrapper);

            // Create a FormData object
            var formData = new FormData();

            // Add the file to the form data - use the correct name format for managed_file
            formData.append('files[upload_file]', file);

            // Use our custom endpoint for file uploads
            const uploadUrl = Drupal.url('onlyoffice-form/file-upload');

            // Get the CSRF token, then upload the file with it
            fetch(Drupal.url('session/token'))
              .then(function (tokenResponse) {
                if (!tokenResponse.ok) {
                  throw new Error('Could not get the CSRF token');
                }
                return tokenResponse.text();
              })
              .then(function (token) {
                // Send the file to our custom file upload endpoint
                return fetch(uploadUrl, {
                  method: 'POST',
                  body: formData,
                  headers: {
                    'X-CSRF-Token': token,
                  },
                });
              })
              .then(function (uploadResponse) {
                return uploadResponse.json().catch(function () {
                  throw new Error(
                    'Could not parse the upload response (status ' +
                      uploadResponse.status +
                      ')',
                  );
                });
              })
              .then(function (response) {
                // Log the response for debugging
                console.log('File upload response:', response);

                if (response && response.status === 'success' && response.fid) {
                  // Update the hidden input with the file ID
                  $wrapper
                    .find('input[name="upload_file[fids]"]')
                    .val(response.fid);

                  // Show the file in the UI
                  showSelectedFile($wrapper, file);

                  console.log(
                    'File uploaded successfully with ID:',
                    response.fid,
                  );
                } else {
                  console.error('File upload failed:', response);

                  // Only log errors to console
                  if (response && response.message) {
                    console.error('Error message:', response.message);
                  }

                  removeSelectedFile($wrapper);
                }
              })
              .catch(function (error) {
                console.error('File upload error:', error);
                removeSelectedFile($wrapper);
              });
          } else {
            // If no file was selected (user canceled the file dialog)
            console.log('No file selected');
          }
        });

        // Handle the "Remove" button click
        $wrapper.on('click', '.onlyoffice-form-remove-button', function (e) {
          e.preventDefault();
          removeSelectedFile($wrapper);
          return false;
        });
      });

      /**
       * Shows a loading state in the UI.
       *
       * @param {jQuery} $wrapper
       *   The file upload wrapper jQuery object.
       */
      function showLoadingState($wrapper) {
        // Create the loading HTML
        var loadingHtml =
          '<div class="onlyoffice-form-uploading-file">' +
          '<div class="onlyoffice-form-file-loading">' +
          '<div class="onlyoffice-form-loading-spinner"></div>' +
          '<div class="onlyoffice-form-loading-text">' +
          Drupal.t('Uploading...') +
          '</div>' +
          '</div>' +
          '</div>';

        // Replace the upload container with the loading indicator
        $wrapper
          .find('.onlyoffice-form-upload-container')
          .replaceWith(loadingHtml);
      }

      /**
       * Shows the selected file in the UI.
       *
       * @param {jQuery} $wrapper
       *   The file upload wrapper jQuery object.
       * @param {File} file
       *   The selected file object.
       */
      function showSelectedFile($wrapper, file) {
        // Create the file preview HTML
        var filePreviewHtml = `<div class="onlyoffice-form-uploaded-file">
        <div class="onlyoffice-form-file-preview">
          <img src="${drupalSettings.myModule.modulePath}/images/pdf.svg" alt="${Drupal.t('PDF File')}" width="96" height="96">
          <div class="onlyoffice-form-file-info">
            <div class="onlyoffice-form-file-title"><span>${Drupal.t('Title')}</span>: ${Drupal.checkPlain(file.name)}</div>
          </div>
        </div>
        <button type="button" class="onlyoffice-form-remove-button button">${Drupal.t('Remove')}</button>
      </div>`;

        // Replace the upload container or loading indicator with the file preview
        $wrapper
          .find(
            '.onlyoffice-form-upload-container, .onlyoffice-form-uploading-file',
          )
          .replaceWith(filePreviewHtml);
      }

      /**
       * Removes the selected file from the UI.
       *
       * @param {jQuery} $wrapper
       *   The file upload wrapper jQuery object.
       */
      function removeSelectedFile($wrapper) {
        // Clear the hidden input value
        $wrapper.find('input[name="upload_file[fids]"]').val('');

        // Reset the file input to allow selecting the same file again
        $wrapper.find('input[type="file"]').val('');

        // Create the upload button HTML
        var uploadButtonHtml =
          '<div class="onlyoffice-form-upload-container">' +
          '<div class="onlyoffice-form-upload-button-wrapper">' +
          '<button type="button" class="onlyoffice-form-upload-button button">' +
          Drupal.t('Select file') +
          '</button>' +
          '</div>' +
          '</div>';

        // Replace the file preview with the upload container
        $wrapper
          .find(
            '.onlyoffice-form-uploaded-file, .onlyoffice-form-uploading-file',
          )
          .replaceWith(uploadButtonHtml);

        // Reattach click handler to the new button
        $wrapper
          .find('.onlyoffice-form-upload-button')
          .on('click', function (e) {
            e.preventDefault();
            $wrapper.find('input[type="file"]').trigger('click');
            return false;
          });
      }
    },
  };
})(jQuery, Drupal, drupalSettings, once);
