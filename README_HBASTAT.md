# HBAStat Plugin for Unraid

## Overview
HBAStat is a plugin for Unraid that provides monitoring of HBA (Host Bus Adapter) controllers in the Unraid dashboard, similar to how GPUStat monitors GPUs.

## Features
- Real-time monitoring of HBA controllers via storcli/storcli64
- Configurable refresh intervals
- Comprehensive drive statistics display:
  - Controller information (model, vendor)
  - Temperature monitoring
  - Drive counts: present, missing, optimal, failed, degraded, offline, rebuild, consistency, predictive, background operations
- Seamless integration with Unraid dashboard
- Collapsible panel with settings access
- Error handling for missing storcli utility

## Installation
1. Copy the `hbastat.plg` file to your Unraid flash drive
2. In Unraid GUI, go to Settings → Plugin Settings → Install Plugin
3. Enter the URL: `file:///boot/config/plugins/hbastat/hbastat.plg`
4. Click Install

Alternatively, you can manually copy the files to `/boot/config/plugins/hbastat/` and run `upgradepkg --install-new /boot/config/plugins/hbastat/hbastat-2026.06.04-final-x86_64.txz`

## Configuration
After installation, configure the plugin via:
Settings → HBA Statistics

Available settings:
- Storcli Command Path (default: storcli)
- Vendor (auto-detected)
- Controller ID for Dashboard
- Temperature Format (C/F)
- UI Automatic Refresh / Interval (milliseconds)
- Enabled Pollers for various statistics

## Dashboard
Once configured, the HBAStat dashboard will appear in the Unraid dashboard showing:
- HBA controller model and vendor
- Temperature (when available)
- Drive statistics with color-coded indicators
- Clickable panel to expand/collapse view
- Settings link for quick access to configuration

## Requirements
- Unraid 6.7.1 or later
- storcli or storcli64 utility installed and available in PATH
- Compatible HBA controller (LSI/Broadcom, Intel, Adaptec, Microsemi, etc.)

## Troubleshooting
If you see "No HBA controllers found":
1. Verify storcli/storcli64 is installed: `which storcli64`
2. Test the command manually: `storcli64 show`
3. Check the plugin logs for detailed error information
4. Ensure the user running the web server has permission to execute storcli

## Support
For issues and feature requests, please visit:
https://forums.unraid.net/topic/xxxxxx-plugin-hba-statistics/

## License
MIT License - see LICENSE file for details