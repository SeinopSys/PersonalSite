(function ($, undefined) {
    "use strict";

    if (!window.URL) window.URL = window.webkitURL;

    const pluginScope = {
        player: undefined,
        editor: undefined,
    };

    const LRC_TS_DECIMALS = 2;

    function floatToPercent(float) {
        return ($.roundTo(float, 5) * 100) + '%';
    }

    function clearFocus() {
        const activeELement = document.activeElement;
        if (typeof activeELement === 'object' &&activeELement !== null && 'blur' in activeELement && typeof activeELement.blur === 'function')
            activeELement.blur();
    }

    function binarySearch(arr, compare) { // binary search, with custom compare function
        let l = 0,
            r = arr.length - 1;
        while (l <= r) {
            // jshint -W016
            let m = l + ((r - l) >> 1),
                comp = compare(arr[m]);
            if (comp < 0) // arr[m] comes before the element
                l = m + 1;
            else if (comp > 0) // arr[m] comes after the element
                r = m - 1;
            else // this[m] equals the element
                return m;
        }
        return l - 1; // return the index of the next left item
                      // usually you would just return -1 in case nothing is found
    }

    class LRCString {
        constructor(str = '', ts = undefined, domNode = null) {
            this.str = str.trim();
            this.ts = ts instanceof Duration ? ts : new Duration(ts);
            this.$domNode = domNode;
        }
    }

    class Duration {
        #ignoreMs;

        constructor(seconds, ignoreMilliseconds = false) {
            this.#ignoreMs = ignoreMilliseconds;
            switch (typeof seconds) {
                case 'string':
                    this.#fromString(seconds);
                    break;
                case "number":
                    this.seconds = parseFloat(parseFloat(seconds).toFixed(LRC_TS_DECIMALS));
                    if (this.#ignoreMs)
                        this.seconds = Math.ceil(this.seconds);
                    break;
            }
        }

        toString(padMinutes = false) {
            if (typeof this.seconds === 'undefined')
                return '';
            let time = this.seconds;
            const mins = Math.floor(time / 60);
            if (mins > 0)
                time -= mins * 60;
            const minsStr = padMinutes ? $.pad(mins) : String(mins);

            const [secsStr, msStr] = time.toFixed(LRC_TS_DECIMALS).split('.');
            return `${minsStr}:${$.pad(secsStr)}${this.#ignoreMs ? '' : `.${msStr}`}`;
        }

        #fromString(ts) {
            if (!Duration.isValid(ts)) {
                this.seconds = NaN;
                return;
            }
            let parts = ts.split(':');
            let dur = parseFloat(parts.pop());
            if (parts.length)
                dur += parseInt(parts.pop(), 10) * 60;
            this.seconds = dur;
        }

        static isValid(ts) {
            return /(?:\d+:)?[0-5]?\d:[0-5]\d(?:\.\d{1,2})?/.test(ts);
        }
    }

    class AudioPlaybackIndicator {
        #$stateDiv;
        #$position;
        #$duration;
        #$filename;
        #$filetype;
        #$progressFill;
        #$progressLoaded;
        #$entrySticks;
        #$thumb;

        constructor($stateDiv) {
            this.#$stateDiv = $stateDiv;
            this.#$position = this.#$stateDiv.find('.status-position');
            this.#$duration = this.#$stateDiv.find('.status-duration');
            this.#$filename = this.#$stateDiv.find('.status-filename');
            this.#$filetype = this.#$stateDiv.find('.status-filetype');
            this.$progressWrap = this.#$stateDiv.find('.progress-wrap');
            this.#$progressFill = this.#$stateDiv.find('.progress-indicator .fill');
            this.#$progressLoaded = this.#$stateDiv.find('.progress-indicator .loaded');
            this.#$entrySticks = this.#$stateDiv.find('.progress-indicator .entry-sticks');
            this.#$thumb = this.#$stateDiv.find('.thumb');
            this.setFileName();
            this.setProgress();
            this.showThumb();
        }

        setFileName(name, type) {
            this.#$filename.text(name ? name.replace(/\.[^.]+$/, '') : '');
            this.#$filetype.text(type ? type.split('/')[1].toUpperCase() : '');
        }

        getFileName() {
            return this.#$filename.text().trim();
        }

        setProgress(position, duration) {
            if (position === undefined && duration === undefined) {
                this.#$position.html('&hellip;');
                this.#$duration.html('&hellip;');
                return;
            }
            const
                pos = position || 0,
                dur = duration || 0;
            this.#$position.text(new Duration(pos));
            this.#$duration.text(new Duration(dur));
            this.updateSeek(pos, dur);
        }

        setLoaded(timeRanges, duration) {
            this.#$progressLoaded.empty();
            for (let i = 0; i < timeRanges.length; i++) {
                const
                    start = timeRanges.start(i),
                    end = timeRanges.end(i);
                this.#$progressLoaded.append($.mk('div').css({
                    left: floatToPercent(start / duration),
                    width: floatToPercent((end - start) / duration),
                }));
            }
        }

        /**
         * @param {LRCString[]} entries
         * @param {float}       duration
         */
        setEntries(entries, duration) {
            this.#$entrySticks.empty();
            $.each(entries, (_, el) => {
                this.#$entrySticks.append($.mk('div').css({
                    left: floatToPercent(el.ts.seconds / duration),
                }));
            });
        }

        updateSeek(pos, dur) {
            const perc = floatToPercent(pos / dur);
            this.#$progressFill.css('width', perc);
            this.#$thumb.css('left', perc);
        }

        showThumb(isPlaying) {
            this.#$stateDiv[isPlaying ? 'addClass' : 'removeClass']('playing');
        }
    }

    class AudioPlayer {
        #$player;
        #$filein;
        #$audiofilebtn;
        #audio;
        #mediatags;
        #volumeStep;
        #$volumedisp;
        #updateIndTimer;
        #stateInd;

        constructor() {
            this.#$player = $('#player');
            this.#$filein = $.mk('input', 'audiofilein').attr({
                type: 'file',
                accept: 'audio/*',
                tabindex: -1,
                'class': 'fileinput',
            }).appendTo($body);

            this.#$audiofilebtn = $('#audiofilebtn');
            this.$playbackbtn = $('#playbackbtn');
            this.#audio = new Audio();
            this.#audio.preload = 'auto';
            this.$stopbtn = $('#stopbtn');
            $(this.#audio).on('ended', () => {
                this.stop();
                this.updatePlaybackButtons();
            }).on('loadeddata', () => {
                this.#stateInd.setProgress();
                this.updateInd();
                this.#$audiofilebtn.enable();
                this.updatePlaybackButtons();
                this.#stateInd.showThumb(true);
                this.updateEntrySticks();
                this.setMetadataLength();
            });

            this.#mediatags = {};

            this.#volumeStep = 0.05;
            this.#$volumedisp = $('#volumedisp');
            this.$volumedown = $('#volumedown');
            this.$volumeup = $('#volumeup');
            let sessionVol = AudioPlayer.getSessionVolume();
            this.volume(sessionVol);
            this.updateVolumeButtons(sessionVol);

            this.#updateIndTimer = undefined;
            this.#stateInd = new AudioPlaybackIndicator(this.#$player.find('.state'));
            let resumeOnRelease;
            this.#stateInd.$progressWrap.on('mousedown', e => {
                if (e.which !== 1 || !this.hasFile())
                    return;

                e.preventDefault();

                this.#stateInd.$progressWrap.addClass('moving');
                resumeOnRelease = !this.#audio.paused;
                this.pause();
                this.updatePlaybackButtons();
            });
            $d.on('mouseup', e => {
                if (e.which !== 1 || !this.#stateInd.$progressWrap.hasClass('moving') || !this.hasFile())
                    return;

                this.recalcThumbPos(e);
                this.#stateInd.$progressWrap.removeClass('moving');
                if (resumeOnRelease) {
                    this.play();
                    this.updatePlaybackButtons();
                }
            });
            $d.on('mousemove', $.throttle(100, e => {
                if (!this.#stateInd.$progressWrap.hasClass('moving') || !this.hasFile())
                    return;

                this.recalcThumbPos(e);
            }));

            this.$stopbtn.on('click', e => {
                e.preventDefault();

                if (!this.hasFile())
                    return;

                this.stop();
                this.updatePlaybackButtons();
                clearFocus();
            });
            this.$playbackbtn.on('click', e => {
                e.preventDefault();

                if (!this.hasFile())
                    return;

                if (this.#audio.paused)
                    this.play();
                else this.pause();
                this.updatePlaybackButtons();
                clearFocus();
            });
            this.$volumedown.on('click', (e, shiftKey, altKey) => {
                e.preventDefault();

                const newvol = this.volume() - (e.altKey || altKey ? 0.01 : this.#volumeStep * (e.shiftKey || shiftKey ? 2 : 1));
                this.updateVolumeButtons(newvol);
                this.volume(newvol);
                clearFocus();
            });
            this.$volumeup.on('click', (e, shiftKey, altKey) => {
                e.preventDefault();

                const newvol = this.volume() + (e.altKey || altKey ? 0.01 : this.#volumeStep * (e.shiftKey || shiftKey ? 2 : 1));
                this.updateVolumeButtons(newvol);
                this.volume(newvol);
                clearFocus();
            });
            this.#$audiofilebtn.on('click', e => {
                e.preventDefault();

                this.#$filein.trigger('click');
                clearFocus();
            });
            this.#$filein.on('change', () => {
                const val = this.#$filein.val();

                if (!val)
                    return;

                this.clearFile();
                this.#$audiofilebtn.disable();
                this.setFile(this.#$filein[0].files[0], file => {
                    this.#stateInd.setFileName(file.name, file.type);
                });
            });
        }

        /** @return {AudioPlayer} */
        static getInstance() {
            if (typeof pluginScope.player === 'undefined')
                pluginScope.player = new AudioPlayer();
            return pluginScope.player;
        }

        static getSessionVolume() {
            if (!window.localStorage)
                return 0.5;

            let stored = localStorage.getItem('lrc-vol');
            if (!stored || isNaN(stored)) {
                stored = '0.5';
                localStorage.setItem('lrc-vol', stored);
            }

            return parseFloat(stored);
        }

        volume(float) {
            if (typeof float === 'undefined')
                return this.#audio.volume;

            float = $.rangeLimit($.roundTo(float, 2), false, 0, 1);
            this.#$volumedisp.text($.pad(Math.round(float * 100), ' ', 3) + '%');
            if (window.localStorage)
                localStorage.setItem('lrc-vol', float);
            this.#audio.volume = float;
        }

        updateVolumeButtons(newvol) {
            this.$volumeup.attr('disabled', newvol >= 1);
            this.$volumedown.attr('disabled', newvol <= 0);
        }

        updatePlaybackButtons() {
            const fileMissing = !this.hasFile();
            this.$playbackbtn.attr('disabled', fileMissing);
            this.$stopbtn.attr('disabled', fileMissing);
            TimingEditor.getInstance().disableModeButton(fileMissing);
            this.$playbackbtn.children().removeClass('fa-play fa-pause');
            if (!this.#audio.paused)
                this.$playbackbtn.children().addClass('fa-pause');
            else this.$playbackbtn.children().addClass('fa-play');
        }

        play() {
            if (!this.#audio.paused)
                return;

            this.#audio.play();
            this.#startUpdateIndTimer();
            this.#stateInd.showThumb(true);
        }

        pause(skipIndUpd = false) {
            this.#stopUpdateIndTimer();

            if (this.#audio.paused)
                return;
            this.#audio.pause();
            if (!skipIndUpd)
                this.updateInd();
        }

        stop() {
            this.pause(true);
            this.#audio.currentTime = 0;
            this.updateInd();
        }

        seek(seconds) {
            if (!this.hasFile())
                return;

            this.#audio.currentTime = $.rangeLimit(this.#audio.currentTime + seconds, false, 0, this.#audio.duration);
            this.updateInd();
        }

        /**
         * @param {File} file
         * @param {Function} callback
         */
        setFile(file, callback) {
            this.stop();
            this.#stateInd.showThumb(false);
            this.updatePlaybackButtons();
            if (!/^audio\//.test(file.type)) {
                $.Dialog.fail(Laravel.jsLocales.dialog_format_error, Laravel.jsLocales.dialog_format_notaudio);
                return;
            }
            this.#audio.src = URL.createObjectURL(file);
            new jsmediatags.Reader(file)
                .setTagsToRead(['title', 'artist', 'album'])
                .read({
                    onSuccess: data => {
                        if (typeof data === 'object') {
                            this.setMediaTags(data.tags);
                        }
                    },
                    onError: function (error) {
                        console.log('Failed to read ID3 tags', error.type, error.info);
                    }
                });
            callback(file);
        }

        hasFile() {
            return this.#audio.readyState !== 0;
        }

        clearFile() {
            this.stop();
            if (this.#audio.src) {
                URL.revokeObjectURL(this.#audio.src);
            }
            this.#audio.src = "";
            this.clearMediaTags();
        }

        getFileName() {
            const tags = this.#mediatags;
            if (tags.title) {
                if (tags.artist) {
                    return `${tags.artist} - ${tags.title}`;
                }
                return tags.title;
            }
            return this.#stateInd.getFileName();
        }

        clearMediaTags() {
            this.#mediatags = {};
            TimingEditor.getInstance().setInitialMetadata({length: null});
        }

        setMediaTags(tags) {
            if (typeof tags !== 'object')
                throw new Error(`setMediaTags: tags must be an object, ${typeof tags} given.`);

            this.#mediatags = tags;
            TimingEditor.getInstance().setInitialMetadata(this.#mediatags);
        }

        getMediaTags() {
            return this.#mediatags;
        }

        #startUpdateIndTimer() {
            this.#stopUpdateIndTimer();
            this.#updateIndTimer = setInterval(() => {
                this.updateInd();
            }, 85);
        }

        #stopUpdateIndTimer() {
            if (typeof this.#updateIndTimer === 'undefined')
                return;

            clearInterval(this.#updateIndTimer);
            this.#updateIndTimer = undefined;
        }

        updateInd() {
            this.#stateInd.setProgress(this.#audio.currentTime, this.#audio.duration);
            this.#stateInd.setLoaded(this.#audio.buffered, this.#audio.duration);
            TimingEditor.getInstance().hlEntry(this.getPlaybackPosition());
        }

        updateEntrySticks() {
            if (!this.hasFile())
                return;

            this.#stateInd.setEntries(TimingEditor.getInstance().getTimings(), this.#audio.duration);
        }

        setMetadataLength() {
            TimingEditor.getInstance().setInitialMetadata({length: new Duration(this.#audio.duration, true)});
        }

        getPlaybackPosition() {
            if (!this.hasFile())
                return;

            return new Duration(this.#audio.currentTime);
        }

        /** @param {string} ts */
        setPlaybackPosition(ts) {
            if (!this.hasFile() || !Duration.isValid(ts))
                return;

            this.#audio.currentTime = new Duration(ts).seconds;
            this.updateInd();
        }

        recalcThumbPos(e) {
            const
                pwOffset = this.#stateInd.$progressWrap.offset(),
                pwWidth = parseInt(this.#stateInd.$progressWrap.css('width'), 10),
                relativeX = $.rangeLimit(e.clientX - pwOffset.left, false, 0, pwWidth - 0.00001);

            this.#audio.currentTime = this.#audio.duration * (relativeX / pwWidth);
            this.#stateInd.updateSeek(relativeX, pwWidth);
            this.updateInd();
        }
    }

    const LRC_META_TAGS = {
        ar: 'artist',
        ti: 'title',
        al: 'album',
        au: 'lyrics_author',
        length: 'length',
        by: 'file_author',
        offset: 'offset',
        re: 'created_with',
        ve: 'version',
    };
    const LRC_TS_REGEX = /\[([\d:.]+)]/g;
    const LRC_META_REGEX = new RegExp(`^\\\[(${Object.keys(LRC_META_TAGS).join('|')}):([^\\\]]+)]$`);

    class LRCParser {
        constructor(lrcfile) {
            this.timings = [];
            this.metadata = {};
            const file = lrcfile.trim();
            if (file.length === 0)
                throw new Error(Laravel.jsLocales.dialog_parse_error_empty);
            const lines = lrcfile.trim().split('\n');
            $.each(lines, (_, el) => {
                const timestamps = el.match(LRC_TS_REGEX);
                if (timestamps) {
                    const text = el.replace(LRC_TS_REGEX, '');
                    $.each(timestamps, (_, ts) => {
                        this.timings.push(new LRCString(text, ts.substring(1, ts.length - 2)));
                    });
                } else {
                    const trimmedEl = el.trim();
                    const metadata = trimmedEl.match(LRC_META_REGEX);
                    if (metadata) {
                        this.metadata[metadata[1]] = metadata[2];
                    }
                }
            });
            this.timings.sort((a, b) => a.ts.seconds - b.ts.seconds);
        }
    }

    class MetadataEditingForm {
        #$form;

        constructor(compiledMetadata) {
            this.#$form = $.mk('form', 'metadata-editing-form');
            $.each(LRC_META_TAGS, (short, long) => {
                const id = `meta_input_${short}`;
                const inputAttrs = {
                    type: 'text',
                    name: short,
                    id,
                    value: compiledMetadata[short],
                    'class': 'form-control',
                };
                if (short === 'offset') {
                    inputAttrs.type = 'number';
                    inputAttrs.step = '0.001';
                    inputAttrs.min = '0';
                    inputAttrs.placeholder = '0';
                    if (inputAttrs.value === inputAttrs.placeholder)
                        inputAttrs.value = '';
                }
                this.#$form.append(
                    $.mk('div').attr('class', 'mb-3').append(
                        $.mk('label')
                            .attr({
                                'for': id,
                            })
                            .append(
                                Laravel.jsLocales.metadata_field_placeholders[long],
                                ` <code>${short}</code>`
                            ),
                        $.mk('input')
                            .attr(inputAttrs)
                    )
                );
            });
            this.#$form.prepend(
                $.mk('div').attr('class', ' alert alert-info text-center').append(
                    $.mk('span').text(Laravel.jsLocales.dialog_edit_meta_reset_info),
                    mk('br'),
                    $.mk('button').attr({
                        'class': 'btn btn-info',
                        type: 'reset',
                    }).append(
                        '<i class="fa fa-undo"/>',
                        $.mk('span').text(Laravel.jsLocales.dialog_edit_meta_reset_btn)
                    )
                )
            );
        }

        get() {
            return this.#$form.clone();
        }
    }

    /** @property {LRCString[]} timings */
    class TimingEditor {
        #mergedOutputStrategy;
        #$timings;
        #$lrcmodebtn;
        #$lrcfilebtn;
        #$filein;
        #$lrcpastebtn;
        #$lrcexportbtn;
        #$lrcexportnometabtn;
        #$lrcmergetogglebtn;
        #$lrcclrbtn;
        #$lrcmetadatabtn;
        #$editor;
        #$entryTemplate;
        #lastLRCFilename;
        #mode;

        constructor() {
            this.#$timings = $('#timings');
            this.#$lrcmodebtn = $('#lrcmodebtn');
            this.#$lrcfilebtn = $('#lrcfilebtn');
            this.#$filein = $.mk('input', 'lrcfilein').attr({
                type: 'file',
                accept: '.lrc',
                tabindex: -1,
                'class': 'fileinput'
            }).appendTo($body);
            this.#$lrcpastebtn = $('#lrcpastebtn');
            this.#$lrcexportbtn = $('#lrcexportbtn');
            this.#$lrcexportnometabtn = $('#lrcexportnometabtn');
            this.#$lrcmergetogglebtn = $('#lrcmergetogglebtn');
            this.#$lrcclrbtn = $('#lrcclrbtn');
            this.#$lrcmetadatabtn = $('#lrcmetadatabtn');
            this.#$editor = this.#$timings.find('.editor');
            this.#$entryTemplate = $('#editor-entry-template').children();
            this.changeMode('edit');
            this.timings = [];
            this.initialMetadata = {
                offset: '0',
                re: `${window.location.href} - SeinopSys' LRC Editor`,
                ve: window.Laravel.git.commit_id,
            };
            this.metadata = {};
            this.#$editor.append(this.makeEntryDiv(new LRCString()));

            this.#lastLRCFilename = undefined;
            this.#mergedOutputStrategy = TimingEditor.getMergedOutputStrategyDefault();
            this.#updateMergeStrategyButton();

            this.#$lrcmodebtn.on('click', e => {
                e.preventDefault();

                if (this.#$lrcmodebtn.is('[disabled]'))
                    return;

                this.changeMode(null, e.altKey);
                clearFocus();
            });
            this.#$lrcpastebtn.on('click', e => {
                e.preventDefault();

                const $form = $.mk('form', 'rawlyrics').append(
                    `<div class="mb-3">
						<textarea class="form-control" rows="10"></textarea>
					</div>
					<p class="text-info"><span class="fa fa-info-circle me-2"></span>${Laravel.jsLocales.dialog_pasteraw_info}</p>`
                );
                $.Dialog.request(Laravel.jsLocales.dialog_pasteraw_title, $form, Laravel.jsLocales.dialog_pasteraw_action, $form => {
                    $form.on('submit', e => {
                        e.preventDefault();

                        $.Dialog.wait(false, 'Importing');
                        const lines = $form.find('textarea').val().trim().split(/\n+/g);
                        lines.push(''); // Add an empty line to account for an outro
                        this.timings = lines.map(el => new LRCString(el));
                        AudioPlayer.getInstance().updateEntrySticks();
                        this.regenEntries();
                        this.#lastLRCFilename = undefined;

                        $.Dialog.close();
                    });
                });
            });
            this.#$lrcexportbtn.on('click', e => {
                e.preventDefault();

                this.storeTimings();
                this.exportLRCFile();
            });
            this.#$lrcexportnometabtn.on('click', e => {
                e.preventDefault();

                this.storeTimings();
                this.exportLRCFile(false);
            });
            this.#$lrcmergetogglebtn.on('click', e => {
                e.preventDefault();

                this.#toggleMergeStrategy();
            });
            this.#$lrcclrbtn.on('click', e => {
                e.preventDefault();

                $.Dialog.confirm($(e.target).text(), undefined, sure => {
                    if (!sure) return;

                    this.#$editor.empty();
                    this.#$editor.append(this.makeEntryDiv(new LRCString()));
                    this.storeTimings();
                    $.Dialog.close();
                });
            });
            this.#$lrcmetadatabtn.on('click', e => {
                e.preventDefault();

                const $form = new MetadataEditingForm(this.getCurrentMetadata()).get();
                $.Dialog.request(Laravel.jsLocales.dialog_edit_meta, $form, Laravel.jsLocales.save, () => {
                    $form.on('submit', e => {
                        e.preventDefault();

                        const data = $form.mkData();
                        $.each(LRC_META_TAGS, key => {
                            this.metadata[key] = data[key].trim();
                        });
                        $.Dialog.close();
                    });
                    $form.on('reset', e => {
                        e.preventDefault();

                        this.metadata = {};
                        const newMetadata = this.getCurrentMetadata();
                        $.each(LRC_META_TAGS, key => {
                            $form.find(`input[name=${key}]`).val(newMetadata[key]);
                        });
                    });
                });
            });
            this.#$editor.on('mouseenter', () => {
                this.#$editor.stop();
            });
            this.#$editor.on('keyup', '.timestamp', e => {
                if (this.#mode !== 'edit')
                    return;

                const
                    $ts = $(e.target),
                    val = $ts.text().trim(),
                    valInvalid = !Duration.isValid(val);
                $ts[val.length && valInvalid ? 'addClass' : 'removeClass']('invalid');
                $ts.siblings('.tools').children('.goto').attr('disabled', valInvalid);
                AudioPlayer.getInstance().updateEntrySticks();
            });
            this.#$editor.on('click', '.addrow-up, .addrow-down', e => {
                e.preventDefault();

                const insertWhere = $(e.target).closest('button').attr('class').match(/addrow-(up|down)/)[1] === 'up' ? 'insertBefore' : 'insertAfter';
                const $entry = this.makeEntryDiv(new LRCString())[insertWhere]($(e.target).closest('.time-entry'));
                this.updateEntryActionButtons();
                this.storeTimings();
                $entry.addClass('new');
                this.scrollHighlightedIntoView($entry, false);
            });
            this.#$editor.on('click', '.remrow', e => {
                e.preventDefault();

                const $entry = $(e.target).closest('.time-entry');
                if ($entry.siblings().length === 0)
                    return;

                $entry.remove();
                this.updateEntryActionButtons();
                this.storeTimings();
                this.hlEntry(AudioPlayer.getInstance().getPlaybackPosition());
            });
            this.#$editor.on('click', '.goto', e => {
                e.preventDefault();

                const $button = $(e.target).closest('.goto');
                if ($button.is(':disabled'))
                    return;

                const $entry = $button.parents('.time-entry');
                const ts = $entry.find('.timestamp').text();
                AudioPlayer.getInstance().setPlaybackPosition(ts);

                if (this.#mode === 'sync')
                    this.passSyncHandle($entry);
                clearFocus();
            });
            this.#$lrcfilebtn.on('click', e => {
                e.preventDefault();

                this.#$filein.trigger('click');
                clearFocus();
            });
            this.#$filein.on('change', () => {
                const val = this.#$filein.val();

                if (!val) return;

                this.#$lrcfilebtn.disable();
                this.readLRCFile(this.#$filein[0].files[0], success => {
                    if (success)
                        this.regenEntries();
                    else this.#$filein.val('');
                    this.#$lrcfilebtn.enable();
                });
            });
        }

        /** @return {TimingEditor} */
        static getInstance() {
            if (typeof pluginScope.editor === 'undefined')
                pluginScope.editor = new TimingEditor();
            return pluginScope.editor;
        }

        disableModeButton(disable) {
            this.#$lrcmodebtn.attr('disabled', disable);
        }

        changeMode(mode = null, preservePosition = false) {
            if (mode === null)
                mode = this.#mode === 'sync' ? 'edit' : 'sync';
            else if ($.inArray(mode, ['sync', 'edit']) === -1)
                throw new Error('Invalid mode: ' + mode);
            if (this.#mode === mode)
                return;

            const $children = this.#$lrcmodebtn.children();
            const modeIcon = ({edit: 'edit', sync: 'time'})[mode];
            const otherMode = mode === 'sync' ? 'edit' : 'sync';
            const otherIcon = mode === 'sync' ? 'edit' : 'clock';
            $children.first().removeClass(`fa-${modeIcon}`).addClass(`fa-${otherIcon}`).next().text(this.#$lrcmodebtn.attr(`data-${otherMode}mode`));
            this.#$editor.removeClass(`mode-${otherMode}`).addClass(`mode-${mode}`);
            this.#mode = mode;

            switch (this.#mode) {
                case "edit":
                    this.revokeSyncHandle();
                    this.#$editor.find('.text, .timestamp').attr('contenteditable', true);
                    this.#$editor.find('.sync-handle').removeClass('sync-handle');
                    break;
                case "sync":
                    this.#$editor.find('.text, .timestamp').removeAttr('contenteditable');
                    const $handle = this.getSyncHandle(preservePosition);
                    this.scrollHighlightedIntoView($handle, false);
                    break;
            }
        }

        getTimings() {
            return this.timings;
        }

        setTimings(timings) {
            this.timings = timings;
            this.regenEntries();
            AudioPlayer.getInstance().updateEntrySticks();
        }

        setInitialMetadata(metadata) {
            $.each(['ar', 'ti', 'al', 'length'], (_, el) => {
                const long = LRC_META_TAGS[el];
                if (metadata[long])
                    this.initialMetadata[el] = metadata[long];
                else if (metadata[long] === null)
                    delete this.initialMetadata[el];
            });
        }

        setMetadata(metadata) {
            this.metadata = metadata;
        }

        getCurrentMetadata() {
            return $.extend({}, this.initialMetadata, this.metadata);
        }

        storeTimings() {
            const $children = this.#$editor.children();
            let timings = [];
            $children.each(function () {
                const
                    $entry = $(this),
                    ts = $entry.children('.timestamp').text().trim();
                if (!ts.length || !Duration.isValid(ts))
                    return;

                const text = $entry.children('.text').text().trim().split('\n');

                $.each(text, (_, el) => {
                    timings.push(new LRCString(
                        el,
                        ts,
                        $entry
                    ));
                });
            });
            this.timings = timings;
            AudioPlayer.getInstance().updateEntrySticks();
        }

        makeEntryDiv(lrcstring) {
            const $clone = this.#$entryTemplate.clone();
            $clone.children().first().text(lrcstring.ts.toString()).trigger('keyup').next().text(lrcstring.str);
            return $clone;
        }

        regenEntries() {
            this.#$editor.empty();
            $.each(this.timings, (i, el) => {
                this.timings[i].$domNode = this.makeEntryDiv(el);
                this.#$editor.append(this.timings[i].$domNode);
            });
            this.passSyncHandle(this.#$editor.children().first());
            this.updateEntryActionButtons();
        }

        updateEntryActionButtons() {
            const $entries = this.#$editor.children();
            $entries.find('.remrow').attr('disabled', $entries.length === 1);
            $entries.find('.goto').disable().filter(function () {
                return Duration.isValid($(this).parent().siblings().first().text().trim());
            }).enable();
        }

        readLRCFile(file, callback) {
            const reader = new FileReader();
            reader.onload = () => {
                try {
                    const parsed = new LRCParser(reader.result);
                    this.setTimings(parsed.timings);
                    this.setMetadata(parsed.metadata);
                } catch (e) {
                    $.Dialog.fail(Laravel.jsLocales.dialog_parse_error, e.message);
                    callback(false);
                    return;
                }
                this.#lastLRCFilename = file.name.replace(/\.lrc$/, '');
                callback(true);
            };
            reader.readAsText(file);
        }

        static giveSyncHandleTo($el) {
            $el.addClass('sync-handle');
            $el.find('.timestamp').focus();
            return $el;
        }

        getSyncHandle(nowplayingAsHandle = false) {
            if (this.#mode === 'edit')
                return;

            const $children = this.#$editor.children();
            if (!$children.length)
                return;

            let $handle = this.#$editor.children('.sync-handle');
            const $nowplaying = this.#$editor.children('.nowplaying').removeClass('nowplaying');
            if (nowplayingAsHandle && !$handle.length) {
                $handle = $nowplaying;
                if ($handle.length)
                    $handle.addClass('sync-handle');
            }
            if (!$handle.length)
                $handle = TimingEditor.giveSyncHandleTo(this.#$editor.children().first());
            return $handle;
        }

        revokeSyncHandle() {
            const $current = this.getSyncHandle();
            if (typeof $current !== 'undefined')
                $current.removeClass('sync-handle nowplaying').removeAttr('title');

            return $current;
        }

        passSyncHandle($next = null, resetOnEnd = true) {
            if (this.#mode === 'edit')
                return;

            const $current = this.revokeSyncHandle();

            if ($next === null)
                $next = $current.next();

            if (!$next.length) {
                if (resetOnEnd) {
                    this.changeMode('edit');
                    return;
                }
                $next = $current;
            }

            TimingEditor.giveSyncHandleTo($next);
            this.scrollHighlightedIntoView($next, false);
        }

        syncEntry(writeTs = true, resetOnEnd = true) {
            if (this.#mode === 'edit')
                return;

            const
                $handle = this.getSyncHandle(),
                pos = AudioPlayer.getInstance().getPlaybackPosition();

            if (writeTs) {
                const $prevTs = $handle.prev().find('.timestamp');
                if ($prevTs.text().trim() === pos) {
                    $prevTs.removeClass('flash').addClass('flash');
                    return;
                }

                $handle.find('.timestamp').text(pos).trigger('keyup');
            }
            this.passSyncHandle(null, resetOnEnd);
            this.storeTimings();
            this.updateEntryActionButtons();
            AudioPlayer.getInstance().updateEntrySticks();
        }

        syncBreakEntry() {
            if (this.#mode === 'edit')
                return;

            const
                $handle = this.getSyncHandle(),
                pos = AudioPlayer.getInstance().getPlaybackPosition();

            this.makeEntryDiv(new LRCString('', pos)).insertBefore($handle).find('[contenteditable]').removeAttr('contenteditable');
            this.scrollHighlightedIntoView($handle, false);
            this.updateEntryActionButtons();
            this.storeTimings();
            AudioPlayer.getInstance().updateEntrySticks();
        }

        undoSync() {
            if (this.#mode === 'edit')
                return;

            const $handle = this.getSyncHandle();
            if ($handle.index() === 0)
                return;
            this.passSyncHandle($handle.prev());
            this.storeTimings();
            this.updateEntryActionButtons();
            AudioPlayer.getInstance().updateEntrySticks();
        }

        emptySync() {
            if (this.#mode === 'edit')
                return;

            const $handle = this.getSyncHandle();
            $handle.find('.timestamp').empty().trigger('keyup');
            this.passSyncHandle();
            this.storeTimings();
            AudioPlayer.getInstance().updateEntrySticks();
        }

        /** @vparam {Duration} position */
        hlEntry(position) {
            if (this.#mode === 'sync' || typeof position === 'undefined')
                return;

            const usableTimings = this.getTimings();
            const i = binarySearch(usableTimings, n => n.ts.seconds - position.seconds);
            if (i < 0) {
                $('.nowplaying').removeClass('nowplaying');
                return;
            }

            const $hl = usableTimings[i].$domNode;
            if ($hl.hasClass('nowplaying'))
                return;

            this.scrollHighlightedIntoView($hl);
        }

        scrollHighlightedIntoView($hl, highlight = true) {
            if (highlight)
                $hl.addClass('nowplaying').siblings().removeClass('nowplaying');

            if (this.#$editor.is(':hover'))
                return;

            const
                hlpos = $hl.position(),
                edpos = this.#$editor.position(),
                scrl = this.#$editor.scrollTop();

            const newscrl = scrl + (hlpos.top - edpos.top) - (this.#$editor.height() / 2) + ($hl.height() / 2);
            this.#$editor.stop().animate({scrollTop: newscrl}, 400);
        }

        /**
         * Grab last value from local storage and use if available
         * @returns {boolean}
         */
        static getMergedOutputStrategyDefault() {
            if (!window.localStorage)
                return true;

            let stored = localStorage.getItem('lrc-merge');
            if (!stored || stored !== 'false') {
                stored = 'true';
                localStorage.setItem('lrc-merge', stored);
            }

            return stored === 'true';
        }

        #toggleMergeStrategy() {
            this.#mergedOutputStrategy = !this.#mergedOutputStrategy;
            if (window.localStorage)
                localStorage.setItem('lrc-merge', this.#mergedOutputStrategy ? 'true' : 'false');
            this.#updateMergeStrategyButton();
        }

        #updateMergeStrategyButton() {
            const $status = this.#$lrcmergetogglebtn.find('.status');
            if (this.#mergedOutputStrategy) {
                $status.addClass('text-success').removeClass('text-danger');
            } else {
                $status.removeClass('text-success').addClass('text-danger');
            }
            $status.text($status.attr(`data-${this.#mergedOutputStrategy ? 'true' : 'false'}`));
        }

        exportLRCFile(includeMetadata = true) {
            let outputArr = [];
            if (includeMetadata) {
                const metadata = this.getCurrentMetadata();
                $.each(metadata, (k, v) => {
                    if (v !== '') {
                        switch (k) {
                            case 'offset':
                                if (v === '0')
                                    return;
                                break;
                            case 'length':
                                v = ` ${v}`;
                                break;
                        }
                        outputArr.push(`[${k}:${v}]`);
                    }
                });
            }
            if (this.#mergedOutputStrategy) {
                const strings = {};
                $.each(this.timings, (i, el) => {
                    if (typeof strings[el.str] === 'undefined')
                        strings[el.str] = [];

                    strings[el.str].push(el.ts.toString(true));
                });
                $.each(strings, (str, tsArr) => {
                    outputArr.push(`[${tsArr.join('][')}]${str}`);
                });
            } else {
                outputArr = [
                    ...outputArr,
                    ...this.timings.map(el => `[${el.ts.toString(true)}]${el.str}`),
                ];
            }
            const output = outputArr.join('\n') + '\n';
            const basename = this.#lastLRCFilename || AudioPlayer.getInstance().getFileName() || 'Lyrics';
            const filename = `${basename}.lrc`;

            const blob = new Blob([output], {type: "text/plain;charset=utf-8"});
            saveAs(blob, filename);
        }
    }

    // Create instances
    AudioPlayer.getInstance();
    TimingEditor.getInstance();

    $d.on('keydown', $.throttle(200, function (e) {
        const tagname = e.target.tagName.toLowerCase();
        if ((tagname === 'input' && e.target.type !== 'file') || tagname === 'textarea' || e.target.getAttribute('contenteditable') !== null)
            return;

        switch (e.keyCode) {
            case Key.LeftArrow:
                pluginScope.player.seek(-2.5 * (e.shiftKey ? 2 : 1));
                break;
            case Key.RightArrow:
                pluginScope.player.seek(2.5 * (e.shiftKey ? 2 : 1));
                break;
            case Key.PageUp:
                pluginScope.player.$volumeup.trigger('click', [e.shiftKey, e.altKey]);
                break;
            case Key.PageDown:
                pluginScope.player.$volumedown.trigger('click', [e.shiftKey, e.altKey]);
                break;
            case Key.Space:
                pluginScope.player.$playbackbtn.trigger('click');
                break;
            case Key.Period:
                pluginScope.player.$stopbtn.trigger('click');
                break;
            case Key.Enter:
                if (e.ctrlKey && !e.altKey) {
                    e.preventDefault();
                    pluginScope.editor.syncBreakEntry(e);
                }
                else {
                    e.preventDefault();
                    pluginScope.editor.syncEntry();
                }
                break;
            case Key.UpArrow:
                pluginScope.editor.undoSync();
                break;
            case Key.DownArrow:
                pluginScope.editor.syncEntry(false, false);
                break;
            case Key.Delete:
                pluginScope.editor.emptySync();
                break;
            default:
                return;
        }

        e.preventDefault();
    }));

    // http://stackoverflow.com/a/17545260/1344955
    $d.on('paste', '[contenteditable]', function (e) {
        let text = '';
        let $this = $(this);

        if (e.clipboardData)
            text = e.clipboardData.getData('text/plain');
        else if (window.clipboardData)
            text = window.clipboardData.getData('Text');
        else if (e.originalEvent.clipboardData)
            text = $.mk('div').text(e.originalEvent.clipboardData.getData('text'));

        if (document.queryCommandSupported('insertText')) {
            document.execCommand('insertHTML', false, $(text).html());
            return false;
        } else {
            $this.find('*').each(function () {
                $(this).addClass('within');
            });

            setTimeout(function () {
                $this.find('*').each(function () {
                    $(this).not('.within').contents().unwrap();
                });
            }, 1);
        }
    }).on('keyup', '[contenteditable]', function () {
        const $this = $(this);
        if ($this.text().trim().length === 0)
            $this.empty();
    });

    const $ist = $('#info-shortcuts-template');
    if (typeof navigator.userAgent === 'string' && /(macos|iphone|os ?x|ip[ao]d)/i.test(navigator.userAgent)) {
        $ist.find('kbd:contains(Shift)').html(`&#x2325;`);
        $ist.find('kbd:contains(Ctrl)').html(`&#x2318;`);
    }
    $('#shortcut-info').on('click', function (e) {
        e.preventDefault();

        const title = $(this).attr('title');
        $.Dialog.info(title, $ist.clone().children());
    });

    window.Plugin = pluginScope;
})(jQuery);
