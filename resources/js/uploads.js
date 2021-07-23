(function ($) {
    'use strict';

    let $uploadToggle = $('#uploads-toggle');
    $uploadToggle.on('click', function (e) {
        e.preventDefault();

        let action = $uploadToggle.hasClass('btn-success') ? 'enable' : 'disable',
            title = $uploadToggle.attr('data-dialogtitle'),
            content = $uploadToggle.attr('data-dialogcontent');

        $.Dialog.confirm(title, content, function (sure) {
            if (!sure) return;

            $.Dialog.wait(false);

            $.post(`/uploads/setting/${action}`, $.mkAjaxHandler(function () {
                if (!this.status) return $.Dialog.fail(false, this.message);

                $.Dialog.success(false, this.message);
                setTimeout(function () {
                    window.location.reload()
                }, 1500);
            }));
        });
    });

    let $revealUploadKey = $('#reveal-upload-key'),
        $uploadKeyDisplay = $('#upload-key-display'),
        $copyUploadKey = $('#copy-upload-key'),
        $regenUploadKey = $('#regen-upload-key'),
        key = $uploadKeyDisplay.attr('data-key'),
        hiddenRegex = /^\*+$/;
    $uploadKeyDisplay.removeAttr('data-key');
    $revealUploadKey.on('click', function (e) {
        e.preventDefault();

        let hidden = hiddenRegex.test($uploadKeyDisplay.val());

        $uploadKeyDisplay.val(hidden ? key : key.replace(/./g, '*'));
        $revealUploadKey.children('.fa').toggleClass('fa-eye fa-eye-slash');
    });
    $regenUploadKey.on('click', function (e) {
        e.preventDefault();

        let $btn = $(this);
        $.Dialog.confirm($btn.attr('title'), $btn.attr('data-dialogcontent'), function (sure) {
            if (!sure) return;

            $.Dialog.wait(false);

            $.post('/uploads/regen', $.mkAjaxHandler(function () {
                if (!this.status) return $.Dialog.fail(false, this.message);

                key = this.upload_key;
                $.Dialog.close(function () {
                    $uploadKeyDisplay.fadeTo(200, 0, function () {
                        let hidden = hiddenRegex.test($uploadKeyDisplay.val());
                        $uploadKeyDisplay.val(hidden ? key.replace(/./g, '*') : key);
                        if (hidden)
                            $revealUploadKey.trigger('click');
                        $uploadKeyDisplay.fadeTo(200, 1);
                    });
                });
            }));
        });
    });

    $uploadKeyDisplay.on('click', function (e) {
        e.preventDefault();

        $uploadKeyDisplay[0].select();
    });

    $copyUploadKey.on('click', function (e) {
        e.preventDefault();

        $.copy(key, e);
    });

    let $uploadList = $('#upload-list'),
        $noimgAlert = $('#noimg-alert'),
        $uploadedTotal = $('#uploaded-total');
    $uploadList.on('click', '.copy-upload-link', function (e) {
        e.preventDefault();

        $.copy($(this).siblings().first().prop('href'), e);
    });
    $uploadList.on('click', '.wipe-upload', function (e) {
        e.preventDefault();

        const $selection = $uploadList.children('.selected');
        if ($selection.length) {
            $.Dialog.confirm(
                Laravel.jsLocales.multiwipe_dialog_title,
                $.translatePlaceholders(Laravel.jsLocales.multiwipe_dialog_text, {cnt: $selection.length}),
                function (sure) {
                    if (!sure) return;

                    $.Dialog.wait(false);

                    $selection.each(function () {
                        wipeUpload($(this).find('.wipe-upload'), false);
                    });
                }
            );
            return;
        }

        wipeUpload($(this), true);
    }).on('click', '.image', function (e) {
        if (e.ctrlKey && !e.altKey) {
            e.preventDefault();

            const
                $input = $(this).find('.selection input'),
                newchecked = !$input.prop('checked');
            $input.prop('checked', newchecked);
            selectionChange($input, newchecked);
        }
    }).on('click', '.image .selection input', function (e) {
        const $this = $(this);
        if (e.altKey && !e.ctrlKey)
            $uploadList.find('.not-selected, .selected').removeClass('not-selected selected').find('.selection input').prop('checked', false);
        else selectionChange($this, $this.prop('checked'));
    });

    function wipeUpload($link, requireConfirm) {
        let $image = $link.closest('.image'),
            id = $image.attr('id').replace(/^upload-/, ''),
            $content = $.mk('div').attr('id', 'img-wipe-confirm').append(
                $.mk('p').append($link.attr('data-dialogcontent')),
                $.mk('div').attr('class', 'del-img-wrap').append(
                    $.mk('img').attr('src', $link.siblings().first().attr('href'))
                )
            );
        const confirm = function (sure) {
            if (!sure) return;

            if (requireConfirm)
                $.Dialog.wait(false);

            let page = window.location.search.match(/page=(\d+)/),
                orderby = window.location.search.match(/orderby=([a-z]+)[ +](asc|desc)/i),
                params = [];
            if (page) {
                page = parseInt(page[1], 10);
                if ($image.parent().siblings().length === 0)
                    page = Math.max(page - 1, 1);
                params.push('page=' + page);
            }
            if (orderby)
                params.push('orderby=' + (orderby.slice(1).join('+')));
            $.post('/uploads/wipe' + (params.length ? '?' + (params.join('&')) : ''), {id: id}, $.mkAjaxHandler(data => {
                if (!data.status) return $.Dialog.fail(false, data.message);

                let $newhtml = $(data.newhtml),
                    newTotal = data.total,
                    usedSpace = data.usedSpace;

                $.Dialog.close(function () {
                    $image.closest('.image-wrap').fadeTo(500, 0, function () {
                        $(this).remove();
                        $uploadList.html($newhtml.filter('#upload-list').children());
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
        if (requireConfirm)
            $.Dialog.confirm($link.attr('data-dialogtitle'), $content, confirm);
        else confirm(true);
    }

    function selectionChange($el, checked) {
        const $imageWrap = $el.closest('.image').parent();

        $imageWrap[checked ? 'addClass' : 'removeClass']('selected');
        const $allWraps = $imageWrap.siblings().addBack();
        $allWraps.removeClass('not-selected');
        const $unsel = $allWraps.filter(':not(.selected)');
        if ($unsel.length < $allWraps.length)
            $unsel.addClass('not-selected');
    }

    $(document).on('keydown', e => {
        if (!/^a$/i.test(e.key) || !e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        e.preventDefault();
        $('.image-wrap').addClass('selected').removeClass('not-selected').find('.selection input').prop('checked', true);
    });
})(jQuery);
