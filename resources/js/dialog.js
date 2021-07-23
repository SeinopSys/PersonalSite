/* globals $body,Key,$w,Time */
(function ($, undefined) {
    'use strict';
    let colors = {
            fail: 'danger',
            success: 'success',
            wait: 'info',
            request: 'primary',
            confirm: 'warning',
            info: 'info'
        },
        noticeClasses = {
            fail: 'danger',
            success: 'success',
            wait: 'info',
            request: 'primary',
            confirm: 'warning',
            info: 'info',
        },
        yiq = {
            danger: 'white',
            success: 'white',
            info: 'white',
            primary: 'white',
            warning: 'dark',
        },
        defaultTitles = window.Laravel.dialog.defaultTitles,
        defaultContent = window.Laravel.dialog.defaultContent;

    let $modalElement = $.mk('div').attr({
        'class': 'modal fade',
        tabindex: -1,
        role: 'dialog',
        'aria-labelledby': 'dialogHeader',
        id: 'dialogOverlay',
    }).html(
        `<div class="modal-dialog" role="document" id="dialogBox">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="dialogHeader"></h4>
				</div>
				<div class="modal-body" id="dialogContent"></div>
				<div class="modal-footer" id="dialogButtons"></div>
			</div>
		</div>`
    );

    class Dialog {
        constructor() {
            this.$dialogOverlay = $('#dialogOverlay');
            this.$dialogContent = $('#dialogContent');
            this.$dialogHeader = $('#dialogHeader');
            this.$dialogBox = $('#dialogBox');
            this.$dialogButtons = $('#dialogButtons');
            this._open = this.$dialogContent.length ? {} : undefined;
            this._closeButton = {
                [window.Laravel.dialog.close]: function () {
                    $.Dialog.close()
                }
            };
            this._$focusedElement = undefined;
            this._modalInstance = undefined;
        }

        isOpen() {
            return typeof this._open === 'object'
        }

        #display(type, title, content, buttons, callback) {
            if (typeof type !== 'string' || typeof colors[type] === 'undefined')
                throw new TypeError('Invalid dialog type: ' + typeof type);

            if (typeof buttons === 'function' && typeof callback !== 'function') {
                callback = buttons;
                buttons = undefined;
            }
            let force_new = false;
            if (typeof callback === 'boolean') {
                force_new = callback;
                callback = undefined;
            } else if (typeof buttons === 'boolean' && typeof callback === 'undefined') {
                force_new = buttons;
                buttons = undefined;
            }

            if (typeof title === 'undefined')
                title = defaultTitles[type];
            else if (title === false)
                title = undefined;
            if (!content)
                content = defaultContent[type];
            let params = {
                type: type,
                title: title,
                content: content || defaultContent[type],
                buttons: buttons,
                color: colors[type]
            };

            let append = Boolean(this._open),
                $contentAdd = $.mk('div').append(params.content),
                appendingToRequest = append && this._open.type === 'request' && ['fail', 'wait'].includes(params.type) && !force_new,
                $requestContentDiv;

            if (!append) {
                this._storeFocus();
                $modalElement.clone().appendTo($body);
            }
            if (appendingToRequest) {
                $requestContentDiv = this.$dialogContent.children(':not(#dialogButtons)').last();
                let $errorNotice = $requestContentDiv.children('.notice:last-child');
                if (!$errorNotice.length) {
                    $errorNotice = $.mk('div').append($.mk('p').attr('class','mb-0'));
                    $requestContentDiv.append($errorNotice);
                } else $errorNotice.show();
                $errorNotice
                    .attr('class', 'alert alert-' + noticeClasses[params.type])
                    .children('p').html(params.content).show();
                this._controlInputs(params.type === 'wait');
            } else {
                this._open = params;
                this.$dialogOverlay = $('#dialogOverlay');
                this.$dialogContent = $('#dialogContent');
                this.$dialogHeader = $('#dialogHeader');
                if (typeof params.title === 'string')
                    this.$dialogHeader.html(params.title);
                this.$dialogBox = $('#dialogBox');
                this.$dialogButtons = $('#dialogButtons').empty();
                this._controlInputs(true);
                this.$dialogContent.append($contentAdd);

                this.$dialogButtons[params.buttons ? 'show' : 'hide']();
            }

            if (!appendingToRequest)
                this.$dialogHeader.parent().attr('class', 'modal-header' + (params.color ? ` bg-${params.color} text-${yiq[params.color]}` : ''));

            if (!appendingToRequest && params.buttons) $.each(params.buttons, (name, obj) => {
                let $button = $.mk('button').attr({
                    'type': 'button',
                    'class': 'btn btn-' + params.color
                });
                if (typeof obj === 'function')
                    obj = {action: obj};
                else if (obj.form) {
                    $requestContentDiv = $(`#${obj.form}`);
                    if ($requestContentDiv.length === 1) {
                        $button.on('click', function () {
                            $requestContentDiv.find('input[type=submit]').first().trigger('click');
                        });
                        $requestContentDiv.prepend($.mk('input').attr('type', 'submit').hide());
                    }
                }
                $button.text(name).on('keydown', e => {
                    if ([Key.Enter, Key.Space].includes(e.keyCode)) {
                        e.preventDefault();

                        $button.trigger('click');
                    } else if ([Key.Tab, Key.LeftArrow, Key.RightArrow].includes(e.keyCode)) {
                        e.preventDefault();

                        let $dBc = this.$dialogButtons.children(),
                            $focused = $dBc.filter(':focus'),
                            $inputs = this.$dialogContent.find(':input');

                        if ($.isKey(Key.LeftArrow, e))
                            e.shiftKey = true;

                        if ($focused.length) {
                            if (!e.shiftKey) {
                                if ($focused.next().length) $focused.next().focus();
                                else if ($.isKey(Key.Tab, e)) $inputs.add($dBc).filter(':visible').first().focus();
                            } else {
                                if ($focused.prev().length) $focused.prev().focus();
                                else if ($.isKey(Key.Tab, e)) ($inputs.length > 0 ? $inputs : $dBc).filter(':visible').last().focus();
                            }
                        } else $inputs.add($dBc)[!e.shiftKey ? 'first' : 'last']().focus();
                    }
                }).on('click', e => {
                    e.preventDefault();

                    $.callCallback(obj.action, [e]);
                });
                this.$dialogButtons.append($button);
            });
            if (!append) {
                if (this._modalInstance)
                    this._modalInstance.dispose();
                this._modalInstance = new bootstrap.Modal(this.$dialogOverlay, {
                    backdrop: 'static',
                    keyboard: false,
                });
                this._modalInstance.show();
                this.$dialogOverlay.one('shown.bs.modal', () => {
                    this._setFocus();
                    Time.update();

                    $.callCallback(callback, [$requestContentDiv]);
                    if (append) {
                        let $lastdiv = this.$dialogContent.children().last();
                        if (appendingToRequest) {
                            const $notice = $lastdiv.children('.notice').last();
                            if ($notice.length)
                                $lastdiv = $notice;
                        }
                        this.$dialogOverlay.stop().animate(
                            {
                                scrollTop: '+=' +
                                    ($lastdiv.position().top + parseFloat($lastdiv.css('margin-top'), 10) + parseFloat($lastdiv.css('border-top-width'), 10))
                            },
                            'fast'
                        );
                    }
                });
            }
        }

        fail(title, content, force_new) {
            this.#display('fail', title, content, this._closeButton, force_new === true);
        }

        success(title, content, buttons, callback) {
            let btnobj;
            if (buttons === true)
                btnobj = this._closeButton;
            else if ($.isArray(buttons)) {
                btnobj = {};
                $.each(buttons, function (_, $el) {
                    (function ($el) {
                        btnobj[$el.html()] = function () {
                            $el.trigger('click');
                        };
                    })($el);
                });
            }
            this.#display('success', title, content, btnobj, callback);
        }

        wait(title, additional_info, force_new) {
            if (typeof additional_info === 'boolean' && typeof force_new === 'undefined') {
                force_new = additional_info;
                additional_info = undefined;
            }
            if (typeof additional_info !== 'string')
                additional_info = defaultContent.wait;
            this.#display('wait', title, $.capitalize(additional_info) + '&hellip;', force_new === true);
        }

        request(title, content, confirmBtn, callback) {
            if (typeof confirmBtn === 'function' && typeof callback === 'undefined') {
                callback = confirmBtn;
                confirmBtn = undefined;
            }
            let buttons = {},
                formid,
                classScope = this;
            if (content instanceof jQuery)
                formid = content.attr('id');
            else if (typeof content === 'string') {
                let match = content.match(/<form\sid=["']([^"']+)["']/);
                if (match)
                    formid = match[1];
            }
            if (confirmBtn !== false) {
                if (formid)
                    buttons[confirmBtn || window.Laravel.dialog.submit] = {
                        submit: true,
                        form: formid,
                    };
                buttons[window.Laravel.dialog.cancel] = function () {
                    classScope.close()
                };
            } else buttons[window.Laravel.dialog.close] = {
                action: function () {
                    classScope.close()
                },
                form: formid,
            };

            this.#display('request', title, content, buttons, callback);
        }

        confirm(title, content, btnTextArray, handlerFunc) {
            if (typeof btnTextArray === 'function' && typeof handlerFunc === 'undefined')
                handlerFunc = btnTextArray;

            let classScope = this;
            if (typeof handlerFunc !== 'function')
                handlerFunc = function (b) {
                    if (b) classScope.close()
                };

            if (!$.isArray(btnTextArray))
                btnTextArray = [window.Laravel.dialog.yes, window.Laravel.dialog.no];
            let buttons = {};
            buttons[btnTextArray[0]] = function () {
                handlerFunc(true)
            };
            buttons[btnTextArray[1]] = function () {
                handlerFunc(false);
                classScope.close()
            };
            this.#display('confirm', title, content, buttons);
        }

        info(title, content, callback) {
            this.#display('info', title, content, this._closeButton, callback);
        }

        setFocusedElement($el) {
            if ($el instanceof jQuery)
                this._$focusedElement = $el;
        }

        _storeFocus() {
            if (typeof this._$focusedElement !== 'undefined' && this._$focusedElement instanceof jQuery)
                return;
            let $focus = $(':focus');
            this._$focusedElement = $focus.length > 0 ? $focus.last() : undefined;
        }

        _restoreFocus() {
            if (typeof this._$focusedElement !== 'undefined' && this._$focusedElement instanceof jQuery) {
                this._$focusedElement.focus();
                this._$focusedElement = undefined;
            }
        }

        _setFocus() {
            let $inputs = this.$dialogContent.find('input,select,textarea').filter(':visible'),
                $actions = this.$dialogButtons.children();
            if ($inputs.length > 0)
                $inputs.first().focus();
            else if ($actions.length > 0 && this._open.type !== 'info')
                $actions.first().focus();
        }

        _controlInputs(disable) {
            let $inputs = this.$dialogContent
                .children(':not(#dialogButtons)')
                .last()
                .add(this.$dialogButtons)
                .find('input, button, select, textarea');

            if (disable)
                $inputs.filter(':not(:disabled)').addClass('temp-disable').disable();
            else $inputs.filter('.temp-disable').removeClass('temp-disable').enable();
        }

        close(callback) {
            if (!this.isOpen() || !this._modalInstance)
                return $.callCallback(callback, false);

            this._modalInstance.hide();
            this.$dialogOverlay.one('hidden.bs.modal', () => {
                this.$dialogOverlay.remove();
                this._open = undefined;
                this._restoreFocus();
                $.callCallback(callback);
            });
        }

        clearNotice(regexp) {
            let $notice = this.$dialogContent.children(':not(#dialogButtons)').children('.notice:last-child');
            if (!$notice.length)
                return false;

            if (typeof regexp === 'undefined' || regexp.test($notice.html())) {
                $notice.hide();
                if ($notice.hasClass('info'))
                    this._controlInputs(false);
                return true;
            }
            return false;
        }
    }

    $.Dialog = new Dialog();

    $body.on('keydown', function (e) {
        if (!$.Dialog.isOpen() || e.keyCode !== Key.Tab)
            return true;

        let $inputs = $.Dialog.$dialogContent.find(':input'),
            $focused = $inputs.filter(e.target),
            idx = $inputs.index($focused);

        if ($focused.length === 0) {
            e.preventDefault();
            $inputs.first().focus();
        } else if (e.shiftKey) {
            if (idx === 0) {
                e.preventDefault();
                $.Dialog.$dialogButtons.find(':last').focus();
            } else {
                let $parent = $focused.parent();
                if (!$parent.is($.Dialog.$dialogButtons))
                    return true;
                if ($parent.children().first().is($focused)) {
                    e.preventDefault();
                    $inputs.eq($inputs.index($focused) - 1).focus();
                }
            }
        }
    });
})(jQuery);
