<?php

/*
  MIT License

  Copyright (c) 2026 YourName

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in all
  copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
  SOFTWARE.
*/

namespace hbastat\lib;

use SimpleXMLElement;

/**
 * Class Hba
 * @package hbastat\lib
 */
class Hba extends Main
{
    const INVENTORY_PARAM = 'show';

    /**
     * Vendor ID → human-readable name map.
     */
    private static $vendorMap = [
        '1000' => 'Broadcom / LSI Logic',
        '1028' => 'Dell',
        '103c' => 'HP',
        '1077' => 'QLogic',
        '10df' => 'Marvell',
        '110a' => 'VMware',
        '111d' => 'IBM',
        '1170' => 'Innodisk',
        '11ab' => 'Marvell',
        '1217' => 'Microsemi',
        '1274' => 'LSI Logic / Symbios',
        '13f5' => 'Samsung',
        '144d' => 'Samsung',
        '152d' => 'Microsemi',
        '15b3' => 'NetApp',
        '15b7' => 'Microsemi',
        '15d0' => 'Seagate',
        '15d9' => 'Western Digital',
        '163c' => 'SanDisk',
        '1673' => 'Intel',
        '17aa' => 'Lenovo',
        '1849' => 'Toshiba',
        '1912' => 'Microsemi',
        '1987' => 'Microsemi',
        '19e5' => 'Microsemi',
        '1b4b' => 'Seagate',
        '1daf' => 'AMD',
        '1de1' => 'HP',
    ];

    /**
     * Hba constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        if (!isset($settings['STORCLI_PATH']) || empty($settings['STORCLI_PATH'])) {
            $settings['STORCLI_PATH'] = 'storcli';
        }
        $settings['cmd'] = $settings['STORCLI_PATH'];
        parent::__construct($settings);
    }

    /**
     * Retrieves HBA controller inventory and parses into an array
     *
     * @return array
     */
    public function getInventory(): array
    {
        $result = [];

        if (!$this->cmdexists) {
            return $result;
        }

        $this->runCommand($this->settings['cmd'], self::INVENTORY_PARAM, false);

        if (empty($this->stdout)) {
            return $result;
        }

        $lines = explode("\n", $this->stdout);
        $inControllerSection = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, 'Ctl Model') !== false && strpos($line, 'AdapterType') !== false) {
                $inControllerSection = true;
                continue;
            }

            if (strpos($line, '----') === 0) {
                continue;
            }

            if ($inControllerSection && preg_match('/^\s*(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                $vendId = str_replace('0x', '', $m[4]);
                $result[] = [
                    'id'        => $m[1],
                    'model'     => $m[2],
                    'vendor'    => self::$vendorMap[$vendId] ?? 'Unknown (' . $vendId . ')',
                    'interface' => $m[3],
                ];
            } elseif ($inControllerSection && $line === '') {
                $inControllerSection = false;
            }
        }

        return $result;
    }

    /**
     * Retrieves HBA controller statistics for every detected controller.
     *
     * @return string JSON
     */
    public function getStatistics()
    {
        $controllers = $this->getInventory();

        if (empty($controllers)) {
            $this->pageData['error'][] = 'No HBA controllers found';
            return json_encode($this->pageData);
        }

        $allControllersData = [];

        foreach ($controllers as $controller) {
            $id = $controller['id'];

            $controllerData = [
                'controller'  => $id,
                'vendor'      => $controller['vendor'] ?? 'Unknown',
                'product'     => $controller['model'] ?? 'Unknown',
                'serialno'    => 'N/A',
                'firmware'    => 'N/A',
                'temperature' => 'N/A',
                'present'     => 0,
                'missing'     => 0,
                'optimal'     => 0,
                'failed'      => 0,
                'degraded'    => 0,
                'offline'     => 0,
                'rebuild'     => 0,
                'consistency' => 0,
                'predictive'  => 0,
                'background'  => 0,
            ];

            $this->runCommand($this->settings['cmd'], "/c{$id} show all", false);

            if (!empty($this->stdout)) {
                $this->parseControllerShowAll($this->stdout, $controllerData);
            } else {
                $controllerData['error'] = 'Failed to retrieve HBA statistics';
            }

            $allControllersData[] = $controllerData;
        }

        return json_encode(['controllers' => $allControllersData]);
    }

    /**
     * Parse the output of `storcli64 /cX show all` and fill controllerData in-place.
     *
     * @param string $output
     * @param array  $controllerData
     */
    private function parseControllerShowAll(string $output, array &$controllerData): void
    {
        $lines = explode("\n", $output);

        // First pass: extract scalar fields (Serial Number, Firmware Version, ROC temperature).
        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^Serial Number\s*=\s*(.+)$/i', $line, $m)) {
                $controllerData['serialno'] = trim($m[1]);
                continue;
            }

            if (preg_match('/^Firmware Version\s*=\s*(.+)$/i', $line, $m)) {
                $controllerData['firmware'] = trim($m[1]);
                continue;
            }

            if (preg_match('/ROC temperature\(Degree Celsius\)\s*=?\s*(\d+)/i', $line, $m)) {
                $controllerData['temperature'] = $m[1];
                continue;
            }
        }

        // Second pass: count physical drives by state.
        // Each drive appears under a "Drive /cX/sY :" header followed by a brief
        // table whose 3rd column is State (UGood, UBad, Onln, Offln, Rbld, ...).
        // Also detect SMART alerts in each drive's "Drive .. State :" sub-block.
        $present = 0;
        $optimal = 0;
        $failed = 0;
        $offline = 0;
        $rebuild = 0;
        $predictive = 0;

        $insideBriefTable = false;
        $sawDriveHeaderForTable = false;
        $insideStateSubBlock = false;

        foreach ($lines as $line) {
            $trim = trim($line);

            // Brief one-drive table is preceded by a "Drive /cX/sY :" header,
            // then a separator line of dashes, then the column header line
            // "EID:Slt DID State DG ...", another separator, then a single data row.
            if (preg_match('#^Drive /c\d+/s\d+\s*:\s*$#', $trim)) {
                $sawDriveHeaderForTable = true;
                $insideBriefTable = false;
                $insideStateSubBlock = false;
                continue;
            }

            if (preg_match('#^Drive /c\d+/s\d+ State\s*:\s*$#', $trim)) {
                $insideStateSubBlock = true;
                continue;
            }

            if ($insideStateSubBlock) {
                if ($trim === '' || preg_match('#^Drive /c\d+/s\d+#', $trim)) {
                    $insideStateSubBlock = false;
                } elseif (preg_match('/^S\.M\.A\.R\.T alert flagged by drive\s*=\s*(\S+)/i', $trim, $m)) {
                    if (strcasecmp(trim($m[1]), 'Yes') === 0) {
                        $predictive++;
                    }
                    $insideStateSubBlock = false;
                }
            }

            if ($sawDriveHeaderForTable && stripos($trim, 'EID:Slt') !== false && stripos($trim, 'State') !== false) {
                $insideBriefTable = true;
                continue;
            }

            if ($insideBriefTable) {
                // Skip dashed separators.
                if (strpos($trim, '----') === 0 || $trim === '') {
                    // The first separator we encounter is before the data row; the
                    // second one closes the table. We only count one row per drive
                    // header, so toggle off after we've seen a data row.
                    continue;
                }

                // Data row format (non-breaking spaces around EID may be empty):
                //   :0  1 UGood -    14.552 TB SATA HDD - N 512B ST...  -
                // After the EID:Slt token, columns are: DID, State, DG, Size, ...
                $cols = preg_split('/\s+/', $trim);
                if (count($cols) >= 3) {
                    $state = $cols[2];
                    $present++;
                    switch (strtolower($state)) {
                        case 'ugood':
                        case 'onln':
                        case 'jbod':
                        case 'ghs':
                        case 'dhs':
                            $optimal++;
                            break;
                        case 'ubad':
                        case 'failed':
                        case 'fld':
                            $failed++;
                            break;
                        case 'offln':
                        case 'offline':
                            $offline++;
                            break;
                        case 'rbld':
                        case 'rebuild':
                            $rebuild++;
                            break;
                    }
                }

                // Reset so we don't double-count from the same header.
                $insideBriefTable = false;
                $sawDriveHeaderForTable = false;
            }
        }

        $controllerData['present']    = $present;
        $controllerData['optimal']    = $optimal;
        $controllerData['failed']     = $failed;
        $controllerData['offline']    = $offline;
        $controllerData['rebuild']    = $rebuild;
        $controllerData['predictive'] = $predictive;
        // missing/degraded/consistency/background do not map to IT-mode HBAs.
    }
}
