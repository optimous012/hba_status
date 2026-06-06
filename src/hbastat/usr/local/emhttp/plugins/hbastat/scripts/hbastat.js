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
        // Handle multiple controllers format
        if (data.controllers !== undefined && Array.isArray(data.controllers)) {
            // Clear previous controller displays
            $('.hba-controller-display').remove();

            // Add display for each controller
            data.controllers.forEach(function(controller, index) {
                // Create controller container if it doesn't exist
                if ($('.hba-controller-' + index).length === 0) {
                    // Insert after the vendor/product row
                    var controllerRow = '<tr class="dash_hbastat_toggle hba-enviro hba-controller-' + index + ' hba-controller-display">' +
                                        '<td></td>' +
                                        '<td>Controller ' + index + ' - <span class=\'hba-vendor-' + index + '\'></span>&nbsp;<span class=\'hba-product-' + index + '\'></span></td>' +
                                        '<td></td>' +
                                        '<td></td>' +
                                        '</tr>';

                    var tempRow = '<tr class="dash_hbastat_toggle hba-enviro hba-controller-' + index + ' hba-controller-display">' +
                                  '<td></td>' +
                                  <td>Temperature</td>' +
                                  <td><span class='hba-temp-' + index + ''></span></td>' +
                                  <td></td>' +
                                  '</tr>';

                    $('.hba-enviro:first').before(controllerRow + tempRow);
                }

                // Update controller-specific fields
                if (controller.vendor !== undefined) {
                    $('.hba-vendor-' + index).text(controller.vendor);
                }
                if (controller.product !== undefined) {
                    $('.hba-product-' + index).text(controller.product);
                }
                if (controller.temperature !== undefined) {
                    $('.hba-temp-' + index).text(controller.temperature + '°C');
                }
            });
        } else {
            // Single controller format (backward compatibility)
            // Update status
            if (data.status !== undefined) {
                $('.hba-status').text(data.status);
            }
            // Update temperature
            if (data.temperature !== undefined) {
                $('.hba-temp').text(data.temperature + '°C');
            }
            // Update vendor and product
            if (data.vendor !== undefined) {
                $('.hba-vendor').text(data.vendor);
            }
            if (data.product !== undefined) {
                $('.hba-product').text(data.product);
            }
        }

        // Update drive counts (from first controller or single controller)
        var controllerData = data.controllers ? (data.controllers[0] || {}) : data;

        if (controllerData.present !== undefined) {
            $('.hba-present').text(controllerData.present);
        }
        if (controllerData.missing !== undefined) {
            $('.hba-missing').text(controllerData.missing);
        }
        if (controllerData.optimal !== undefined) {
            $('.hba-optimal').text(controllerData.optimal);
        }
        if (controllerData.failed !== undefined) {
            $('.hba-failed').text(controllerData.failed);
        }
        if (controllerData.degraded !== undefined) {
            $('.hba-degraded').text(controllerData.degraded);
        }
        if (controllerData.offline !== undefined) {
            $('.hba-offline').text(controllerData.offline);
        }
        if (controllerData.rebuild !== undefined) {
            $('.hba-rebuild').text(controllerData.rebuild);
        }
        if (controllerData.consistency !== undefined) {
            $('.hba-consistency').text(controllerData.consistency);
        }
        if (controllerData.predictive !== undefined) {
            $('.hba-predictive').text(controllerData.predictive);
        }
        if (controllerData.background !== undefined) {
            $('.hba-background').text(controllerData.background);
        }
    }).fail(function() {
        // Handle error
        $('.hba-status').text('Error');
        $('.hba-temp').text('N/A');
        $('.hba-vendor').text('N/A');
        $('.hba-product').text('N/A');
        $('.hba-present').text('N/A');
        $('.hba-missing').text('N/A');
        $('.hba-optimal').text('N/A');
        $('.hba-failed').text('N/A');
        $('.hba-degraded').text('N/A');
        $('.hba-offline').text('N/A');
        $('.hba-rebuild').text('N/A');
        $('.hba-consistency').text('N/A');
        $('.hba-predictive').text('N/A');
        $('.hba-background').text('N/A');
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