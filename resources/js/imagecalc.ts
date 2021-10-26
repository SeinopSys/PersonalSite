(function ($) {
  const $tabs = $('#main-tabs');
  const hashchange = function (hash?: string) {
    if (hash && /^#[a-z-]+$/.test(hash)) {
      $('.tab-panel').addClass('d-none');
      const $linkedTab = $(hash);
      const $tabPills = $tabs.find('a')
        .removeClass('active');
      if ($linkedTab.length) {
        $linkedTab.removeClass('d-none');
        $tabPills.filter(function () {
          return this.hash === hash;
        }).addClass('active');
      } else $('.tab-panel.not-found')
        .removeClass('d-none')
        .show();
    } else {
      const $a = $tabs.find('a')
        .first()
        .addClass('active');
      const linkHash = $a.attr('href');
      if (linkHash) {
        $(linkHash).removeClass('d-none');
      }
    }
  };
  $(window).on('hashchange', () => {
    hashchange(window.location.hash);
  }).triggerHandler('hashchange');

  $('.tab-panel form')
    .on('clear', e => {
      $(e.target)
        .find('.form-control[required], .form-control[pattern]')
        .each((_, el) => {
          $(el)
            .trigger('change');
        });
    });

  $('.form-control[required], .form-control[pattern]')
    .on('keydown keyup change', e => {
      const $target = $(e.target);
      let isValid;
      if (e.target.tagName === 'INPUT') isValid = $target.is(':valid');
      else {
        const targetValue = $target.val() as string;
        if (typeof $target.attr('required') !== 'undefined') isValid = targetValue.length > 0;
        else isValid = true;

        if (isValid) {
          const pattern = $target.attr('pattern');
          if (typeof pattern !== 'undefined') {
            isValid = new RegExp(pattern).test(targetValue);
          }
        }
      }
      $target[isValid ? 'removeClass' : 'addClass']('is-invalid');
    })
    .trigger('change');

  interface ScaledSize {
    width: number;
    height: number;
    scale: number;
  }

  function scaleResize(
    inputWidth: number,
    inputHeight: number,
    preferred: Pick<ScaledSize, 'width'> | Pick<ScaledSize, 'height'> | Pick<ScaledSize, 'scale'>,
  ): ScaledSize {
    if ('scale' in preferred) {
      const { scale } = preferred;
      return {
        scale,
        height: Math.round(inputHeight * scale),
        width: Math.round(inputWidth * scale),
      };
    }
    if ('width' in preferred) {
      const { width } = preferred as Pick<ScaledSize, 'width'>;
      const div = width / inputWidth;
      return {
        width,
        scale: div,
        height: Math.round(inputHeight * div),
      };
    }
    if ('height' in preferred) {
      const { height } = preferred;
      const div = height / inputHeight;
      return {
        height,
        scale: div,
        width: Math.round(inputWidth * div),
      };
    }

    throw new Error('[scalaresize] Invalid arguments');
  }

  const greatestCommonDivisor = (a: number, b: number): number => (b === 0 ? a : greatestCommonDivisor(b, a % b));

  function reduceRatio(width: number, height: number): number[] {
    if (width === height) {
      return [1, 1];
    }

    let localWidth = width;
    let localHeight = height;
    if (localWidth < localHeight) {
      [localWidth, localHeight] = [height, localWidth];
    }

    const divisor = greatestCommonDivisor(localWidth, localHeight);

    return [localWidth / divisor, localHeight / divisor];
  }

  (function (ns) {
    const $width = $(`#${ns}-width`);
    const $height = $(`#${ns}-height`);
    const $targets = $(`input[name="${ns}-target"]`);
    const $value = $(`#${ns}-value`);
    const $output = $(`#${ns}-output`);
    const $form = $(`#${ns}-form`);

    $form.on('submit', e => {
      e.preventDefault();

      const origWidth = parseInt($width.val() as string, 10);
      const origHeight = parseInt($height.val() as string, 10);
      const target = $targets.filter(':checked').attr('value') as 'scale';
      const value = parseFloat($value.val() as string);

      const result = scaleResize(origWidth, origHeight, { [target]: value });

      const condensedScale = String(result.scale)
        .replace(/(\.\d*?)(\d)(\2)(\2+)$/, '$1$2$3');

      // OUTPUT PHASE
      $output.removeClass('d-none');
      $output.text(`${result.width} × ${result.height} @ ${condensedScale}`);
    })
      .on('reset', () => {
        $output.addClass('d-none');
        $form.find('.form-control')
          .val('')
          .trigger('change');
      });

    const cycleNames = ['width', 'height', 'scale'] as const;
    const cycleValues = {
      width: '1280',
      height: '480',
      scale: '2',
    } as const;

    $(`#${ns}-predefined-data`)
      .on('click', e => {
        e.preventDefault();

        $width.val('1920')
          .trigger('change');
        $height.val('1080')
          .trigger('change');
        const $currentTarget = $targets.filter(':checked');
        let $nextTarget;
        if ($currentTarget.length) $nextTarget = $currentTarget;
        else {
          const nextTargetName = cycleNames[Math.floor(Math.random() * cycleNames.length)];
          $nextTarget = $targets.filter(`[value="${nextTargetName}"]`);
          $nextTarget.prop('checked', true);
        }
        $value.val(cycleValues[$nextTarget.attr('value') as keyof typeof cycleValues])
          .trigger('change');
        $form.triggerHandler('submit');
      });
  })('scale');

  (function (ns) {
    const $width = $(`#${ns}-width`);
    const $height = $(`#${ns}-height`);
    const $output = $(`#${ns}-output`);
    const $form = $(`#${ns}-form`);

    $form.on('submit', e => {
      e.preventDefault();

      const
        origWidth = parseInt($width.val() as string, 10);
      const origHeight = parseInt($height.val() as string, 10);

      const result = reduceRatio(origWidth, origHeight);

      // OUTPUT PHASE
      $output.removeClass('d-none');
      let output = result.join(':');
      if (output === '64:27') {
        output += ' <small class="fw-normal">(“21:9”)</small>';
      }
      $output.empty()
        .append($('<p class="display-4 mb-0 fw-bold" />')
          .html(output));
      if (result[0] !== result[1] && result[1] !== 1) {
        $output.append(
          $('<p class="font-family-monospace mb-0" />')
            .text(`${result[0]} ÷ ${result[1]} = ${result[0] / result[1]}`),
        );
      }
    })
      .on('reset', () => {
        $output.addClass('d-none');
        $form.find('.form-control')
          .val('')
          .trigger('change');
      });

    const demoData = [
      [1920, 1080],
      [1600, 900],
      [1024, 768],
      [100, 100],
      [2560, 1080],
    ];

    $(`#${ns}-predefined-data`)
      .on('click', e => {
        e.preventDefault();

        const data = demoData[Math.floor(Math.random() * demoData.length)];
        $width.val(data[0])
          .trigger('change');
        $height.val(data[1])
          .trigger('change');
        $form.triggerHandler('submit');
      });
  })('aspectratio');
})(jQuery);
