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
    'title' => 'Landesbetrieb Mobilität Rheinland-Pfalz',
    'url' => 'http://verkehr.rlp.de/index.php?lang=10&menu1=50&menu2=10&menu3=',
    'callback' => function($response, array $provider_config) {

        preg_match_all('#\<tr\s+class\=\'(.*?)\'\>(.*?)\<\/tr\>#ms', $response, $rows);

        $currentCategory = 'Unknown';
        $currentLocation = 'Unknown';
        $currentLocationShort = 'Unknown';
        $currentStreet = 'Unknown';
        $currentKM = 'Unknown';

        $buffer = [];

        foreach ($rows[2] as $key => $row) {
            if ($rows[1][$key] === 'webcam_bab_row') {
                $currentCategory = utf8_encode(preg_replace('/\s+/', ' ', trim(strip_tags($row))));
                continue;
            }

            if ($rows[1][$key] === 'webcam_as_row') {
                $locationMeta = [];
                if (preg_match("#<td class='webcam_dir'>&uArr;</td><td class='webcam_bab'>(.*?)</td><td class='webcam_km'>(.*?)</td><td></td><td class='webcam_as'>(.*?)</td><td class='webcam_dir'>&uArr;\s+</td>#", $row, $locationMeta)) {
                    $currentLocation = utf8_encode(trim($locationMeta[1]) . ', ' . trim($locationMeta[3]) . ' (' . trim($locationMeta[2]) . ')');
                    $currentLocationShort = utf8_encode(trim($locationMeta[3]));
                    $currentStreet = trim($locationMeta[1]);
                    $currentKM = trim($locationMeta[2]);
                } else {
                    eluviel_console_out('ERROR: Could not parse webcam_as_row');
                    $currentLocation = 'Unknown';
                    $currentLocationShort = 'Unknown';
                    $currentStreet = 'Unknown';
                    $currentKM = 'Unknown';
                }
                continue;
            }

            if ($rows[1][$key] === 'webcam_img_row') {
                $imageMatch = [];

                if (preg_match('#src=\'(http://verkehr.rlp.de/syncdata/cam/(\d+)/thumb_240x180.jpg)#', $row, $imageMatch)) {
                    $HTTPURL = str_replace('thumb_240x180.jpg', 'thumb_640x480.jpg', $imageMatch[1]);
                    $ProviderID = $imageMatch[2];

                    // Add config

                    $ConfigID = strtoupper(hash('crc32', $ProviderID . '@verkehr.rlp.de'));

                    $buffer[$ConfigID] = [
                        'ConfigID' => $ConfigID,
                        'Category' => 'DE-RP',
                        'ProviderID' => $ProviderID,
                        'Street' => $currentStreet,
                        'Area' => $currentLocationShort,
                        'Location' => $currentKM,
                        'Direction' => $currentLocation,
                        'HTTP' => [
                            'URL' => $HTTPURL . '?{%UNIX_TIMESTAMP%}',
                            'Referer' => $provider_config['url'],
                        ],
                        'Source' => 'Landesbetrieb Mobilität Rheinland-Pfalz (http://verkehr.rlp.de)',
                        'Version' => time(),
                    ];
                } else {
                    eluviel_console_out('ERROR: Could not parse webcam_img_row');
                }

                continue;
            }

            eluviel_console_out('ERROR: Could not parse row: ' . $row);
        }

        return $buffer;
    }
];
