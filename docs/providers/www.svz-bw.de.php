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
    'url' => 'http://www.svz-bw.de/fileadmin/templates/vizbw1/tabkamera.php?filter_strasse=ALL&filter_bereich=ALL&showresult=1&cachebreaker={%UNIX_TIMESTAMP%}',
    'callback' => function($response, array $provider_config) {
        $index = json_decode($response);

        if (!isset($index->result) || !is_array($index->result)) {
            throw new Exception('Result not set or not an array');
        }

        $buffer = [];

        foreach ($index->result as $result) {
            if ($result->artdereinbindung !== null) {
                // Not supported
                continue;
            }

            $ConfigID = strtoupper(hash('crc32', $result->name . '@www.svz-bw.de'));

            $buffer[$ConfigID] = [
                'ConfigID' => $ConfigID,
                'Category' => 'DE-BW',
                'ProviderID' => $result->name,
                'Street' => $result->strasse,
                'Area' => $result->bereich_name,
                'Location' => $result->bezeichnung,
                'Direction' => $result->blickrichtung,
                'HTTP' => [
                    'URL' => "http://www.svz-bw.de/kamera/ftpdata/{$result->name}/{$result->name}_gross.jpg?{%UNIX_TIMESTAMP%}",
                    'Referer' => "http://www.svz-bw.de/fileadmin/templates/vizbw1/kameradetail.php?id={$result->name}",
                ],
                'Source' => 'Straßenverkehrszentrale Baden-Württemberg (www.svz-bw.de)',
                'Version' => time(),
            ];
        }

        return $buffer;
    }
];
