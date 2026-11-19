import { ConfirmHandleFunction, Dialog } from './dialog';
import { copy, mkAjaxHandler, translatePlaceholders } from './utils';
import { initFolderTree } from './uploads/folder-tree-controller';

const $uploadList = $('#upload-list');
let $noimgAlert = $('#noimg-alert');
let $uploadedTotal = $('#uploaded-total');

interface ListUpdateData {
  status: boolean;
  message?: string;
  newhtml: string;
  total: number;
  usedSpace: string;
}

const initialFolderMatch = window.location.search.match(/[?&]folder=([^&]+)/);
let currentFolderId: string | null = initialFolderMatch ? decodeURIComponent(initialFolderMatch[1]) : null;

export function getCurrentFolder(): string | null {
  return currentFolderId;
}

function applyListUpdate(data: ListUpdateData) {
  const $newhtml = $(data.newhtml);
  $uploadList.empty().append($newhtml.filter('#upload-list').children());
  $('.pagination-wrapper').replaceWith($newhtml.filter('.pagination-wrapper'));
  $noimgAlert = $('#noimg-alert');
  $uploadedTotal = $('#uploaded-total');
  $uploadedTotal.text(data.total);
  $('#used-space').text(data.usedSpace);
  $noimgAlert.toggleClass('d-none', $uploadList.children().length > 0);
}

function currentPageParams(): { page: number; orderby: string | null } {
  const pageMatch = window.location.search.match(/page=(\d+)/);
  const orderbyMatch = window.location.search.match(/orderby=([a-z_]+)[ +](asc|desc)/i);
  return {
    page: pageMatch ? parseInt(pageMatch[1], 10) : 1,
    orderby: orderbyMatch ? `${orderbyMatch[1]}+${orderbyMatch[2]}` : null,
  };
}

function wipeUpload($link: JQuery, requireConfirm: boolean) {
  const $image = $link.closest('.image');
  const id = ($image.attr('id') || '').replace(/^upload-/, '');
  const href = $link.siblings().first().attr('href') || '';
  const $preview = /\.(webm|mp4)(\?|$)/i.test(href)
    ? $(document.createElement('video')).attr({
      src: href, muted: 'muted', controls: 'controls', playsinline: 'playsinline',
    })
    : $(document.createElement('img')).attr('src', href);
  const $content = $(document.createElement('div')).attr('id', 'img-wipe-confirm').append(
    $(document.createElement('p')).append($link.attr('data-dialogcontent') || ''),
    $(document.createElement('div')).attr('class', 'del-img-wrap').append($preview),
  );
  const confirm: ConfirmHandleFunction = sure => {
    if (!sure) return;

    if (requireConfirm) Dialog.wait(false);

    const currentParams = currentPageParams();
    let { page } = currentParams;
    const { orderby } = currentParams;
    if ($image.parent().siblings().length === 0) page = Math.max(page - 1, 1);
    const params: string[] = [`page=${page}`];
    if (orderby) params.push(`orderby=${orderby}`);
    if (currentFolderId) params.push(`folder=${encodeURIComponent(currentFolderId)}`);

    $.post(`/uploads/wipe?${params.join('&')}`, { ids: [id] }, mkAjaxHandler((data: ListUpdateData) => {
      if (!data.status) {
        Dialog.fail(false, data.message);
        return;
      }

      Dialog.close(() => {
        $image.closest('.image-wrap').fadeTo(500, 0, function () {
          $(this).remove();
          applyListUpdate(data);
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

function fetchListPage(page: number, orderby: string | null) {
  const params: Record<string, string | number> = { page };
  if (orderby) params.orderby = orderby;
  if (currentFolderId) params.folder = currentFolderId;

  $uploadList.stop().fadeTo(150, 0.4);

  $.ajax({
    url: '/uploads',
    data: params,
    headers: { Accept: 'application/json' },
    success: mkAjaxHandler((data: ListUpdateData) => {
      if (!data.status) {
        $uploadList.stop().fadeTo(150, 1);
        Dialog.fail(false, data.message);
        return;
      }

      const newSearch = `?page=${page}${orderby ? `&orderby=${orderby}` : ''}${currentFolderId ? `&folder=${encodeURIComponent(currentFolderId)}` : ''}`;
      window.history.pushState(null, '', newSearch);

      if (orderby) {
        const decodedOrderby = orderby.replace('+', ' ');
        $('#ordering-links .sort-link').each(function () {
          const href = $(this).attr('href') || '';
          const hrefOrderby = (href.match(/[?&]orderby=([a-z_]+[+ ](asc|desc))/i) || [])[1] || '';
          $(this).toggleClass('active', hrefOrderby.replace('+', ' ') === decodedOrderby);
        });
      }

      applyListUpdate(data);
      $uploadList.stop().fadeTo(150, 1);
    }),
  });
}

/** Uploads the chosen files one request at a time into the current folder, then reloads the list. */
let uploading = false;
async function uploadFiles(files: File[]) {
  if (files.length === 0 || uploading) return;
  uploading = true;

  const $status = $('#upload-status');
  const errors: string[] = [];
  let ok = 0;
  const maxBytes = Number($('#upload-dropzone').attr('data-max-bytes')) || Infinity;
  for (let i = 0; i < files.length; i += 1) {
    if (files[i].size > maxBytes) {
      errors.push(translatePlaceholders(window.Laravel.jsLocales['upload-too-large'], { name: files[i].name, max: `${Math.round(maxBytes / 1048576)} MB` }));
      continue; // eslint-disable-line no-continue
    }
    $status.text(translatePlaceholders(window.Laravel.jsLocales['upload-progress'], { done: i + 1, total: files.length }));
    const body = new FormData();
    body.append('file', files[i]);
    if (currentFolderId) body.append('folder', currentFolderId);
    try {
      // eslint-disable-next-line no-await-in-loop
      const data = await $.ajax({
        url: '/uploads/upload', method: 'POST', data: body, processData: false, contentType: false,
      }) as { status: boolean; message?: string };
      if (data.status) ok += 1;
      else errors.push(`${files[i].name}: ${data.message}`);
    } catch (e) {
      const xhr = e as JQuery.jqXHR;
      const detail = xhr.status === 413 ? 'too large' : (xhr.responseJSON?.message || xhr.statusText || 'error');
      errors.push(`${files[i].name}: ${detail}`);
    }
  }

  const { orderby } = currentPageParams();
  $status.text(errors.length === 0
    ? translatePlaceholders(window.Laravel.jsLocales['upload-done'], { count: ok })
    : translatePlaceholders(window.Laravel.jsLocales['upload-partial'], { ok, total: files.length, errors: errors.join('; ') }));
  fetchListPage(1, orderby);
  uploading = false;
}

$('#upload-file-input').on('change', function onFilesChosen() {
  const input = this as HTMLInputElement;
  const files = Array.from(input.files || []);
  input.value = '';
  uploadFiles(files);
});

// Files dropped anywhere on the page are uploaded; the zone only highlights to show where they go.
// Without a handler on drop, the browser would navigate away to the dropped file.
const dropZone = document.getElementById('upload-dropzone');
if (dropZone) {
  const isFileDrag = (e: DragEvent) => Array.from(e.dataTransfer?.types || []).includes('Files');
  let dragDepth = 0;
  const highlight = (on: boolean) => dropZone.classList.toggle('border-primary', on);
  window.addEventListener('dragenter', e => {
    if (!isFileDrag(e)) return;
    dragDepth += 1;
    highlight(true);
  });
  window.addEventListener('dragleave', e => {
    if (!isFileDrag(e)) return;
    dragDepth = Math.max(dragDepth - 1, 0);
    if (dragDepth === 0) highlight(false);
  });
  window.addEventListener('dragover', e => {
    if (isFileDrag(e)) e.preventDefault();
  });
  window.addEventListener('drop', e => {
    if (!isFileDrag(e)) return;
    e.preventDefault();
    dragDepth = 0;
    highlight(false);
    uploadFiles(Array.from(e.dataTransfer?.files || []));
  });
}

export function navigateToFolder(folderId: string | null): void {
  currentFolderId = folderId;
  fetchListPage(1, currentPageParams().orderby);
}

$(document).on('keydown', e => {
  if (!/^a$/i.test(e.key) || !e.ctrlKey || e.shiftKey || e.altKey) {
    return;
  }

  e.preventDefault();
  $('.image-wrap').addClass('selected').removeClass('not-selected').find('.selection input')
    .prop('checked', true);
});

$(document).on('click', '.pagination-wrapper a, #ordering-links .sort-link', function (e) {
  e.preventDefault();

  const href = $(this).attr('href') || '';
  const pageMatch = href.match(/[?&]page=(\d+)/);
  const orderbyMatch = href.match(/[?&]orderby=([a-z_]+)[+ ](asc|desc)/i);
  const page = pageMatch ? parseInt(pageMatch[1], 10) : 1;
  const orderby = orderbyMatch ? `${orderbyMatch[1]}+${orderbyMatch[2]}` : currentPageParams().orderby;

  fetchListPage(page, orderby);
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

        const ids = $selection.map(function () {
          return ($(this).find('.image').attr('id') || '').replace(/^upload-/, '');
        }).get();

        const { page, orderby } = currentPageParams();
        const params: string[] = [`page=${page}`];
        if (orderby) params.push(`orderby=${orderby}`);
        if (currentFolderId) params.push(`folder=${encodeURIComponent(currentFolderId)}`);

        $.post(`/uploads/wipe?${params.join('&')}`, { ids }, mkAjaxHandler((data: ListUpdateData) => {
          if (!data.status) {
            Dialog.fail(false, data.message);
            return;
          }

          Dialog.close(() => applyListUpdate(data));
        }));
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

initFolderTree({
  getInitialFolder: getCurrentFolder,
  onFolderChange: navigateToFolder,
});
