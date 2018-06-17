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

class EluvielPublicWebcam extends IPSModule {

    const CONFIGS_DIR = 'docs/configs/';
    const DEFAULT_REFRESH_IMAGE_TIMER_INTERVAL = 300;
    const CURL_TIMEOUT = 5;
    const CURL_USERAGENT = 'IPSEluvielPublicWebcam/1.0';
    const STATUS_CODE_CREATING_INSTANCE = 101;
    const STATUS_CODE_OK = 102;
    const STATUS_CODE_ERROR_CONFIG_FILE_NOT_FOUND = 200;
    const STATUS_CODE_ERROR_CONFIG_FILE_INVALID = 201;
    const STATUS_CODE_ERROR_HTTP_AUTHORIZATION_REQUIRED = 401;
    const STATUS_CODE_ERROR_HTTP_FORBIDDEN = 403;
    const STATUS_CODE_ERROR_HTTP_NOT_FOUND = 404;
    const STATUS_CODE_ERROR_HTTP_SERVER_ERROR = 500;
    const STATUS_CODE_ERROR_HTTP_GENERIC = 600;
    const STATUS_CODE_ERROR_CURL_GENERIC = 700;
    const STATUS_CODE_ERROR_CURL_TIMEOUT = 701;
    const STATUS_CODE_ERROR_EMPTY_RESPONSE = 800;
    const MEDIA_TYPE_IMAGE = 1;

    public function __construct($InstanceID) {
        parent::__construct($InstanceID);
    }

    public function Create() {
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger('RefreshImageTimerInterval', self::DEFAULT_REFRESH_IMAGE_TIMER_INTERVAL);
        $this->RegisterPropertyString('ConfigID', '677F1E58');
        //$this->RegisterPropertyString('SelectConfigIDHelper', '');  // Dummy
        // Variables
        if ($this->GetMediaID() === false) {
            $this->CreateMedia();
        }
        // Timer
        $this->RegisterTimer('RefreshImage', $this->ReadPropertyInteger('RefreshImageTimerInterval') * 1000, 'EluvielPublicWebcam_RefreshImage($_IPS[\'TARGET\'], false);');
        $this->SetTimerInterval('RefreshImage', $this->ReadPropertyInteger('RefreshImageTimerInterval') * 1000);
    }

    private function GetMediaID() {
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildID) {
            if (!IPS_MediaExists($ChildID)) {
                continue;
            }

            return $ChildID;
        }

        return false;
    }

    private function CreateMedia() {
        $MediaID = IPS_CreateMedia(self::MEDIA_TYPE_IMAGE);

        copy($this->GetModuleDir('imgs/dummy.jpg'), IPS_GetKernelDir() . 'media/' . $MediaID . '.jpg');
        IPS_SetMediaFile($MediaID, IPS_GetKernelDir() . 'media/' . $MediaID . '.jpg', true);
        IPS_SetName($MediaID, 'CurrentImage');
        IPS_SetParent($MediaID, $this->InstanceID);

        return $MediaID;
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        $this->SetTimerInterval('RefreshImage', $this->ReadPropertyInteger('RefreshImageTimerInterval') * 1000);

        if ($this->GetMediaID() === false) {
            $this->CreateMedia();
        }

        if (!$this->RefreshImage()) {
            return false;
        }

        $this->SetStatus(self::STATUS_CODE_OK);
    }

    public function RefreshImage(bool $EchoMessages = null) {

        $ConfigID = $this->ReadPropertyString('ConfigID');

        if (empty($ConfigID)) {
            $this->LogMessage(__METHOD__, '(ERROR) ConfigID empty (no config selected)', $EchoMessages);
            return false;
        }

        $Config = $this->GetConfig($ConfigID);

        if (!is_object($Config)) {
            $this->LogMessage(__METHOD__, '(ERROR) Could not get config for ' . $ConfigID, $EchoMessages);
            return false;
        }

        $this->LogMessage(__METHOD__, '(INFO) Trying to get image for ConfgID ' . $ConfigID);

        $CURLURL = str_replace(['{%UNIX_TIMESTAMP%}'], [time()], $Config->HTTP->URL);
        $CURLReferer = (!empty($Config->HTTP->Referer)) ? $Config->HTTP->Referer : $Config->Source;

        $Image = $this->CurlRequest($CURLURL, $CURLReferer, $EchoMessages);

        if (empty($Image)) {
            $this->LogMessage(__METHOD__, '(ERROR) Could not get image for ConfigID ' . $ConfigID, $EchoMessages);
            return false;
        }

        $MediaID = $this->GetMediaID();

        if (empty($MediaID) || !IPS_MediaExists($MediaID)) {
            $this->LogMessage(__METHOD__, '(ERROR) Could not find MediaID', $EchoMessages);
            return false;
        }

        $MediaFilePath = IPS_GetKernelDir() . 'media' . DIRECTORY_SEPARATOR . $MediaID . '.jpg';

        if (@file_put_contents($MediaFilePath, $Image)) {
            $this->LogMessage(__METHOD__, '(OK) Stored image for ' . $ConfigID . ' to ' . $MediaFilePath, $EchoMessages);
            IPS_SendMediaEvent($MediaID);
            return true;
        }

        $this->LogMessage(__METHOD__, '(ERROR) Could not refresh webcam image for ' . $ConfigID . ' at ' . $MediaFilePath, $EchoMessages);
        return false;
    }

    public function GetConfigurationForm() {

        $ConfigIDValues = [];

        foreach (glob($this->GetModuleDir(self::CONFIGS_DIR) . DIRECTORY_SEPARATOR . '*.json') as $ConfigFilename) {
            $Config = json_decode(file_get_contents($ConfigFilename));
            $ConfigIDValues[] = [
                'ConfigID' => $Config->ConfigID,
                'Category' => $Config->Category,
                'Street' => $Config->Street,
                'Area' => $Config->Area,
                'Location' => $Config->Location,
                'Direction' => $Config->Direction
            ];
        }

        $ConfigurationFrom = [
            'elements' => [
                [
                    'type' => 'IntervalBox',
                    'name' => 'RefreshImageTimerInterval',
                    'caption' => 'Seconds'
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'ConfigID',
                    'caption' => 'Config ID'
                ]
            ],
            'actions' => [
                [
                    'type' => 'Button',
                    'label' => 'Refresh image now',
                    'onClick' => 'EluvielPublicWebcam_RefreshImage($id, true);'
                ],
                
                [
                    'type' => 'List',
                    'caption' => 'Config ID Helper',
                    'rowCount' => 10,
                    'add' => false,
                    'delete' => false,
                    'sort' => [
                        'column' => 'Street',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'label' => 'Config ID',
                            'name' => 'ConfigID',
                            'width' => '65px'
                        ],
                        [
                            'label' => 'Category',
                            'name' => 'Category',
                            'width' => '50px'
                        ],
                        [
                            'label' => 'Street',
                            'name' => 'Street',
                            'width' => '100px'
                        ],
                        [
                            'label' => 'Area',
                            'name' => 'Area',
                            'width' => '100px'
                        ],
                        [
                            'label' => 'Location',
                            'name' => 'Location',
                            'width' => '150px'
                        ],
                        [
                            'label' => 'Direction',
                            'name' => 'Direction',
                            'width' => '100px'
                        ]
                    ],
                    'values' => $ConfigIDValues
                ],
                [
                    'type' => 'Label',
                    'caption' => ' ',
                    'label' => 'This list is READ ONLY! Please insert the desired Config ID into the "Config ID" text input field!'
                ],
                [
                    'type' => 'Label',
                    'caption' => ' ',
                    'label' => count($ConfigIDValues) . ' configs found'
                ],
            ],
            'status' => [
                ['code' => self::STATUS_CODE_OK, 'caption' => 'OK', 'icon' => 'active'],
                ['code' => self::STATUS_CODE_ERROR_CONFIG_FILE_NOT_FOUND, 'caption' => 'Config file not found', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_CONFIG_FILE_INVALID, 'caption' => 'Config file invalid (JSON error)', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_HTTP_AUTHORIZATION_REQUIRED, 'caption' => 'HTTP 401 (Authorization Required)', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_HTTP_FORBIDDEN, 'caption' => 'HTTP 403 (Forbidden)', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_HTTP_NOT_FOUND, 'caption' => 'HTTP 404 (Not Found)', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_HTTP_SERVER_ERROR, 'caption' => 'HTTP 500 (Server Errror)', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_HTTP_GENERIC, 'caption' => 'HTTP Generic Error', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_CURL_GENERIC, 'caption' => 'cURL Generic Error', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_CURL_TIMEOUT, 'caption' => 'cURL Timeout', 'icon' => 'error'],
                ['code' => self::STATUS_CODE_ERROR_EMPTY_RESPONSE, 'caption' => 'Got empty response', 'icon' => 'error'],
            ]
        ];

        return json_encode($ConfigurationFrom);
    }

    private function GetModuleDir($suffix = null) {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $suffix), '\\/');
    }

    private function GetConfig() {

        $ConfigFilename = $this->GetModuleDir(self::CONFIGS_DIR . trim($this->ReadPropertyString('ConfigID')) . '.json');

        if (!is_file($ConfigFilename) || !is_readable($ConfigFilename)) {
            $this->LogMessage(__METHOD__, '(ERROR) Config file not found or not readable: ' . $ConfigFilename);
            $this->SetStatus(self::STATUS_CODE_ERROR_CONFIG_FILE_NOT_FOUND);
            return false;
        }

        $Config = @json_decode(file_get_contents($ConfigFilename));

        $JSONLastError = json_last_error();

        if ($JSONLastError !== JSON_ERROR_NONE) {
            $this->LogMessage(__METHOD__, '(ERROR) JSON error: "' . json_last_error_msg() . '" in file ' . $ConfigFilename);
            $this->SetStatus(self::STATUS_CODE_ERROR_CONFIG_FILE_INVALID);
            return false;
        }

        return $Config;
    }

    private function LogMessage($Sender, $Message, $EchoMessage = false) {
        IPS_LogMessage($Sender . "[#$this->InstanceID]", $Message);

        if ($EchoMessage === true) {
            echo $Message . PHP_EOL;
        }
    }

    private function CurlRequest($URL, $Referer = null, $EchoMessages = false) {
        $CURL = curl_init($URL);

        curl_setopt($CURL, CURLOPT_CONNECTTIMEOUT, self::CURL_TIMEOUT);
        curl_setopt($CURL, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        curl_setopt($CURL, CURLOPT_USERAGENT, self::CURL_USERAGENT);
        curl_setopt($CURL, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($CURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($CURL, CURLOPT_AUTOREFERER, true);

        if ($Referer !== null) {
            curl_setopt($CURL, CURLOPT_REFERER, $Referer);
        }

        $Response = curl_exec($CURL);

        $CURLError = curl_error($CURL);
        $CURLErrno = curl_errno($CURL);

        if ($CURLErrno !== CURLE_OK) {

            $this->LogMessage(__METHOD__, '(ERROR) CURL Error #' . $CURLErrno . ': ' . $CURLError . ' on ' . $URL, $EchoMessages);

            switch ($CURLErrno) {
                case CURLE_OPERATION_TIMEDOUT:
                    $this->SetStatus(self::STATUS_CODE_ERROR_CURL_TIMEOUT);
                    break;
                default: $this->SetStatus(self::STATUS_CODE_ERROR_CURL_GENERIC);
                    break;
            }
            curl_close($CURL);
            return false;
        }

        $HttpStatusCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);

        if ($HttpStatusCode !== 200) {
            $this->LogMessage(__METHOD__, '(ERROR) HTTP Status ' . $HttpStatusCode . ' on ' . $URL, $EchoMessages);

            switch ($HttpStatusCode) {
                case 401:
                    $this->SetStatus(self::STATUS_CODE_ERROR_HTTP_AUTHORIZATION_REQUIRED);
                    break;
                case 403:
                    $this->SetStatus(self::STATUS_CODE_ERROR_HTTP_FORBIDDEN);
                    break;
                case 404:
                    $this->SetStatus(self::STATUS_CODE_ERROR_HTTP_NOT_FOUND);
                    break;
                case 500:
                    $this->SetStatus(self::STATUS_CODE_ERROR_HTTP_SERVER_ERROR);
                    break;
                default:
                    $this->SetStatus(self::STATUS_CODE_ERROR_HTTP_GENERIC);
                    break;
            }
            curl_close($CURL);
            return false;
        }

        curl_close($CURL);

        if (empty($Response)) {
            $this->SetStatus(self::STATUS_CODEE_ERROR_EMPTY_RESPONSE);
            return false;
        }

        $this->SetStatus(self::STATUS_CODE_OK);
        $this->LogMessage(__METHOD__, '(OK) Got data from ' . $URL . ' (' . strlen($Response) . ' bytes)', $EchoMessages);
        return $Response;
    }

}
