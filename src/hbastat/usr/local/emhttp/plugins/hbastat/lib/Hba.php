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

/**
 * Class Hba
 * @package hbastat\lib
 */
class Hba extends Main
{
    const INVENTORY_PARAM = 'show';

    /** Where slow-changing per-controller info (serial, firmware, drive states) is cached. */
    const CACHE_FILE = '/var/tmp/hbastat_cache.json';

    /** Default cold-cache TTL (seconds) if the cfg doesn't override it. */
    const DEFAULT_COLD_TTL_SEC = 300;

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
     * Retrieves HBA controller inventory and parses into an array.
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
     * Hot path. Returns the per-controller payload as JSON.
     *
     * On each call we only run `/cN show temperature` (sub-second). Everything
     * else — serial, firmware, drive state counts — comes from the cold cache,
     * which is regenerated at most every COLD_TTL_SEC seconds.
     *
     * @return string JSON
     */
    public function getStatistics()
    {
        $cold = $this->getOrRefreshColdCache();

        if (empty($cold['controllers'])) {
            $this->pageData['error'][] = 'No HBA controllers found';
            return json_encode($this->pageData);
        }

        $out = [];
        foreach ($cold['controllers'] as $cached) {
            $tempC = $this->fetchTemperatureC((string)$cached['controller']);
            $entry = $cached;
            $entry['temperature'] = $tempC === null ? 'N/A' : (string)$tempC;
            $out[] = $entry;
        }

        return json_encode(['controllers' => $out]);
    }

    /**
     * Read the cache file if it exists and is within TTL; otherwise rebuild it.
     */
    private function getOrRefreshColdCache(): array
    {
        $ttl = (int)($this->settings['COLD_TTL_SEC'] ?? self::DEFAULT_COLD_TTL_SEC);
        if ($ttl < 30) $ttl = 30;

        if (is_file(self::CACHE_FILE)) {
            $age = time() - filemtime(self::CACHE_FILE);
            if ($age < $ttl) {
                $raw = @file_get_contents(self::CACHE_FILE);
                $decoded = $raw === false ? null : json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['controllers'])) {
                    return $decoded;
                }
            }
        }

        return $this->refreshColdCache();
    }

    /**
     * Run the cold-path storcli calls and rebuild the cache file.
     *
     * Cold = data that rarely changes:
     *   - Controller identity (vendor, product) from inventory
     *   - Serial Number + Firmware Version from `/cN show`
     *   - Physical drive state counts from `/cN/eall/sall show` (brief table)
     */
    private function refreshColdCache(): array
    {
        $controllers = $this->getInventory();
        $payload = [
            'generated_at' => time(),
            'controllers'  => [],
        ];

        foreach ($controllers as $c) {
            $id = (string)$c['id'];

            $info = $this->fetchControllerInfo($id);
            $drives = $this->fetchDriveStates($id);

            $payload['controllers'][] = array_merge([
                'controller' => $id,
                'vendor'     => $c['vendor']    ?? 'Unknown',
                'product'    => $c['model']     ?? 'Unknown',
                'serialno'   => $info['serial']   ?? 'N/A',
                'firmware'   => $info['firmware'] ?? 'N/A',
            ], $drives);
        }

        @file_put_contents(self::CACHE_FILE, json_encode($payload));
        return $payload;
    }

    /**
     * Hot poll. Returns the ROC temperature in °C as an int, or null on failure.
     */
    private function fetchTemperatureC(string $id): ?int
    {
        $this->runCommand($this->settings['cmd'], "/c{$id} show temperature", false);
        if (empty($this->stdout)) {
            return null;
        }
        foreach (explode("\n", $this->stdout) as $line) {
            if (preg_match('/ROC temperature\(Degree Celsius\)\s*=?\s*(\d+)/i', $line, $m)) {
                return (int)$m[1];
            }
        }
        return null;
    }

    /**
     * Pull Serial Number + Firmware Version from `/cN show` (much smaller than `show all`).
     *
     * @return array{serial:string,firmware:string}
     */
    private function fetchControllerInfo(string $id): array
    {
        $serial = 'N/A';
        $firmware = 'N/A';

        $this->runCommand($this->settings['cmd'], "/c{$id} show", false);
        if (empty($this->stdout)) {
            return ['serial' => $serial, 'firmware' => $firmware];
        }

        foreach (explode("\n", $this->stdout) as $line) {
            $t = trim($line);
            if (preg_match('/^Serial Number\s*=\s*(.+)$/i', $t, $m)) {
                $serial = trim($m[1]);
            } elseif (preg_match('/^Firmware Version\s*=\s*(.+)$/i', $t, $m)) {
                $firmware = trim($m[1]);
            }
        }
        return ['serial' => $serial, 'firmware' => $firmware];
    }

    /**
     * Parse the brief physical-drive table from `/cN/eall/sall show`.
     *
     * The table looks like:
     *   EID:Slt DID State DG       Size Intf Med SED PI SeSz Model           Sp
     *    :0       1 UGood -    14.552 TB SATA HDD - N  512B ST...             -
     *
     * Predictive count is approximated from UBad/Failed state in the brief
     * table — getting the per-drive SMART alert flag would require the
     * verbose `show all` per drive, which defeats the point of the cold-path
     * simplification.
     *
     * @return array<string,int>
     */
    private function fetchDriveStates(string $id): array
    {
        $counts = [
            'present'     => 0,
            'optimal'     => 0,
            'failed'      => 0,
            'offline'     => 0,
            'rebuild'     => 0,
            'predictive'  => 0,
            'missing'     => 0,
            'degraded'    => 0,
            'consistency' => 0,
            'background'  => 0,
        ];

        $this->runCommand($this->settings['cmd'], "/c{$id}/eall/sall show", false);
        if (empty($this->stdout)) {
            return $counts;
        }

        $insideTable = false;
        foreach (explode("\n", $this->stdout) as $line) {
            $t = trim($line);

            if (stripos($t, 'EID:Slt') !== false && stripos($t, 'State') !== false) {
                $insideTable = true;
                continue;
            }
            if (!$insideTable) {
                continue;
            }
            // End of table: a legend row like "EID-Enclosure Device ID|..."
            if (preg_match('/^(EID|UGood|Med|SeSz|DG)-/', $t)) {
                $insideTable = false;
                continue;
            }
            // Skip separators and blank lines.
            if (strpos($t, '----') === 0 || $t === '') {
                continue;
            }

            $cols = preg_split('/\s+/', $t);
            if (count($cols) < 3) {
                continue;
            }

            $state = strtolower($cols[2]);
            $counts['present']++;
            switch ($state) {
                case 'ugood':
                case 'onln':
                case 'jbod':
                case 'ghs':
                case 'dhs':
                    $counts['optimal']++;
                    break;
                case 'ubad':
                case 'failed':
                case 'fld':
                    $counts['failed']++;
                    $counts['predictive']++;
                    break;
                case 'offln':
                case 'offline':
                    $counts['offline']++;
                    break;
                case 'rbld':
                case 'rebuild':
                    $counts['rebuild']++;
                    break;
            }
        }

        return $counts;
    }
}
