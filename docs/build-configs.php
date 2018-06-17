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

define('ELUVIEL_CONFIGS_OUTPUT_PATH', __DIR__ . '/configs');

$providers = [
    'www.svz-bw.de' => require(__DIR__ . '/providers/www.svz-bw.de.php'),
    'viz.berlin.de' => require(__DIR__ . '/providers/viz.berlin.de.php'),
    'verkehr.rlp.de' => require(__DIR__ . '/providers/verkehr.rlp.de.php'),
    'www.bayerninfo.de' => require(__DIR__ . '/providers/www.bayerninfo.de.php')
];

function eluviel_console_out($message) {
    echo date('r') . ' - ' . $message . PHP_EOL;
}

function eluviel_curl_get($url) {

    $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0',
        'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.139 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/64.0.3282.119 Safari/537.36',
        'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:60.0) Gecko/20100101 Firefox/60.0'
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_USERAGENT, $user_agents[mt_rand(0, count($user_agents) - 1)]);
    curl_setopt($curl, CURLOPT_URL, str_replace(['{%UNIX_TIMESTAMP%}'], [time()], $url));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($curl);

    $curl_error_number = curl_errno($curl);
    $curl_error_message = curl_error($curl);
    $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($curl_error_number !== 0 || !empty($curl_error_message)) {
        throw new Exception("cURL error $curl_error_number: $curl_error_message", 0x5b256d8e);
    }

    if ($http_status_code !== 200) {
        throw new Exception("HTTP error $curl_error_number", 0x5b256da3);
    }

    return $response;
}

eluviel_console_out('eluviel Public Webcams Config Builder started');
foreach ($providers as $provider_name => $provider_config) {
    eluviel_console_out("Processing provider {$provider_config['title']} [$provider_name]...");

    try {
        $response = eluviel_curl_get($provider_config['url']);
    } catch (Exception $ex) {
        eluviel_console_out("ERROR: #{$ex->getCode()}: {$ex->getMessage()}");
        continue;
    }

    eluviel_console_out('Running callback');
    try {
        $callback_result = call_user_func_array($provider_config['callback'], ['response' => $response, 'provider_config' => $provider_config]);
    } catch (Exception $ex) {
        eluviel_console_out("ERROR: Callback failed: #{$ex->getCode()}: {$ex->getMessage()}");
        continue;
    }
    eluviel_console_out("Callback finished");

    if (!is_array($callback_result)) {
        eluviel_console_out("ERORR: Callback result is not an array");
        continue;
    }

    foreach ($callback_result as $config_id => $config) {
        $config_filename = strtoupper(hash('crc32', $config_id));
        eluviel_console_out("Writing config \"$config_id\"");
        file_put_contents(ELUVIEL_CONFIGS_OUTPUT_PATH . DIRECTORY_SEPARATOR . $config_id . '.json', json_encode($config, JSON_PRETTY_PRINT));
    }
}
eluviel_console_out('eluviel Public Webcams Config Builder finished');
