<?php

$title = __('global.lrc'); ?>
@extends('layouts.container')

@section('panel-body')
    <h3 class="text-center">
        {!! __('lrc.title', ['lrc' => '<a href="https://en.wikipedia.org/wiki/LRC_(file_format)">LRC</a>']) !!}
        <x-js-icon></x-js-icon>
        <button class="btn btn-link" title="{{ __('lrc.dialog_shortcut_info') }}" id="shortcut-info">
            <x-fa icon="info-circle"></x-fa>
        </button>
    </h3>

    <fieldset>
        <legend>{{ __('lrc.audioplayer') }}</legend>
        <div id="player">
            <div class="controls">
                <div class="btn-group">
                    <button class="btn btn-primary" id="audiofilebtn" title="{{ __('lrc.player_selectfile') }}">
                        <x-fa icon="upload"></x-fa>
                    </button>
                    <button class="btn btn-secondary" id="playbackbtn" title="{{ __('lrc.player_playpause') }}" disabled>
                        <x-fa icon="play"></x-fa>
                    </button>
                    <button class="btn btn-secondary" id="stopbtn" title="{{ __('lrc.player_stop') }}" disabled>
                        <x-fa icon="stop"></x-fa>
                    </button>
                    <span class="input-group-text volume">
                        <button class="btn btn-secondary btn-sm" id="volumedown" title="{{ __('lrc.player_voldec') }}">
                            <x-fa icon="volume-down" size="lg"></x-fa>
                        </button>
                        <span id="volumedisp" class="mx-2">&hellip;</span>
                        <button class="btn btn-secondary btn-sm" id="volumeup" title="{{ __('lrc.player_volinc') }}">
                            <x-fa icon="volume-up" size="lg"></x-fa>
                        </button>
					</span>
                </div>
            </div>
            <div class="state">
                <div class="progress-wrap" title="{{ __('lrc.player_seek') }}">
                    <div class="progress-indicator">
                        <div class="fill">&nbsp;</div>
                        <div class="loaded"></div>
                        <div class="entry-sticks"></div>
                    </div>
                    <div class="thumb"></div>
                </div>
                <div class="status">
					<span class="status-time">
						<span class="status-position">&hellip;</span> / <span class="status-duration">&hellip;</span>
					</span>
                    <span class="status-filetype"></span>
                    <span class="status-filename" data-nofile="{{ __('lrc.player_nofile') }}"></span>
                </div>
            </div>
        </div>
    </fieldset>

    <br>

    <fieldset>
        <legend>{{ __('lrc.timingeditor') }}</legend>
        <div id="timings">
            <div class="controls">
                <div class="btn-group w-100" role="group">
                    <button class="btn btn-primary" id="lrcmodebtn"
                            data-syncmode="{{ __('lrc.player_switch_syncmode') }}"
                            data-editmode="{{ __('lrc.player_switch_editmode') }}" disabled>
                        <span class="fa"></span> <span class="modename">&hellip;</span>
                    </button>
                    <div class="btn-group" role="group">
                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <x-fa icon="file-import" first></x-fa>{{ __('global.import') }}
                            <span class="caret"></span>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#import-lrc" id="lrcfilebtn">
                                <strong><x-fa icon="file-upload" first></x-fa>{{ __('lrc.timing_import') }}</strong>
                            </a>
                            <a class="dropdown-item" href="#paste-lyrics" id="lrcpastebtn">
                                <x-fa icon="clipboard" first></x-fa>{{ __('lrc.timing_pastelyrics') }}
                            </a>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <x-fa icon="file-export" first></x-fa>{{ __('global.export') }}
                            <span class="caret"></span>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#import-lrc" id="lrcexportbtn">
                                <strong><span
                                        class="fa fa-file-code"></span> {{ __('lrc.timing_export').' '.__('lrc.timing_exportwithmeta') }}
                                </strong>
                            </a>
                            <a class="dropdown-item" href="#paste-lyrics" id="lrcexportnometabtn">
                                <span
                                    class="fa fa-file"></span> {{ __('lrc.timing_export').' '.__('lrc.timing_exportnometa') }}
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#toggle-merge" id="lrcmergetogglebtn">
                                {{ __('lrc.toggle_merge') }} <strong class="status text-uppercase"
                                                                     data-true="{{ __('global.on') }}"
                                                                     data-false="{{ __('global.off') }}">&hellip;</strong><br>
                                <small class="help-block">{{ __('lrc.merge_explainer') }}</small>
                            </a>
                        </div>
                    </div>
                    <button class="btn btn-secondary" id="lrcmetadatabtn">
                        <x-fa icon="compact-disc" first></x-fa>{{ __('lrc.timing_metadata') }}
                    </button>
                    <button class="btn btn-danger" id="lrcclrbtn">
                        <x-fa icon="eraser" first></x-fa>{{ __('lrc.timing_wipe') }}
                    </button>
                </div>
            </div>
            <div class="editor-wrap">
                <div class="editor"></div>
                <div class="alert alert-info editor-no-scroll"><span
                        class="fa fa-info-circle"></span> {{ __('lrc.editor_scroll_lock') }}</div>
            </div>
        </div>
    </fieldset>

    <div id="editor-entry-template" class="d-none">
        <div class="time-entry">
            <span class="timestamp" contenteditable data-empty="0:00.000"></span>
            <span class="text" contenteditable data-empty="{{ __('lrc.timing_entry_empty') }}"></span>
            <div class="tools btn-group">
                <button class="btn btn-success addrow-up edit-only text-nowrap" title="{{ __('lrc.timing_addrowup') }}">
                    <x-fa icon="plus" first></x-fa><x-fa icon="arrow-up"></x-fa>
                </button>
                <button class="btn btn-success addrow-down edit-only text-nowrap"
                        title="{{ __('lrc.timing_addrowdown') }}">
                    <x-fa icon="plus" first></x-fa><span class="fa fa-arrow-down"></span>
                </button>
                <button class="btn btn-danger remrow edit-only text-nowrap" title="{{ __('lrc.timing_remrow') }}" disabled>
                    <x-fa icon="minus"></x-fa>
                </button>
                <button class="btn btn-warning goto text-nowrap" title="{{ __('lrc.timing_goto') }}" disabled>
                    <x-fa icon="step-forward"></x-fa>
                </button>
            </div>
        </div>
    </div>

    <div id="info-shortcuts-template" class="d-none">
        <p>{{ __('lrc.howto.opening.0') }}</p>
        <p>{!! __('lrc.howto.opening.1', [
			'lrcff' => '<a href="'.__('lrc.fileformat_url').'">'.__('lrc.fileformat').'</a>'
		]) !!}</p>

        <h3></h3>
        <ul>
            <li>{!! __('lrc.howto.limitations.0', ['t' => '<code>&lt;auido&gt;</code>']) !!}</li>
            <li>{!! __('lrc.howto.limitations.1', [
				'dur' => '<code>&lt;00:10.48></code>',
				'app' => '<a href="http://www.autolyric.com/" rel="nofollow">AutoLyric</a>',
			]) !!}</li>
        </ul>

        <h3>{{ __('lrc.howto.ui.title') }}</h3>
        <p>{{ __('lrc.howto.ui.0') }}</p>
        <p>{!! __('lrc.howto.ui.1', [
			'player' => '<strong>'.__('lrc.player').'</strong>'
		]) !!}</p>
        <p>{!! __('lrc.howto.ui.2', [
			'editor' => '<strong>'.__('lrc.editor').'</strong>'
		]) !!}</p>
        <p>{!! __('lrc.howto.ui.3', [
			'tm' => '<strong>'.__('lrc.twomodes').'</strong>',
			'em' => '<strong>'.strtolower(__('lrc.player_editmode')).'</strong>',
			'enter' => '<kbd>&#x23ce;&nbsp;'.__('kbd.enter').'</kbd>',
			'shift' => '<kbd>&#x21E7;&nbsp;'.__('kbd.shift').'</kbd>',
		]) !!}</p>
        <p>{!! __('lrc.howto.ui.4', [
			'sm' => '<strong>'.strtolower(__('lrc.player_syncmode')).'</strong>',
			'alt' => '<kbd>'.__('kbd.alt').'</kbd>',
			'enter' => '<kbd>&#x23ce;&nbsp;'.__('kbd.enter').'</kbd>'
		]) !!}</p>
        <p>{{ __('lrc.howto.ui.5') }}</p>
        <p>{!! __('lrc.howto.ui.6', [
			'tsf' => '<code>'.__('lrc.tsformat').'</code>',
			'ex' => '<code>0:01</code>',
		]) !!}</p>

        <h3>{{ __('lrc.howto.shortcuts.title') }}</h3>
        <h4>{{ __('lrc.howto.shortcuts.player.title') }}</h4>
        <ul>
            <li><kbd>&nbsp;{{ __('kbd.space') }}&nbsp;</kbd>&#x2003;{{ __('lrc.player_playpause') }}</li>
            <li><kbd>.</kbd>&#x2003;{{ __('lrc.player_stop') }}</li>
            <li>
                <kbd>{{ __('kbd.pgup') }}</kbd> / <kbd>{{ __('kbd.pgdn') }}</kbd>&#x2003;{{ __('lrc.player_volinc') }}
                / {{ __('lrc.player_voldec') }}<br>{!! __('lrc.howto.shortcuts.player.volstep', [
					'in' => '<strong>5%</strong>',
					'shift' => '<kbd>&#x21E7;&nbsp;'.__('kbd.shift').'</kbd>',
					'alt' => '<kbd>'.__('kbd.alt').'</kbd>',
				]) !!}</li>
            <li>
                <kbd>&#x1f814;</kbd> / <kbd>&#x1f816;</kbd>&#x2003;{{ __('lrc.player_seek') }} <br>{!! __('lrc.howto.shortcuts.player.seekstep', [
					'shift' => '<kbd>&#x21E7;&nbsp;'.__('kbd.shift').'</kbd>',
				]) !!}
            </li>
        </ul>
        <h4>{{ __('lrc.howto.shortcuts.editor') }}</h4>
        <ul>
            <li><kbd>&#x23ce;&nbsp;{{ __('kbd.enter') }}</kbd>&#x2003;{{ __('lrc.timing_sync') }}</li>
            <li><kbd>{{ __('kbd.ctrl') }}</kbd>
                <kbd>&#x23ce;&nbsp;{{ __('kbd.enter') }}</kbd>&#x2003;{{ __('lrc.timing_sync_break') }}</li>
            <li><kbd>&#x1f815;</kbd> / <kbd>&#x1f817;</kbd>&#x2003;{{ __('lrc.timing_sync_jump') }}</li>
            <li><kbd>{{ __('kbd.delete') }}</kbd>&#x2003;{{ __('lrc.timing_sync_remove') }}</li>
        </ul>
    </div>
@endsection

@section('js-locales')
    {!! \App\Util\Core::ExportTranslations('lrc',[
        'dialog_pasteraw_title',
        'dialog_pasteraw_info',
        'dialog_pasteraw_action',
        'dialog_parse_error',
        'dialog_parse_error_empty',
        'dialog_format_error',
        'dialog_format_notaudio',
        'dialog_edit_meta',
        'metadata_field_placeholders',
        'dialog_edit_meta_reset_info',
        'dialog_edit_meta_reset_btn',
    ]) !!}
    {!! \App\Util\Core::ExportTranslations('global',[
        'save',
    ]) !!}
@endsection
