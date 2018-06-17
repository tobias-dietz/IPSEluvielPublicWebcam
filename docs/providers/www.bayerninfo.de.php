<?php

/*
 * This file is part of eluviel Public Webcam module for IP-Symcon.
 * Copyright (C) 2018 Tobias Dietz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

return [
    'title' => 'Straßenverkehrszentrale Baden-Württemberg',
    'url' => 'http://www.bayerninfo.de/webcams/webcams-all-muenchen-nuernberg',
    'callback' => function($response, array $provider_config) {

        $folders = [];

        preg_match_all('#<li class="item-\d+"><a href="(/webcams/[a-z0-9/_\-]+)" >.*?</a></li>#', $response, $folders);

        $buffer = [];

        foreach (array_unique($folders[1]) as $folderIndexUrlRelative) {
            $folderIndexUrl = 'http://www.bayerninfo.de' . $folderIndexUrlRelative;
            eluviel_console_out('Following folder ' . $folderIndexUrl);

            $folderIndex = eluviel_curl_get($folderIndexUrl);
            $folderIndexImageItems = [];
            preg_match_all('#<li class="sigFreeThumb">(.*?)</li>#sm', $folderIndex, $folderIndexImageItems);



            foreach ($folderIndexImageItems[0] as $folderIndexImageItem) {

                $matches = [];
                preg_match('#src="(.*?)"#', $folderIndexImageItem, $matches);
                $HTTPURL = 'http://www.bayerninfo.de' . $matches[1];

                $matches = [];
                preg_match('#<span class="sig-caption-route-ALL" align="bottom" >(.*?)</span>#', $folderIndexImageItem, $matches);
                $location = htmlspecialchars_decode($matches[1]);

                $matches = [];
                preg_match('#<span class="sig-caption-direction-ALL" style="width:200px;" align="bottom">(.*?)</span>#', $folderIndexImageItem, $matches);
                $meta = htmlspecialchars_decode($matches[1]);

                $ProviderID = basename($HTTPURL, '.jpg');

                $ConfigID = strtoupper(hash('crc32', $ProviderID . '@www.bayerninfo.de'));

                $buffer[$ConfigID] = [
                    'ConfigID' => $ConfigID,
                    'Category' => 'DE-BY',
                    'ProviderID' => $ProviderID,
                    'Street' => $location,
                    'Area' => $location,
                    'Location' => $location,
                    'Direction' => $meta,
                    'HTTP' => [
                        'URL' => $HTTPURL,
                        'Referer' => $folderIndexUrl,
                    ],
                    'Source' => 'Bayerisches Staatsministerium des Innern, für Bau und Verkehr sowie Konsortium VIB und VIB Verkehrsinformationsagentur Bayern GmbH (http://www.bayerninfo.de)',
                    'Version' => time(),
                ];
            }
        }

        return $buffer;
    }
];
