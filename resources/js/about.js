(function () {
    "use strict";

    moment.tz.add('Europe/Budapest|CET CEST|-10 -20|01010101010101010101010|1BWp0 1qM0 WM0 1qM0 WM0 1qM0 11A0 1o00 11A0 1o00 11A0 1o00 11A0 1qM0 WM0 1qM0 WM0 1qM0 11A0 1o00 11A0 1o00|11e6');
    moment.tz.setDefault('Europe/Budapest');

    let $age = $('#age'),
        $localtime = $('#localtime'),
        $tstart = $localtime.children('.start'),
        $tick = $localtime.children('.tick'),
        $tend = $localtime.children('.end'),
        born = moment($age.attr('datetime')),
        LANGEN = Laravel.locale === 'en',
        check = function () {
            $age.text(moment().diff(born, 'years'));
            const parts = moment().format(LANGEN ? 'h:mm A' : 'H:mm').split(':');
            $tstart.text(parts[0]);
            $tend.text(parts[1]);
            $tick.toggleClass('invisible');
        };

    setInterval(check, 1000);
    check();
})();
