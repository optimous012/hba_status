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

// ROC junction-temperature thresholds (°C) for SAS3008-class HBAs.
var HBA_TEMP_WARN = 60;
var HBA_TEMP_CRIT = 70;

function hbastat_tempClass(t) {
    var n = parseInt(t, 10);
    if (isNaN(n)) return '';
    if (n >= HBA_TEMP_CRIT) return 'red-text';
    if (n >= HBA_TEMP_WARN) return 'orange-text';
    return 'green-text';
}

function hbastat_paintTemp($el, value) {
    $el.removeClass('green-text orange-text red-text');
    if (value === undefined || value === null || value === '' || value === 'N/A') {
        $el.text('—');
        return;
    }
    $el.addClass(hbastat_tempClass(value));
    $el.text(value + ' °C');
}

function hbastat_paintStatus(id, ctl) {
    var failed = parseInt(ctl.failed, 10) || 0;
    var offline = parseInt(ctl.offline, 10) || 0;
    var predictive = parseInt(ctl.predictive, 10) || 0;
    var rebuild = parseInt(ctl.rebuild, 10) || 0;

    var orb, text;
    if (failed > 0 || offline > 0) {
        orb = 'red-orb'; text = 'fault';
    } else if (predictive > 0 || rebuild > 0) {
        orb = 'orange-orb'; text = 'warning';
    } else {
        orb = 'green-orb'; text = 'active';
    }
    var $orb = $('.hba-status-orb-' + id);
    $orb.removeClass('green-orb orange-orb red-orb').addClass(orb);
    $('.hba-status-text-' + id).text(text);
}

function hbastat_paintSmart(id, ctl) {
    var predictive = parseInt(ctl.predictive, 10) || 0;
    var $icon = $('.hba-smart-icon-' + id);
    var $text = $('.hba-smart-text-' + id);
    $icon.removeClass('fa-thumbs-o-up fa-thumbs-o-down green-text red-text');
    if (predictive > 0) {
        $icon.addClass('fa-thumbs-o-down red-text');
        $text.text(predictive + ' SMART alert' + (predictive === 1 ? '' : 's'));
    } else {
        $icon.addClass('fa-thumbs-o-up green-text');
        $text.text('healthy');
    }
}

function hbastat_paintDrives(id, ctl) {
    var present = parseInt(ctl.present, 10);
    var optimal = parseInt(ctl.optimal, 10);
    var $el = $('.hba-drives-' + id);
    if (isNaN(present)) {
        $el.text('—');
        return;
    }
    if (!isNaN(optimal)) {
        $el.text(optimal + '/' + present + ' optimal');
    } else {
        $el.text(present);
    }
}

function hbastat_status() {
    $.getJSON('/plugins/hbastat/hbastatus.php', function(data) {
        var controllers = [];
        if (data && Array.isArray(data.controllers)) {
            controllers = data.controllers;
        } else if (data && data.controller !== undefined) {
            controllers = [data];
        }

        controllers.forEach(function(ctl) {
            var id = ctl.controller;
            if (id === undefined || id === null) return;

            ['vendor', 'product', 'serialno', 'firmware'].forEach(function(f) {
                if (ctl[f] !== undefined && ctl[f] !== null) {
                    $('.hba-' + f + '-' + id).text(ctl[f]);
                }
            });

            ['present', 'missing', 'optimal', 'failed', 'degraded',
             'offline', 'rebuild', 'consistency', 'predictive', 'background'
            ].forEach(function(f) {
                if (ctl[f] !== undefined && ctl[f] !== null) {
                    $('.hba-' + f + '-' + id).text(ctl[f]);
                }
            });

            hbastat_paintTemp($('.hba-temperature-' + id), ctl.temperature);
            hbastat_paintStatus(id, ctl);
            hbastat_paintSmart(id, ctl);
            hbastat_paintDrives(id, ctl);

            // Reveal the "— Firmware X" suffix in the tile header once we have data.
            if (ctl.firmware && ctl.firmware !== 'N/A') {
                $('.hba-header-sep-' + id + ', .hba-firmware-label-' + id).show();
            }
        });
    }).fail(function() {
        $('[class*="hba-"]').filter(function() {
            return $(this).is('span') && !$(this).hasClass('tile-header');
        }).text('N/A');
    });
}

function hbastat_dash() {
    var box = $('.dash_hbastat');
    if (box.length) {
        if (box.is(':visible')) {
            box.hide();
        } else {
            box.show();
        }
    }
}
