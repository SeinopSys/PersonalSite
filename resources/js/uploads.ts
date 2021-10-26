import { ConfirmHandleFunction, Dialog } from './dialog';
import { copy, mkAjaxHandler, translatePlaceholders } from './utils';

const $uploadList = $('#upload-list');
let $noimgAlert = $('#noimg-alert');
let $uploadedTotal = $('#uploaded-total');

function wipeUpload($link: JQuery, requireConfirm: boolean) {
  const $image = $link.closest('.image');
  const id = ($image.attr('id') || '').replace(/^upload-/, '');
  const $content = $(document.createElement('div')).attr('id', 'img-wipe-confirm').append(
    $(document.createElement('p')).append($link.attr('data-dialogcontent') || ''),
    $(document.createElement('div')).attr('class', 'del-img-wrap').append(
      $(document.createElement('img')).attr('src', $link.siblings().first().attr('href') || ''),
    ),
  );
  const confirm: ConfirmHandleFunction = sure => {
    if (!sure) return;

    if (requireConfirm) Dialog.wait(false);

    const pageMatch = window.location.search.match(/page=(\d+)/);
    const orderby = window.location.search.match(/orderby=([a-z]+)[ +](asc|desc)/i);
    const params = [];
    if (pageMatch) {
      let page = parseInt(pageMatch[1], 10);
      if ($image.parent().siblings().length === 0) page = Math.max(page - 1, 1);
      params.push(`page=${page}`);
    }
    if (orderby) params.push(`orderby=${orderby.slice(1).join('+')}`);
    $.post(`/uploads/wipe${params.length ? `?${params.join('&')}` : ''}`, { id }, mkAjaxHandler(data => {
      if (!data.status) {
        Dialog.fail(false, data.message);
        return;
      }

      const $newhtml = $(data.newhtml);
      const newTotal = data.total;
      const { usedSpace } = data;

      Dialog.close(() => {
        $image.closest('.image-wrap').fadeTo(500, 0, function () {
          $(this).remove();
          $uploadList.empty().append($newhtml.filter('#upload-list').children());
          $('.pagination-wrapper').replaceWith($newhtml.filter('.pagination-wrapper'));
          $noimgAlert = $('#noimg-alert');
          $uploadedTotal = $('#uploaded-total');
          $uploadedTotal.text(newTotal);
          $('#used-space').text(usedSpace);
          if ($uploadList.children().length === 0) {
            $noimgAlert.removeClass('hidden');
            $uploadList.remove();
          }
        });
      });
    }));
  };
  if (requireConfirm) Dialog.confirm({
    title: $link.attr('data-dialogtitle') || '',
    content: $content,
    handlerFunc: confirm,
  });
  else confirm(true);
}

function selectionChange($el: JQuery, checked: boolean) {
  const $imageWrap = $el.closest('.image').parent();

  $imageWrap[checked ? 'addClass' : 'removeClass']('selected');
  const $allWraps = $imageWrap.siblings().addBack();
  $allWraps.removeClass('not-selected');
  const $unsel = $allWraps.filter(':not(.selected)');
  if ($unsel.length < $allWraps.length) $unsel.addClass('not-selected');
}

$(document).on('keydown', e => {
  if (!/^a$/i.test(e.key) || !e.ctrlKey || e.shiftKey || e.altKey) {
    return;
  }

  e.preventDefault();
  $('.image-wrap').addClass('selected').removeClass('not-selected').find('.selection input')
    .prop('checked', true);
});

const $uploadToggle = $('#uploads-toggle');
$uploadToggle.on('click', e => {
  e.preventDefault();

  const action = $uploadToggle.hasClass('btn-success') ? 'enable' : 'disable';
  const title = $uploadToggle.attr('data-dialogtitle') || '';
  const content = $uploadToggle.attr('data-dialogcontent');

  Dialog.confirm({
    title,
    content,
    handlerFunc: sure => {
      if (!sure) return;

      Dialog.wait(false);

      $.post(`/uploads/setting/${action}`, mkAjaxHandler(data => {
        if (!data.status) {
          Dialog.fail(false, data.message);
          return;
        }

        Dialog.success(false, data.message);
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      }));
    },
  });
});

const $revealUploadKey = $('#reveal-upload-key');
const $uploadKeyDisplay = $<HTMLInputElement>('#upload-key-display');
const $copyUploadKey = $('#copy-upload-key');
const $regenUploadKey = $('#regen-upload-key');
let key = $uploadKeyDisplay.attr('data-key') || '';
const hiddenRegex = /^\*+$/;
$uploadKeyDisplay.removeAttr('data-key');
$revealUploadKey.on('click', e => {
  e.preventDefault();

  const hidden = hiddenRegex.test($uploadKeyDisplay.val() as string);

  $uploadKeyDisplay.val(hidden ? key : key.replace(/./g, '*'));
  $revealUploadKey.children('.fa').toggleClass('fa-eye fa-eye-slash');
});
$regenUploadKey.on('click', function (e) {
  e.preventDefault();

  const $btn = $(this);
  Dialog.confirm({
    title: $btn.attr('title') || '',
    content: $btn.attr('data-dialogcontent'),
    handlerFunc: sure => {
      if (!sure) return;

      Dialog.wait(false);

      $.post('/uploads/regen', mkAjaxHandler(data => {
        if (!data.status) {
          Dialog.fail(false, data.message);
          return;
        }

        key = data.upload_key;
        Dialog.close(() => {
          $uploadKeyDisplay.fadeTo(200, 0, () => {
            const hidden = hiddenRegex.test($uploadKeyDisplay.val() as string);
            $uploadKeyDisplay.val(hidden ? key.replace(/./g, '*') : key);
            if (hidden) $revealUploadKey.trigger('click');
            $uploadKeyDisplay.fadeTo(200, 1);
          });
        });
      }));
    },
  });
});

$uploadKeyDisplay.on('click', e => {
  e.preventDefault();

  $uploadKeyDisplay[0].select();
});

$copyUploadKey.on('click', e => {
  e.preventDefault();

  copy(key, e);
});

$uploadList.find('input:checked').prop('checked', false);
$uploadList.on('click', '.copy-upload-link', function (e) {
  e.preventDefault();

  copy($(this).siblings().first().prop('href'), e);
});
$uploadList.on('click', '.wipe-upload', function (e) {
  e.preventDefault();

  const $selection = $uploadList.children('.selected');
  if ($selection.length) {
    Dialog.confirm({
      title: window.Laravel.jsLocales.multiwipe_dialog_title,
      content: translatePlaceholders(window.Laravel.jsLocales.multiwipe_dialog_text, { cnt: $selection.length }),
      handlerFunc: sure => {
        if (!sure) return;

        Dialog.wait(false);

        $selection.each(function () {
          wipeUpload($(this).find('.wipe-upload'), false);
        });
      },
    });
    return;
  }

  wipeUpload($(this), true);
}).on('click', '.image', function (e) {
  if (e.ctrlKey && !e.altKey) {
    e.preventDefault();

    const
      $input = $(this).find('.selection input');
    const newChecked = !$input.prop('checked');
    $input.prop('checked', newChecked);
    selectionChange($input, newChecked);
  }
}).on('click', '.image .selection input', function (e) {
  const $this = $(this);
  if (e.altKey && !e.ctrlKey) $uploadList.find('.not-selected, .selected')
    .removeClass('not-selected selected')
    .find('.selection input')
    .prop('checked', false);
  else selectionChange($this, $this.prop('checked'));
});
