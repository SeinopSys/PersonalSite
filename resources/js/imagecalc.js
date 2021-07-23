(function ($) {
    'use strict';

    let $tabs = $('#main-tabs'),
        hashchange = function (hash) {
            if (hash && /^#[a-z-]+$/.test(hash)) {

                $('.tab-panel').addClass('d-none');
                const $linkedTab = $(hash);
                const $tabPills = $tabs.find('a').removeClass('active');
                if ($linkedTab.length) {
                    $linkedTab.removeClass('d-none');
                    $tabPills.filter(function () {
                        return this.hash === hash;
                    }).addClass('active');
                } else $('.tab-panel.not-found').removeClass('d-none').show();
            } else {
                let $a = $tabs.find('a').first().addClass('active');
                $($a.attr('href')).removeClass('d-none');
            }
        };
    $w.on('hashchange', function () {
        hashchange(window.location.hash);
    }).triggerHandler('hashchange');

    $('.tab-panel form').on('clear', e => {
        $(e.target).find('.form-control[required], .form-control[pattern]').each((_, el) => {
            $(el).trigger('change');
        });
    });

    $('.form-control[required], .form-control[pattern]').on('keydown keyup change', e => {
        const $target = $(e.target);
        let isValid;
        if (e.target.tagName === 'INPUT')
            isValid = $target.is(':valid');
        else {
            if (typeof $target.attr('required') !== 'undefined')
                isValid = $target.val().length > 0;
            else isValid = true;

            if (isValid) {
                const pattern = $target.attr('pattern');
                if (typeof pattern !== 'undefined') {
                    isValid = new RegExp(pattern).test($target.val());
                }
            }
        }
        $target[isValid ? 'removeClass' : 'addClass']('is-invalid');
    }).trigger('change');

    function scaleResize(w, h, p) {
        let div, d = {
            scale: p.scale,
            width: p.width,
            height: p.height
        };
        if (!isNaN(d.scale)) {
            d.height = Math.round(h * d.scale);
            d.width = Math.round(w * d.scale);
        } else if (isNaN(d.width)) {
            div = d.height / h;
            d.width = Math.round(w * div);
            d.scale = div;
        } else if (isNaN(d.height)) {
            div = d.width / w;
            d.height = Math.round(h * div);
            d.scale = div;
        } else throw new Error('[scalaresize] Invalid arguments');
        return d;
    }

    const greatestCommonDivisor = (a, b) => b === 0 ? a : greatestCommonDivisor(b, a % b);

    /**
     * @param {number} width
     * @param {number} height
     *
     * @return {number[]}
     */
    function reduceRatio(width, height) {
        if (width === height) {
            return [1, 1];
        }

        if (width < height) {
            [width, height] = [height, width];
        }

        const divisor = greatestCommonDivisor(width, height);

        return [width / divisor, height / divisor];
    }

    (function (ns) {
        const $width = $(`#${ns}-width`),
            $height = $(`#${ns}-height`),
            $targets = $(`input[name="${ns}-target"]`),
            $value = $(`#${ns}-value`),
            $output = $(`#${ns}-output`),
            $form = $(`#${ns}-form`);

        $form.on('submit', function (e) {
            e.preventDefault();

            const
                origWidth = parseInt($width.val(), 10),
                origHeight = parseInt($height.val(), 10),
                target = $targets.filter(':checked').attr('value'),
                value = parseFloat($value.val());

            const result = scaleResize(origWidth, origHeight, {[target]: value});

            const condensedScale = String(result.scale).replace(/(\.\d*?)(\d)(\2)(\2+)$/, '$1$2$3');

            // OUTPUT PHASE
            $output.removeClass('d-none');
            $output.text(`${result.width} × ${result.height} @ ${condensedScale}`);
        }).on('reset', function () {
            $output.addClass('d-none');
            $form.find('.form-control').val('').trigger('change');
        });

        const cycleNames = ['width', 'height', 'scale'];
        const cycleValues = {
            'width': '1280',
            'height': '480',
            'scale': '2',
        };

        $(`#${ns}-predefined-data`).on('click', function (e) {
            e.preventDefault();

            $width.val('1920').trigger('change');
            $height.val('1080').trigger('change');
            const $currentTarget = $targets.filter(':checked');
            let $nextTarget;
            if ($currentTarget.length)
                $nextTarget = $currentTarget;
            else {
                const nextTargetName = cycleNames[Math.floor(Math.random() * cycleNames.length)];
                $nextTarget = $targets.filter(`[value="${nextTargetName}"]`);
                $nextTarget.prop('checked', true);
            }
            $value.val(cycleValues[$nextTarget.attr('value')]).trigger('change');
            $form.triggerHandler('submit');
        });
    })('scale');

    (function (ns) {
        const $width = $(`#${ns}-width`),
            $height = $(`#${ns}-height`),
            $output = $(`#${ns}-output`),
            $form = $(`#${ns}-form`);

        $form.on('submit', function (e) {
            e.preventDefault();

            const
                origWidth = parseInt($width.val(), 10),
                origHeight = parseInt($height.val(), 10);

            const result = reduceRatio(origWidth, origHeight);

            // OUTPUT PHASE
            $output.removeClass('d-none');
            let output = result.join(':');
            if (output === '64:27') {
                output += ` <small class="fw-normal">(“21:9”)</small>`;
            }
            $output.empty().append($('<p class="display-4 mb-0 fw-bold" />').html(output));
            if (result[0] !== result[1] && result[1] !== 1) {
                $output.append(
                    $('<p class="font-family-monospace mb-0" />').text(`${result[0]} ÷ ${result[1]} = ${result[0] / result[1]}`)
                );
            }
        }).on('reset', function () {
            $output.addClass('d-none');
            $form.find('.form-control').val('').trigger('change');
        });

        const demoData = [
            [1920, 1080],
            [1600, 900],
            [1024, 768],
            [100, 100],
            [2560, 1080],
        ];

        $(`#${ns}-predefined-data`).on('click', function (e) {
            e.preventDefault();

            const data = demoData[Math.floor(Math.random() * demoData.length)];
            $width.val(data[0]).trigger('change');
            $height.val(data[1]).trigger('change');
            $form.triggerHandler('submit');
        });
    })('aspectratio');
})(jQuery);
