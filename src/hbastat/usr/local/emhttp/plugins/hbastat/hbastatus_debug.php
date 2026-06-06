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

const ES = ' ';

include 'lib/Main.php';
include 'lib/Hba.php';
require_once 'lib/Error.php';

use hbastat\lib\Hba;
use hbastat\lib\Main;
use hbastat\lib\Error;

// Clear debug file
file_put_contents("/tmp/hbastat_debug.txt", "");

if (!isset($hbastat_cfg)) {
    $hbastat_cfg = Main::getSettings();
}

// Debug: Show what command we're using
error_log("HBA DEBUG: Using command: " . ($hbastat_cfg['STORCLI_PATH'] ?? 'NOT SET'));
file_put_contents("/tmp/hbastat_debug.txt", "Using command: " . ($hbastat_cfg['STORCLI_PATH'] ?? 'NOT SET') . "\n", FILE_APPEND);

// $hbastat_inventory should be set if called from settings page code
if (isset($hbastat_inventory) && $hbastat_inventory) {
    $hbastat_cfg['inventory'] = true;
    // Settings page looks for $hbastat_data specifically -- inventory all supported HBA types
    $hbastat_data = (new Hba($hbastat_cfg))->getInventory();
} else {
    $hbastat_data = (new Hba($hbastat_cfg))->getStatistics();
}

// Debug: Show what we got back
error_log("HBA DEBUG: Got data: " . $hbastat_data);
file_put_contents("/tmp/hbastat_debug.txt", "Got data: " . $hbastat_data . "\n", FILE_APPEND);

$json = $hbastat_data ;
header('Content-Type: application/json');
header('Content-Length:' . ES . strlen($json));
echo $json;
file_put_contents("/tmp/hbajson2_debug","Time = ".date(DATE_RFC2822)."\n") ;
file_put_contents("/tmp/hbajson2_debug",$json."\n",FILE_APPEND) ;

?>