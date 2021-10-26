import { roundTo } from './utils';

(function () {
  const minWage = 167400;
  const $form = $('#net-salary-calc');
  const $grossInput = $('#gross-salary');
  const $minWageBtn = $('#min-wage-btn');
  const $outputWrap = $('#net-output-wrap');
  const $outputTable = $('#net-output');
  const $outputTbody = $outputTable.children('tbody');
  const $totalDeductPerc = $('#total-deductions-perc');
  const $totalDeduct = $('#total-deductions');
  const $netSalaryPerc = $('#net-salary-perc');
  const $netSalary = $('#net-salary');
  const deductions = {
    income_tax: 15,
    pension_contrib: 10,
    health_ins_contrib: 7,
    unemployed_contrib: 1.5,
  };
  const money = (amount: number) => `${amount.toLocaleString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ')} Ft`;

  $form.on('submit', e => {
    e.preventDefault();

    const grossSalary = parseInt($grossInput.val() as string, 10);
    if (Number.isNaN(grossSalary) || !Number.isFinite(grossSalary)) {
      $grossInput.addClass('is-invalid');
      return;
    }
    $grossInput.removeClass('is-invalid');

    let
      totalDeducted = 0;
    let netSalary = grossSalary;
    $outputTbody.empty();
    $.each(deductions, (key: string, perc_amount: number) => {
      const amount = roundTo((perc_amount / 100) * grossSalary, 2);
      totalDeducted += perc_amount;
      netSalary -= amount;
      $outputTbody.append(
        $(document.createElement('tr')).append(
          $(document.createElement('td')).text((window.Laravel.jsLocales.deductions as unknown as Record<string, string>)[key]),
          $(document.createElement('td')).text(`${perc_amount.toLocaleString()}%`),
          $(document.createElement('td')).text(money(amount)),
        ),
      );
    });

    $totalDeductPerc.text(`${totalDeducted.toLocaleString()}%`);
    $totalDeduct.text(money((totalDeducted / 100) * grossSalary));

    $netSalaryPerc.text(`${(roundTo((netSalary / grossSalary) * 100, 2)).toLocaleString()}%`);
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
