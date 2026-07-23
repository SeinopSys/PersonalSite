import { render } from 'preact';
import { Dialog } from '../dialog';
import { copy, mkAjaxHandler, setElDisabled } from '../utils';
import { FolderTree, UploadFolderData } from './FolderTree';

interface FoldersResponse {
  status: boolean;
  message?: string;
  folders: UploadFolderData[];
}

interface FolderMutationResponse {
  status: boolean;
  message?: string;
  id?: string;
  parent_id?: string | null;
  name?: string;
  disable_thumbnails?: boolean;
  disable_conversion?: boolean;
  secondary_domain?: boolean;
  upload_key?: string;
  upload_url?: string;
}

interface FolderTreeControllerOptions {
  getInitialFolder: () => string | null;
  onFolderChange: (folderId: string | null) => void;
}

const $treeMount = $('#upload-folder-tree');
const $toolbar = $('#upload-folder-toolbar');
const $panel = $('#upload-folder-panel');

let folders: UploadFolderData[] = [];
let selectedId: string | null = null;
let onFolderChange: (folderId: string | null) => void = () => undefined;

function findFolder(id: string): UploadFolderData | undefined {
  return folders.find(f => f.id === id);
}

function selectFolder(id: string | null) {
  selectedId = id;
  // renderTree/renderPanel are hoisted function declarations defined further down; the mutual
  // reference with selectFolder (passed to FolderTree as onSelect) is circular at the top level.
  // eslint-disable-next-line no-use-before-define
  renderTree();
  // eslint-disable-next-line no-use-before-define
  renderPanel();
  onFolderChange(id);
}

function renderTree() {
  if (!$treeMount.length) return;
  render(
    <FolderTree folders={folders} selectedId={selectedId} onSelect={selectFolder} />,
    $treeMount[0],
  );
}

function renderPanel() {
  if (!$panel.length) return;

  if (selectedId === null) {
    $panel.empty();
    $toolbar.find('.folder-only').addClass('d-none');
    return;
  }

  const folder = findFolder(selectedId);
  if (!folder) return;

  $toolbar.find('.folder-only').removeClass('d-none');
  $toolbar.find('#upload-folder-name').text(folder.name);

  const $content = $(document.createElement('div')).append(
    $(document.createElement('h4')).text(window.Laravel.jsLocales['folder-key-heading'] || 'Folder upload URL'),
    $(document.createElement('p')).addClass('text-muted small')
      .text(window.Laravel.jsLocales['folder-key-description'] || 'POST file(s) directly to this URL - no other parameters needed.'),
    $(document.createElement('p')).append(
      $(document.createElement('a')).attr({ href: '/docs/api#/operations/uploads.uploadByKey', target: '_blank' }).append(
        $(document.createElement('span')).addClass('fa fa-book'),
        document.createTextNode(` ${window.Laravel.jsLocales['folder-apidocs-link'] || 'API documentation for this URL'}`),
      ),
    ),
    $(document.createElement('div')).addClass('input-group mb-3').append(
      $(document.createElement('button')).addClass('btn btn-secondary').attr('id', 'reveal-folder-key')
        .append($(document.createElement('span')).addClass('fa fa-eye')),
      $(document.createElement('button')).addClass('btn btn-warning').attr('id', 'regen-folder-key')
        .append($(document.createElement('span')).addClass('fa fa-sync')),
      $(document.createElement('input')).attr({ type: 'text', readonly: 'readonly', id: 'folder-key-display' })
        .addClass('form-control').val('••••••••••••••••••••'),
      $(document.createElement('button')).addClass('btn btn-secondary').attr('id', 'copy-folder-key')
        .append($(document.createElement('span')).addClass('fa fa-copy')),
    ),
    $(document.createElement('div')).addClass('form-check').append(
      $(document.createElement('input')).addClass('form-check-input').attr({ type: 'checkbox', id: 'folder-disable-thumbnails' }).prop('checked', folder.disable_thumbnails),
      $(document.createElement('label')).addClass('form-check-label').attr('for', 'folder-disable-thumbnails')
        .text(window.Laravel.jsLocales['folder-settings-thumbnails'] || 'Disable thumbnail generation'),
    ),
    $(document.createElement('div')).addClass('form-check').append(
      $(document.createElement('input')).addClass('form-check-input').attr({ type: 'checkbox', id: 'folder-disable-conversion' }).prop('checked', folder.disable_conversion),
      $(document.createElement('label')).addClass('form-check-label').attr('for', 'folder-disable-conversion')
        .text(window.Laravel.jsLocales['folder-settings-conversion'] || 'Disable format conversion'),
    ),
    $(document.createElement('div')).addClass('form-check mb-3').append(
      $(document.createElement('input')).addClass('form-check-input').attr({ type: 'checkbox', id: 'folder-secondary-domain' }).prop('checked', folder.secondary_domain),
      $(document.createElement('label')).addClass('form-check-label').attr('for', 'folder-secondary-domain')
        .text(window.Laravel.jsLocales['folder-settings-secondary-domain'] || 'Serve files from the secondary domain'),
      $(document.createElement('span')).addClass('fa fa-spinner fa-spin ms-2 d-none').attr('id', 'folder-settings-spinner'),
    ),
  );
  $panel.empty().append($content);

  let url = folder.upload_url;
  let revealed = false;

  const $keyDisplay = $('#folder-key-display');
  const applyKeyDisplay = () => {
    $keyDisplay.val(revealed ? url : url.replace(/./g, '•'));
  };
  applyKeyDisplay();

  $('#reveal-folder-key').on('click', () => {
    revealed = !revealed;
    applyKeyDisplay();
  });
  $('#copy-folder-key').on('click', e => {
    if (!url) return;
    copy(url, e);
  });
  $('#regen-folder-key').on('click', () => {
    Dialog.confirm({
      title: window.Laravel.jsLocales.keyregen || 'Re-generate upload key',
      content: window.Laravel.jsLocales['action-dialog-content-regenkey'],
      handlerFunc: sure => {
        if (!sure) return;
        Dialog.wait(false);
        $.post(`/uploads/folders/${selectedId}/regen`, mkAjaxHandler((data: FolderMutationResponse) => {
          if (!data.status) {
            Dialog.fail(false, data.message);
            return;
          }
          url = data.upload_url || '';
          revealed = true;
          applyKeyDisplay();
          if (selectedId) {
            const updated = findFolder(selectedId);
            if (updated) {
              updated.upload_key = data.upload_key || '';
              updated.upload_url = url;
            }
          }
          Dialog.close();
        }));
      },
    });
  });

  const $settingsCheckboxes = $('#folder-disable-thumbnails, #folder-disable-conversion, #folder-secondary-domain');
  const $settingsSpinner = $('#folder-settings-spinner');
  let settingsRequestPending = false;

  $settingsCheckboxes.on('change', () => {
    if (settingsRequestPending) return;
    settingsRequestPending = true;

    const $thumbnailsCheckbox = $('#folder-disable-thumbnails');
    const $conversionCheckbox = $('#folder-disable-conversion');
    const $secondaryDomainCheckbox = $('#folder-secondary-domain');
    const previousThumbnails = folder.disable_thumbnails;
    const previousConversion = folder.disable_conversion;
    const previousSecondaryDomain = folder.secondary_domain;

    setElDisabled($settingsCheckboxes, true);
    $settingsSpinner.removeClass('d-none');

    $.ajax({
      url: `/uploads/folders/${selectedId}`,
      method: 'PUT',
      data: {
        disable_thumbnails: $thumbnailsCheckbox.prop('checked') ? 1 : 0,
        disable_conversion: $conversionCheckbox.prop('checked') ? 1 : 0,
        secondary_domain: $secondaryDomainCheckbox.prop('checked') ? 1 : 0,
      },
      success: mkAjaxHandler((data: FolderMutationResponse) => {
        settingsRequestPending = false;
        setElDisabled($settingsCheckboxes, false);
        $settingsSpinner.addClass('d-none');

        if (!data.status) {
          Dialog.fail(false, data.message);
          $thumbnailsCheckbox.prop('checked', previousThumbnails);
          $conversionCheckbox.prop('checked', previousConversion);
          $secondaryDomainCheckbox.prop('checked', previousSecondaryDomain);
          return;
        }
        if (selectedId) {
          const updated = findFolder(selectedId);
          if (updated) {
            updated.disable_thumbnails = Boolean(data.disable_thumbnails);
            updated.disable_conversion = Boolean(data.disable_conversion);
            updated.secondary_domain = Boolean(data.secondary_domain);
            if (data.upload_url) {
              updated.upload_url = data.upload_url;
              url = data.upload_url;
              applyKeyDisplay();
            }
          }
        }
      }),
      error: () => {
        settingsRequestPending = false;
        setElDisabled($settingsCheckboxes, false);
        $settingsSpinner.addClass('d-none');
        $thumbnailsCheckbox.prop('checked', previousThumbnails);
        $conversionCheckbox.prop('checked', previousConversion);
        $secondaryDomainCheckbox.prop('checked', previousSecondaryDomain);
        Dialog.fail(false);
      },
    });
  });
}

function loadFolders(selectAfter?: string | null) {
  $.get('/uploads/folders', mkAjaxHandler((data: FoldersResponse) => {
    if (!data.status) return;
    folders = data.folders;
    if (typeof selectAfter !== 'undefined') selectedId = selectAfter;
    renderTree();
    renderPanel();
  }));
}

function buildNameForm(defaultValue = ''): JQuery<HTMLElement> {
  const $form = $<HTMLElement>(document.createElement('form')).attr('id', 'folder-name-form');
  $form.append(
    $(document.createElement('div')).addClass('mb-3').append(
      $(document.createElement('label')).addClass('form-label').attr('for', 'folder-name-input')
        .text(window.Laravel.jsLocales['folder-name-label'] || 'Folder name'),
      $(document.createElement('input')).addClass('form-control').attr({ type: 'text', id: 'folder-name-input', required: 'required' }).val(defaultValue),
    ),
  );
  return $form;
}

function initToolbarActions() {
  $('#create-folder-btn').on('click', () => {
    const $form = buildNameForm();
    Dialog.request({
      title: window.Laravel.jsLocales['folder-create-dialog-title'] || 'Create folder',
      content: $form,
      callback: () => {
        $form.on('submit', e => {
          e.preventDefault();
          const name = $('#folder-name-input').val() as string;
          $.post('/uploads/folders', { name, parent_id: selectedId }, mkAjaxHandler((data: FolderMutationResponse) => {
            if (!data.status) {
              Dialog.fail(false, data.message);
              return;
            }
            Dialog.close(() => loadFolders(data.id));
          }));
        });
      },
    });
  });

  $('#rename-folder-btn').on('click', () => {
    if (!selectedId) return;
    const folder = findFolder(selectedId);
    const $form = buildNameForm(folder ? folder.name : '');
    Dialog.request({
      title: window.Laravel.jsLocales['folder-rename-dialog-title'] || 'Rename folder',
      content: $form,
      callback: () => {
        $form.on('submit', e => {
          e.preventDefault();
          const name = $('#folder-name-input').val() as string;
          $.ajax({
            url: `/uploads/folders/${selectedId}`,
            method: 'PUT',
            data: { name },
            success: mkAjaxHandler((data: FolderMutationResponse) => {
              if (!data.status) {
                Dialog.fail(false, data.message);
                return;
              }
              Dialog.close(() => loadFolders(selectedId));
            }),
          });
        });
      },
    });
  });

  $('#delete-folder-btn').on('click', () => {
    if (!selectedId) return;
    const folder = findFolder(selectedId);
    if (!folder) return;

    const subfolderCount = folders.filter(f => {
      // count all descendants of the selected folder
      let current: UploadFolderData | undefined = f;
      while (current && current.parent_id !== null) {
        if (current.parent_id === selectedId) return true;
        current = findFolder(current.parent_id);
      }
      return false;
    }).length;

    const fileCount = folder.upload_count;

    Dialog.confirm({
      title: (window.Laravel.jsLocales['folder-delete-dialog-title'] || 'Delete folder :name').replace(':name', folder.name),
      content: (window.Laravel.jsLocales['folder-delete-dialog-text'] || 'This will permanently delete :files files and :folders subfolders.')
        .replace(':files', String(fileCount))
        .replace(':folders', String(subfolderCount)),
      handlerFunc: sure => {
        if (!sure) return;
        Dialog.wait(false);
        $.ajax({
          url: `/uploads/folders/${selectedId}`,
          method: 'DELETE',
          success: mkAjaxHandler((data: FolderMutationResponse) => {
            if (!data.status) {
              Dialog.fail(false, data.message);
              return;
            }
            Dialog.close(() => {
              selectedId = null;
              loadFolders(null);
              onFolderChange(null);
            });
          }),
        });
      },
    });
  });
}

export function initFolderTree(options: FolderTreeControllerOptions) {
  if (!$treeMount.length) return;

  onFolderChange = options.onFolderChange;
  selectedId = options.getInitialFolder();

  initToolbarActions();
  loadFolders(selectedId);
}
