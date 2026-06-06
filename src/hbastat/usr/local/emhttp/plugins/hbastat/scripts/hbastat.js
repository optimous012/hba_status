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

function hbastat_status() {
    $.getJSON('/plugins/hbastat/hbastatus.php', function(data) {
        var controllers = [];
        if (data && Array.isArray(data.controllers)) {
            controllers = data.controllers;
        } else if (data && data.controller !== undefined) {
            controllers = [data];
        }

        var fields = [
            'vendor', 'product', 'serialno', 'firmware', 'temperature',
            'present', 'missing', 'optimal', 'failed', 'degraded',
            'offline', 'rebuild', 'consistency', 'predictive', 'background'
        ];

        controllers.forEach(function(ctl) {
            var id = ctl.controller;
            if (id === undefined || id === null) return;

            fields.forEach(function(f) {
                var $el = $('.hba-' + f + '-' + id);
                if (!$el.length) return;
                var v = ctl[f];
                if (v === undefined || v === null) return;
                if (f === 'temperature' && v !== 'N/A' && v !== '') {
                    v = v + '°C';
                }
                $el.text(v);
            });
        });
    }).fail(function() {
        $('[class*="hba-"].load').text('N/A');
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
