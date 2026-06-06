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
    // We'll get the command from settings instead of hardcoding it
    const INVENTORY_PARAM = 'show';
    const STATISTICS_PARAM = 'show all';

    /**
     * Hba constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        // Set the command from settings, defaulting to 'storcli' if not set
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

        if ($this->cmdexists) {
            $this->runCommand($this->settings['cmd'], self::INVENTORY_PARAM, false);

            // Debug logging (commented out for production)
            // file_put_contents("/tmp/hbastat_inventory_debug.txt",
            //     "Command: {$this->settings['cmd']} " . self::INVENTORY_PARAM . "\n" .
            //     "Output length: " . strlen($this->stdout) . "\n" .
            //     "Output:\n{$this->stdout}\n---\n",
            //     FILE_APPEND);

            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                // Parse storcli64 show output for controller information
                // Format from storcli64 show:
                // CLI Version = ...
                // Operating system = ...
                // Status Code = 0
                // Status = Success
                // Description = None
                //
                // Number of Controllers = 2
                // Host Name = Cybertron
                // ...
                // IT System Overview :
                // ==================
                // --------------------------------------------------------------------------
                // Ctl Model       AdapterType   VendId DevId SubVendId SubDevId PCI Address
                // --------------------------------------------------------------------------
                //   0 SAS9300-16i   SAS3008(C0) 0x1000  0x97    0x1000   0x3130 00:03:00:00
                //   1 SAS9300-16i   SAS3008(C0) 0x1000  0x97    0x1000   0x3130 00:05:00:00
                // --------------------------------------------------------------------------

                $lines = explode("\n", $this->stdout);
                $controllerInfo = [];
                $inControllerSection = false;

                foreach ($lines as $line) {
                    $line = trim($line);

                    // Skip until we reach the controller table header
                    if (strpos($line, 'Ctl Model') !== false && strpos($line, 'AdapterType') !== false) {
                        $inControllerSection = true;
                        continue;
                    }

                    // Skip separator lines
                    if (strpos($line, '--------------------------------------------------------------------------') !== false) {
                        continue;
                    }

                    // Parse controller data lines (they start with spaces and a number)
                    if ($inControllerSection && preg_match('/^\s*(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $matches)) {
                        $ctlNum = trim($matches[1]);
                        $model = trim($matches[2]);
                        $adapterType = trim($matches[3]);
                        $vendId_raw = trim($matches[4]);
                        $devId = trim($matches[5]);
                        $subVendId = trim($matches[6]);
                        $subDevId = trim($matches[7]);
                        $pciAddress = trim($matches[8]);

                        // Debug logging
                        file_put_contents("/tmp/hbastat_inventory_debug.txt",
                            "Raw line: '" . $line . "'\n" .
                            "Matches[0] = '" . (isset($matches[0]) ? $matches[0] : 'NULL') . "'\n" .
                            "Matches[1] = '" . (isset($matches[1]) ? $matches[1] : 'NULL') . "'\n" .
                            "Matches[2] = '" . (isset($matches[2]) ? $matches[2] : 'NULL') . "'\n" .
                            "Matches[3] = '" . (isset($matches[3]) ? $matches[3] : 'NULL') . "'\n" .
                            "Matches[4] = '" . (isset($matches[4]) ? $matches[4] : 'NULL') . "'\n" .
                            "Matches[5] = '" . (isset($matches[5]) ? $matches[5] : 'NULL') . "'\n" .
                            "Matches[6] = '" . (isset($matches[6]) ? $matches[6] : 'NULL') . "'\n" .
                            "Matches[7] = '" . (isset($matches[7]) ? $matches[7] : 'NULL') . "'\n" .
                            "Matches[8] = '" . (isset($matches[8]) ? $matches[8] : 'NULL') . "'\n",
                            FILE_APPEND);

                        $vendId_raw = trim($matches[4]);
                        file_put_contents("/tmp/hbastat_inventory_debug.txt",
                            "vendId_raw (trimmed matches[4]): '" . $vendId_raw . "'\n",
                            FILE_APPEND);

                        // Extract vendor ID (remove 0x prefix if present)
                        $vendId = str_replace('0x', '', $vendId_raw);
                        file_put_contents("/tmp/hbastat_inventory_debug.txt",
                            "After removing 0x prefix: '" . $vendId . "'\n",
                            FILE_APPEND);

                        // Extract vendor name from vendId (this is a simplification)
                        $vendorName = $this->getVendorNameFromId($vendId);

                        // Debug logging for vendor name
                        file_put_contents("/tmp/hbastat_inventory_debug.txt",
                            "Vendor ID: '" . $vendId . "' (type: " . gettype($vendId) . ") -> Vendor Name: '$vendorName'\n",
                            FILE_APPEND);

                        // Also show what's in the vendor map for this ID
                        $vendIdClean = str_replace('0x', '', $vendId);
                        $mapValue = isset($this->getVendorMap()[$vendIdClean]) ? $this->getVendorMap()[$vendIdClean] : 'NOT FOUND';
                        file_put_contents("/tmp/hbastat_inventory_debug.txt",
                            "Looking up '$vendIdClean' in vendor map: '$mapValue'\n",
                            FILE_APPEND);

                        $result[] = [
                            'id' => $ctlNum,
                            'model' => $model,
                            'vendor' => $vendorName,
                            'serialno' => 'N/A', // Serial number not in this output, would need '/call show' for specific controller
                            'firmware' => 'N/A', // Firmware version not in this output, would need '/call show' for specific controller
                            'interface' => $adapterType
                        ];
                    }
                }

                // Debug logging for results
                file_put_contents("/tmp/hbastat_inventory_debug.txt",
                    "Found controllers: " . count($result) . "\n" .
                    print_r($result, true) . "\n---\n",
                    FILE_APPEND);
            }
        }

        return $result;
    }

    /**
     * Extract vendor name from vendor ID
     *
     * @param string $vendId
     * @return string
     */
    private function getVendorNameFromId(string $vendId): string
    {
        // Remove 0x prefix if present
        $vendId = str_replace('0x', '', $vendId);

        // Map common vendor IDs to names
        $vendorMap = [
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
            '1a03' => 'Device',
            '1b4b' => 'Seagate',
            '1b73' => 'Windows',
            '1bd8' => 'Etron',
            '1c36' => 'Rockchip',
            '1c58' => 'Rockchip',
            '1d6f' => 'Trident',
            '1daf' => 'AMD',
            '1de1' => 'HP',
            '1df0' => 'Realtek',
            '1df7' => 'Realtek',
            '1e13' => 'Realtek',
            '1ecb' => 'Lenovo',
            '1ef7' => 'Realtek',
            '1f24' => 'ASMedia',
            '1fbb' => 'Realtek',
        ];

        return $vendorMap[$vendId] ?? 'Unknown (' . $vendId . ')';
    }

    /**
     * Get the vendor map for debugging
     *
     * @return array
     */
    private function getVendorMap(): array
    {
        return [
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
            '1a03' => 'Device',
            '1b4b' => 'Seagate',
            '1b73' => 'Windows',
            '1bd8' => 'Etron',
            '1c36' => 'Rockchip',
            '1c58' => 'Rockchip',
            '1d6f' => 'Trident',
            '1daf' => 'AMD',
            '1de1' => 'HP',
            '1df0' => 'Realtek',
            '1df7' => 'Realtek',
            '1e13' => 'Realtek',
            '1ecb' => 'Lenovo',
            '1ef7' => 'Realtek',
            '1f24' => 'ASMedia',
            '1fbb' => 'Realtek',
        ];
    }

    /**
     * Retrieves HBA controller statistics
     */
    public function getStatistics()
    {
        // Get inventory first to know what controllers we have
        $controllers = $this->getInventory();

        // Debug logging
        error_log("HBA DEBUG: getStatistics: controllers = " . print_r($controllers, true));

        if (empty($controllers)) {
            $this->pageData['error'][] = 'No HBA controllers found';
            return json_encode($this->pageData);
        }

        // Initialize array to hold all controller data
        $allControllersData = [];

        // Process each controller
        foreach ($controllers as $controller) {
            // Debug logging
            error_log("HBA DEBUG: getStatistics: processing controller = " . print_r($controller, true));

            // Set basic controller info
            $controllerData = [
                'controller' => $controller['id'] ?? 'N/A',
                'vendor' => isset($controller['vendor']) ? $controller['vendor'] : 'Unknown',
                'product' => isset($controller['model']) ? $controller['model'] : 'Unknown',
                'serialno' => isset($controller['serialno']) ? $controller['serialno'] : 'N/A',
                'firmware' => isset($controller['firmware']) ? $controller['firmware'] : 'N/A',
                'temperature' => 'N/A', // Default value
            ];

            // Run detailed statistics command for this controller to get temperature
            $statisticsParam = "/c{$controller['id']} show temperature";
            $this->runCommand($this->settings['cmd'], $statisticsParam, false);

            if (!empty($this->stdout) && strlen($this->stdout) > 0) {
                $this->parseStatistics($this->stdout);
                // Update temperature from parsed statistics
                if (isset($this->pageData['temperature']) && $this->pageData['temperature'] !== 'N/A') {
                    $controllerData['temperature'] = $this->pageData['temperature'];
                }
            } else {
                $controllerData['error'] = 'Failed to retrieve HBA statistics';
            }

            // Add to our collection
            $allControllersData[] = $controllerData;
        }

        // If there's only one controller and no specific CONTROLLERID set,
        // maintain backward compatibility by returning single controller data
        if (count($controllers) == 1 && !isset($this->settings['CONTROLLERID'])) {
            return json_encode($allControllersData[0]);
        }

        // Return all controllers data
        return json_encode(['controllers' => $allControllersData]);
    }

    /**
     * Parses HBA statistics from storcli output
     *
     * @param string $output
     */
    private function parseStatistics(string $output)
    {
        $lines = explode("\n", $output);
        $currentSection = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and headers
            if (empty($line) || preg_match('/Status Code|Status|Description|Number of Controllers/', $line)) {
                continue;
            }

            // Look for controller section
            if (preg_match('/^\s*\/\//', $line)) {
                continue; // Skip comment lines
            }

            // Look for temperature line (format: ROC temperature(Degree Celsius) 51)
            if (preg_match('/ROC temperature\(Degree Celsius\)\s*(\d+)/i', $line, $tempMatches)) {
                $this->pageData['temperature'] = $tempMatches[1];
                continue;
            }

            if (preg_match('/^Host Controller/', $line)) {
                $currentSection = 'controller';
                continue;
            }

            if (preg_match('/^Drive /', $line)) {
                $currentSection = 'drives';
                continue;
            }

            if (preg_match('/^VD /', $line)) {
                $currentSection = 'virtual_drives';
                continue;
            }

            // Parse key-value pairs (standard format: key = value)
            if (preg_match('/^(.+?)\s*=\s*(.+)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);

                // Map storcli output to our pageData
                switch ($key) {
                    case 'temperature':
                    case 'roc temperature':
                    case 'roc temperature(degree celsius)':
                        $this->pageData['temperature'] = $value;
                        break;
                    case 'present':
                        $this->pageData['present'] = $value;
                        break;
                    case 'missing':
                        $this->pageData['missing'] = $value;
                        break;
                    case 'optimal':
                        $this->pageData['optimal'] = $value;
                        break;
                    case 'failed':
                    case 'fld':
                        $this->pageData['failed'] = $value;
                        break;
                    case 'degraded':
                    case 'dgrd':
                        $this->pageData['degraded'] = $value;
                        break;
                    case 'offline':
                    case 'offln':
                        $this->pageData['offline'] = $value;
                        break;
                    case 'rebuild':
                    case 'rbld':
                        $this->pageData['rebuild'] = $value;
                        break;
                    case 'consistency':
                    case 'chky':
                        $this->pageData['consistency'] = $value;
                        break;
                    case 'predictive':
                    case 'chkw':
                        $this->pageData['predictive'] = $value;
                        break;
                    case 'background':
                    case 'bgi':
                        $this->pageData['background'] = $value;
                        break;
                }
            }
        }

        // Basic controller info was already set in getStatistics()
        // No need to reset it here
    }
}