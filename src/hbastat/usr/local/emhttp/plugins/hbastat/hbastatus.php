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

require_once __DIR__ . '/lib/Main.php';
require_once __DIR__ . '/lib/Hba.php';
require_once __DIR__ . '/lib/Error.php';

use hbastat\lib\Hba;
use hbastat\lib\Main;

const HBASTAT_NOTIFY_SCRIPT = '/usr/local/emhttp/webGui/scripts/notify';
const HBASTAT_NOTIFY_STATE  = '/var/tmp/hbastat_notify_state';

if (!isset($hbastat_cfg)) {
    $hbastat_cfg = Main::getSettings();
}

if (isset($hbastat_inventory) && $hbastat_inventory) {
    $hbastat_cfg['inventory'] = true;
    $hbastat_data = (new Hba($hbastat_cfg))->getInventory();
    $json = json_encode($hbastat_data);
} else {
    $json = (new Hba($hbastat_cfg))->getStatistics();
    hbastat_check_notify($json, $hbastat_cfg);
}

header('Content-Type: application/json');
header('Content-Length: ' . strlen($json));
echo $json;

/**
 * If any controller exceeds the configured critical temperature, send an
 * Unraid notification — respecting the per-controller cooldown so we don't
 * spam on every poll.
 */
function hbastat_check_notify(string $json, array $cfg): void
{
    if ((string)($cfg['NOTIFY_ENABLE'] ?? '0') !== '1') {
        return;
    }
    if (!is_file(HBASTAT_NOTIFY_SCRIPT) || !is_executable(HBASTAT_NOTIFY_SCRIPT)) {
        return;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload) || empty($payload['controllers'])) {
        return;
    }

    $critC = (int)($cfg['TEMP_CRIT'] ?? 70);
    $cooldownSec = max(60, ((int)($cfg['NOTIFY_COOLDOWN_MIN'] ?? 60)) * 60);

    $state = [];
    if (is_file(HBASTAT_NOTIFY_STATE)) {
        $raw = @file_get_contents(HBASTAT_NOTIFY_STATE);
        $decoded = $raw === false ? null : json_decode($raw, true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }

    $now = time();
    $changed = false;

    foreach ($payload['controllers'] as $ctl) {
        $id = (string)($ctl['controller'] ?? '');
        if ($id === '') continue;
        $tempRaw = $ctl['temperature'] ?? null;
        if ($tempRaw === null || $tempRaw === 'N/A' || $tempRaw === '') continue;
        $tempC = (int)$tempRaw;

        $lastNotify = (int)($state[$id]['lastNotify'] ?? 0);

        if ($tempC >= $critC) {
            if (($now - $lastNotify) < $cooldownSec) {
                continue;
            }
            $product = $ctl['product'] ?? 'HBA';
            $subject = "HBA Controller {$id} over-temperature";
            $desc = "Controller {$id} ({$product}) at {$tempC} °C — critical threshold is {$critC} °C";
            $cmd = sprintf(
                '%s -i %s -s %s -d %s',
                escapeshellcmd(HBASTAT_NOTIFY_SCRIPT),
                escapeshellarg('alert'),
                escapeshellarg($subject),
                escapeshellarg($desc)
            );
            @shell_exec($cmd . ' >/dev/null 2>&1');
            $state[$id] = ['lastNotify' => $now, 'lastTempC' => $tempC];
            $changed = true;
        } else {
            // Reset cooldown once we drop back below crit so the next over-temp
            // event fires immediately instead of being throttled.
            if ($lastNotify !== 0) {
                unset($state[$id]);
                $changed = true;
            }
        }
    }

    if ($changed) {
        @file_put_contents(HBASTAT_NOTIFY_STATE, json_encode($state));
    }
}
