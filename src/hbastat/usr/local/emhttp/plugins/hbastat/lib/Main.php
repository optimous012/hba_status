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

/** @noinspection PhpIncludeInspection */
require_once('/usr/local/emhttp/plugins/dynamix/include/Wrappers.php');
require_once __DIR__ . '/Error.php';

/**
 * Class Main
 * @package hbastat\lib
 */
class Main
{
    const PLUGIN_NAME = 'hbastat';
    const COMMAND_EXISTS_CHECKER = 'which';

    /**
     * @var array
     */
    public $settings;

    /**
     * @var string
     */
    protected $stdout;

    /**
     * @var array
     */
    protected $inventory;

    /**
     * @var array
     */
    protected $pageData;

    /**
     * @var bool
     */
    protected $cmdexists;

    /**
     * HBASat constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
        // Use the configured command path, or default to 'storcli'
        if (!isset($this->settings['STORCLI_PATH']) || empty($this->settings['STORCLI_PATH'])) {
            $this->settings['STORCLI_PATH'] = 'storcli';
        }

        if (isset($this->settings['inventory'])) {
            $this->checkCommand($this->settings['STORCLI_PATH'], false);
        } else {
            $this->checkCommand($this->settings['STORCLI_PATH']);
        }

        $this->stdout = '';
        $this->inventory = [];

        $this->pageData = [
            'controller' => 'N/A',
            'vendor' => 'N/A',
            'product' => 'N/A',
            'serialno' => 'N/A',
            'firmware' => 'N/A',
            'temperature' => 'N/A',
            'present' => 'N/A',
            'missing' => 'N/A',
            'optimal' => 'N/A',
            'failed' => 'N/A',
            'degraded' => 'N/A',
            'offline' => 'N/A',
            'rebuild' => 'N/A',
            'consistency' => 'N/A',
            'predictive' => 'N/A',
            'background' => 'N/A',
        ];
    }

    /**
     * Checks if vendor utility exists in the system and dies if it does not
     *
     * @param string $utility
     * @param bool $error
     */
    protected function checkCommand(string $utility, bool $error = true)
    {
        $this->cmdexists = false;
        // Check if vendor utility is available
        $this->runCommand(self::COMMAND_EXISTS_CHECKER, $utility, false);
        if (!empty($this->stdout)) {
            $this->cmdexists = true;
        } else {
            // Send the error but don't die because we need to continue for inventory
            if ($error) {
                $this->pageData['error'][] = Error::get(Error::VENDOR_UTILITY_NOT_FOUND);
            }
        }
    }

    /**
     * Runs a command in shell and stores STDOUT in class variable
     *
     * @param string $command
     * @param string $argument
     * @param bool $escape
     */
    protected function runCommand(string $command, string $argument = '', bool $escape = true)
    {
        if ($escape) {
            $this->stdout = shell_exec(sprintf("%s %s", $command, escapeshellarg($argument)));
        } else {
            $this->stdout = shell_exec(sprintf("%s %s", $command, $argument));
        }
    }

    /**
     * Retrieves plugin settings and returns them or defaults if no file
     *
     * @return mixed
     */
    public static function getSettings()
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return parse_plugin_cfg(self::PLUGIN_NAME);
    }
}