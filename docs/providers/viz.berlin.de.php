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
    'title' => 'Verkehrsinformationszentrale Berlin',
    'url' => 'http://viz.berlin.de/web/guest/2?p_p_id=vizmap_WAR_vizmapportlet_INSTANCE_Ds4N&p_p_lifecycle=0&p_p_state=normal&p_p_mode=view&p_p_col_id=column-1&p_p_col_count=1&_vizmap_WAR_vizmapportlet_INSTANCE_Ds4N_cmd=traffic&_vizmap_WAR_vizmapportlet_INSTANCE_Ds4N_submenu=traffic_webcams',
    'callback' => function($response, array $provider_config) {
        preg_match_all('#var _vizmap_WAR_vizmapportlet_INSTANCE_Ds4N_marker(\d+)Html=\'<b>(.*?)</b><br><img src="(.*?)(&|\?)rand#', $response, $results);

        $buffer = [];

        foreach ($results[2] as $key => $location) {
            $ProviderID = hash('crc32', $results[3][$key]);

            $ConfigID = strtoupper(hash('crc32', $ProviderID . '@viz.berlin.de'));

            $buffer[$ConfigID] = [
                'ConfigID' => $ConfigID,
                'Category' => 'DE-BE',
                'ProviderID' => $ProviderID,
                'Street' => $location,
                'Area' => $location,
                'Location' => $location,
                'Direction' => $location,
                'HTTP' => [
                    'URL' => $results[3][$key],
                    'Referer' => $provider_config['url'],
                ],
                'Source' => 'Verkehrsinformationszentrale Berlin (viz.berlin.de)',
                'Version' => time(),
            ];
        }

        return $buffer;
    }
];
