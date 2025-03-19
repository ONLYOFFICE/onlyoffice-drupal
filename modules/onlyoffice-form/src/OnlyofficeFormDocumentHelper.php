<?php

namespace Drupal\onlyoffice_form;

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

/**
 * Set of tools for working with forms.
 */
class OnlyofficeFormDocumentHelper {
    /**
     * Checking pdf onlyoffice form by file content
     *
     * @param string $fileContent file content
     * @return bool
     */
    public function isOnlyofficeForm($fileContent) {
        $onlyofficeFormMetaTag = "ONLYOFFICEFORM";

        $indexFirst = strpos($fileContent, "%\xCD\xCA\xD2\xA9\x0D");
        if ($indexFirst === false) {
            return false;
        }

        $pFirst = substr($fileContent, $indexFirst + 6);
        if (!str_starts_with($pFirst, "1 0 obj\n<<\n")) {
            return false;
        }

        $pFirst = substr($pFirst, 11);

        $indexStream = strpos($pFirst, "stream\x0D\x0A");
        $indexMeta = strpos($pFirst, $onlyofficeFormMetaTag);

        if ($indexStream === false || $indexMeta === false || $indexStream < $indexMeta) {
            return false;
        }

        $pMeta = substr($pFirst, $indexMeta);
        $pMeta = substr($pMeta, strlen($onlyofficeFormMetaTag) + 3);

        $indexMetaLast = strpos($pMeta, " ");
        if ($indexMetaLast === false) {
            return false;
        }

        $pMeta = substr($pMeta, $indexMetaLast + 1);

        $indexMetaLast = strpos($pMeta, " ");
        if ($indexMetaLast === false) {
            return false;
        }

        return true;
    }
}