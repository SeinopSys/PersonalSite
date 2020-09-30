(function ($) {
    'use strict';

    if (Laravel.locale !== 'en')
        moment.locale(Laravel.locale);

    // document.createElement shortcut
    window.mk = function () {
        return document.createElement.apply(document, arguments);
    };

    // $(document.createElement) shortcut
    $.mk = (name, id) => {
        let $el = $(document.createElement.call(document, name));
        if (typeof id === 'string')
            $el.attr('id', id);
        return $el;
    };

    // Globalize common elements
    window.$w = $(window);
    window.$d = $(document);
    window.$header = $('header');
    window.$main = $('#main');
    window.$footer = $('footer');
    window.$body = $('body');
    window.$head = $('head');

    // Common key codes for easy reference
    window.Key = {
        Enter: 13,
        Esc: 27,
        Space: 32,
        PageUp: 33,
        PageDown: 34,
        LeftArrow: 37,
        UpArrow: 38,
        RightArrow: 39,
        DownArrow: 40,
        Delete: 46,
        Backspace: 8,
        Tab: 9,
        Comma: 188,
        Period: 190,
    };
    $.isKey = function (Key, e) {
        return e.keyCode === Key;
    };

    window.hcaptchaReady = () => {
        const {hcaptchaKey} = Laravel;

        if (typeof hcaptchaKey === 'undefined')
            return;

        if (hcaptchaKey === '') {
            console.error("You haven't set your Site Key for hCaptcha v3. Get it on https://hcaptcha.com.");
            return;
        }

        const relevantInputName = 'h-captcha-response';

        const getKeyEl = $form => {
            let $el = $form.find(`:input[name="${relevantInputName}"]`);
            if (!$el.length) {
                $el = $($.mk('textarea')).attr('name', relevantInputName).appendTo($form);
            }
            return $el;
        };

        const getSubmitEls = $form => $form.find(`button:not([type]), input[type="submit"]`);

        $('form').filter('[data-hcaptcha="true"]').each((_, form) => {
            const $form = $(form);
            const $container = $form.find('.h-captcha');
            const widgetId = hcaptcha.render($container.get(0), {
                sitekey: hcaptchaKey,
                ...$container.data(),
                callback: token => {
                    const $key = getKeyEl($form);
                    $key.val(token);
                    form.submit();
                    // Reset the key for the next submission
                    $key.val('');
                    getSubmitEls($form).attr('disabled', false);
                }
            });
            $form.on('submit', e => {
                const $key = getKeyEl($form);
                console.log($key.val());
                if ($key.val().length === 0) {
                    e.preventDefault();
                    getSubmitEls($form).attr('disabled', true);
                    hcaptcha.execute(widgetId);
                    return;
                }
                // Reset the key for the next submission
                $key.val('');
            });
        });
    };

    // Time class
    (function ($) {
        let dateformat = {order: 'Do MMMM YYYY, H:mm:ss'};
        dateformat.orderwd = `dddd, ${dateformat.order}`;

        class DateFormatError extends Error {
            constructor(message, element) {
                super(message);

                this.name = 'DateFormatError';
                this.element = element;
            }
        }

        class Time {
            static update() {
                $('time[datetime]:not(.nodt)').addClass('dynt').each(function () {
                    let $this = $(this),
                        date = $this.attr('datetime');
                    if (typeof date !== 'string') throw new TypeError('Invalid date data type: "' + (typeof date) + '"');

                    let Timestamp = moment(date);
                    if (!Timestamp.isValid())
                        throw new DateFormatError('Invalid date format: "' + date + '"', this);

                    let Now = moment(),
                        showDayOfWeek = !$this.attr('data-noweekday'),
                        timeAgoStr = Timestamp.from(Now),
                        $elapsedHolder = $this.parent().children('.dynt-el'),
                        updateHandler = $this.data('dyntime-beforeupdate');

                    if (typeof updateHandler === 'function') {
                        let result = updateHandler(Time.difference(Now.toDate(), Timestamp.toDate()));
                        if (result === false) return;
                    }

                    if ($elapsedHolder.length > 0 || $this.hasClass('no-dynt-el')) {
                        $this.html(Timestamp.format(showDayOfWeek ? dateformat.orderwd : dateformat.order));
                        $elapsedHolder.html(timeAgoStr);
                    } else $this.attr('title', Timestamp.format(dateformat.order)).html(timeAgoStr);
                });
            }

            static difference(now, timestamp) {
                let substract = (now.getTime() - timestamp.getTime()) / 1000,
                    d = {
                        past: substract > 0,
                        time: Math.abs(substract),
                        target: timestamp,
                    },
                    time = d.time;

                d.day = Math.floor(time / this.inSeconds.day);
                time -= d.day * this.inSeconds.day;

                d.hour = Math.floor(time / this.inSeconds.hour);
                time -= d.hour * this.inSeconds.hour;

                d.minute = Math.floor(time / this.inSeconds.minute);
                time -= d.minute * this.inSeconds.minute;

                d.second = Math.floor(time);

                if (d.day >= 7) {
                    d.week = Math.floor(d.day / 7);
                    d.day -= d.week * 7;
                }
                if (d.week >= 4) {
                    d.month = Math.floor(d.week / 4);
                    d.week -= d.month * 4;
                }
                if (d.month >= 12) {
                    d.year = Math.floor(d.month / 12);
                    d.month -= d.year * 12;
                }

                return d;
            }
        }

        Time.inSeconds = {
            'year': 31557600,
            'month': 2592000,
            'week': 604800,
            'day': 86400,
            'hour': 3600,
            'minute': 60,
        };
        window.Time = Time;

        Time.update();
        setInterval(Time.update, 10e3);
    })(jQuery);

    // Make the first letter of the first or all word(s) uppercase
    $.capitalize = (str, all) => {
        if (all) return str.replace(/((?:^|\s)[a-z])/g, match => match.toUpperCase());
        else return str.length === 1 ? str.toUpperCase() : str[0].toUpperCase() + str.substring(1);
    };

    $.strRepeat = (str, times = 1) => new Array(times + 1).join(str);
    $.pad = function (str, char, len, dir) {
        if (typeof str !== 'string')
            str = '' + str;

        if (typeof char !== 'string')
            char = '0';
        if (typeof len !== 'number' && !isFinite(len) && isNaN(len))
            len = 2;
        else len = parseInt(len, 10);
        if (typeof dir !== 'boolean')
            dir = true;

        if (len <= str.length)
            return str;
        const padstr = new Array(len - str.length + 1).join(char);
        str = dir === $.pad.left ? padstr + str : str + padstr;

        return str;
    };
    $.pad.right = !($.pad.left = true);

    $.toArray = (args, n = 0) => [].slice.call(args, n);

    // Create AJAX response handling function
    $w.on('ajaxerror', function () {
        let details = '';
        if (arguments.length > 1) {
            let data = $.toArray(arguments, 1);
            if (data[1] === 'abort')
                return;
            details = ' Details:<pre><code>' + data.slice(1).join('\n').replace(/</g, '&lt;') + '</code></pre>Response body:';
            let xdebug = /^(?:<br \/>\n)?(<pre class='xdebug-var-dump'|<font size='1')/;
            if (xdebug.test(data[0].responseText))
                details += `<div class="reset">${data[0].responseText.replace(xdebug, '$1')}</div>`;
            else if (typeof data[0].responseText === 'string')
                details += `<pre><code>${data[0].responseText.replace(/</g, '&lt;')}</code></pre>`;
        }
        $.Dialog.fail(false, `There was an error while processing your request.${details}`);
    });
    $.mkAjaxHandler = function (f) {
        return function (data) {
            if (typeof data !== 'object') {
                //noinspection SSBasedInspection
                console.log(data);
                $w.triggerHandler('ajaxerror');
                return;
            }

            if (typeof f === 'function') f.call(data);
        };
    };

    // Checks if a variable is a function and if yes, runs it
    // If no, returns default value (undefined or value of def)
    $.callCallback = (func, params, def) => {
        if (typeof params !== 'object' || !$.isArray(params)) {
            def = params;
            params = [];
        }
        if (typeof func !== 'function')
            return def;

        return func.apply(window, params);
    };

    // Convert .serializeArray() result to object
    $.fn.mkData = function (obj) {
        let tempdata = $(this).serializeArray(), data = {};
        $.each(tempdata, function (i, el) {
            data[el.name] = el.value;
        });
        if (typeof obj === 'object')
            $.extend(data, obj);
        return data;
    };

    // Get CSRF token
    $.getCSRFToken = function () {
        return window.Laravel.csrfToken;
    };
    let lasturl,
        statusCodeHandlers = {
            404: function () {
                $.Dialog.fail(false, window.Laravel.ajaxErrors[404].replace(':endpoint', lasturl.replace(/</g, '&lt;').replace(/\//g, '/<wbr>')));
            },
            500: function () {
                $.Dialog.fail(false, window.Laravel.ajaxErrors[500]);
            },
            503: function () {
                $.Dialog.fail(false, window.Laravel.ajaxErrors[503]);
            },
        };
    $.ajaxSetup({
        dataType: 'json',
        error: function (xhr) {
            if (typeof statusCodeHandlers[xhr.status] !== 'function')
                $w.triggerHandler('ajaxerror', $.toArray(arguments));
            $body.removeClass('loading');
        },
        beforeSend: function (_, settings) {
            lasturl = settings.url;
        },
        statusCode: statusCodeHandlers,
        headers: {'X-CSRF-TOKEN': $.getCSRFToken()},
    });

    // Copy any text to clipboard
    // Must be called from within an event handler
    let $notif;
    $.copy = (text, e) => {
        if (!document.queryCommandSupported('copy')) {
            prompt('Copy with Ctrl+C, close with Enter', text);
            return true;
        }

        let $helper = $.mk('textarea'),
            success = false;
        $helper
            .css({
                opacity: 0,
                width: 0,
                height: 0,
                position: 'fixed',
                left: '-10px',
                top: '50%',
                display: 'block',
            })
            .text(text)
            .appendTo('body')
            .focus();
        $helper.get(0).select();

        try {
            success = document.execCommand('copy');
        } catch (err) { /* ignore */
        }

        setTimeout(function () {
            $helper.remove();
            if (typeof $notif === 'undefined' || e) {
                if (typeof $notif === 'undefined')
                    $notif = $.mk('span')
                        .attr({
                            id: 'copy-notify',
                            'class': 'alert alert-'+(success ? 'success' : `danger`),
                        })
                        .html('<span class="fa fa-copy"></span> <span class="fa fa-' + (success ? 'check' : 'times') + '"></span>')
                        .appendTo($body);
                if (e) {
                    let w = $notif.outerWidth(),
                        h = $notif.outerHeight(),
                        top = e.clientY - (h / 2);
                    return $notif.stop().css({
                        top: top,
                        left: (e.clientX - (w / 2)),
                        bottom: 'initial',
                        right: 'initial',
                        opacity: 1,
                    }).animate({top: top - 20, opacity: 0}, 600, function () {
                        $(this).remove();
                        $notif = undefined;
                    });
                }
                $notif.fadeTo('fast', 1);
            } else $notif.stop().css('opacity', 1);
            $notif.delay(success ? 300 : 1000).fadeTo('fast', 0, function () {
                $(this).remove();
                $notif = undefined;
            });
        }, 1);
    };

    $.fn.toggleHtml = function (contentArray) {
        return this.html(contentArray[$.rangeLimit(contentArray.indexOf(this.html()) + 1, true, contentArray.length - 1)]);
    };

    $.fn.enable = function () {
        return this.attr('disabled', false);
    };
    $.fn.disable = function () {
        return this.attr('disabled', true);
    };

    $.roundTo = (number, precision) => {
        let pow = Math.pow(10, precision);
        return Math.round(number * pow) / pow;
    };
    $.rangeLimit = function (input, overflow) {
        let min, max, paramCount = 2;
        switch (arguments.length - paramCount) {
            case 1:
                min = 0;
                max = arguments[paramCount];
                break;
            case 2:
                min = arguments[paramCount];
                max = arguments[paramCount + 1];
                break;
            default:
                throw new Error('Invalid number of parameters for $.rangeLimit');
        }
        if (overflow) {
            if (input > max)
                input = min;
            else if (input < min)
                input = max;
        }
        return Math.min(max, Math.max(min, input));
    };

    $.translatePlaceholders = (string, params) => {
        if (typeof string !== 'string') {
            console.log(params);
            throw new TypeError('String expected as first argument');
        }
        $.each(params, (k, v) => {
            string = string.replace(new RegExp(':' + k, 'g'), v);
        });
        return string;
    };
})(jQuery);

$(function () {
    'use strict';

    $('#logout-link').on('click', function (e) {
        e.preventDefault();

        $.Dialog.confirm($(this).text().trim(), undefined, function (sure) {
            if (!sure) return;

            $.Dialog.wait(false);

            $.post('/logout', () => window.location.href = '/');
        });
    });

    const $sunsetNotice = $('#hu-sunset-notice');
    const sunsetKey = 'hu_sunset_dismissed';
    $sunsetNotice.on('closed.bs.alert', () => {
        localStorage.setItem(sunsetKey, 'true');
    });
    if (localStorage.getItem(sunsetKey) === 'true')
        $sunsetNotice.remove();
    else $sunsetNotice.slideDown();
});
