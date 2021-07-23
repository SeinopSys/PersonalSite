(function () {
    "use strict";

    const
        minWage = 161000,
        $form = $('#net-salary-calc'),
        $grossInput = $('#gross-salary'),
        $minWageBtn = $('#min-wage-btn'),
        $outputWrap = $('#net-output-wrap'),
        $outputTable = $('#net-output'),
        $outputTbody = $outputTable.children('tbody'),
        $totalDeductPerc = $('#total-deductions-perc'),
        $totalDeduct = $('#total-deductions'),
        $netSalaryPerc = $('#net-salary-perc'),
        $netSalary = $('#net-salary'),
        deductions = {
            income_tax: 15,
            pension_contrib: 10,
            health_ins_contrib: 7,
            unemployed_contrib: 1.5,
        },
        money = amount => amount.toLocaleString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Ft';

    $form.on('submit', e => {
        e.preventDefault();

        const grossSalary = parseInt($grossInput.val(), 10);
        if (isNaN(grossSalary) || !isFinite(grossSalary)) {
            $grossInput.addClass('is-invalid');
            return;
        } else $grossInput.removeClass('is-invalid');

        let
            totalDeducted = 0,
            netSalary = grossSalary;
        $outputTbody.empty();
        $.each(deductions, (key, perc_amount) => {
            const amount = $.roundTo((perc_amount / 100) * grossSalary, 2);
            totalDeducted += perc_amount;
            netSalary -= amount;
            $outputTbody.append(
                $.mk('tr').append(
                    $.mk('td').text(Laravel.jsLocales.deductions[key]),
                    $.mk('td').text(perc_amount.toLocaleString() + '%'),
                    $.mk('td').text(money(amount))
                )
            );
        });

        $totalDeductPerc.text(totalDeducted.toLocaleString() + '%');
        $totalDeduct.text(money((totalDeducted / 100) * grossSalary));

        $netSalaryPerc.text(($.roundTo((netSalary / grossSalary) * 100, 2)).toLocaleString() + '%');
        $netSalary.text(money(netSalary));

        $outputWrap.removeClass('d-none');
    }).on('reset', e => {
        e.preventDefault();

        $grossInput.val('');
        $outputWrap.addClass('d-none');
    });

    $minWageBtn.on('click', e => {
        e.preventDefault();

        $grossInput.val(minWage);
        $form.triggerHandler('submit');
    });
})();
