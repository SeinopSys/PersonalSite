<?php
?>
@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('global.networking') }}<x-js-icon></x-js-icon></h2>
    <p>{{ __('networking.about') }}</p>
    <ul class="nav nav-pills mb-3" id="main-tabs">
        <li class="nav-item"><a class="nav-link" href="#vlsm">VLSM</a></li>
        <li class="nav-item"><a class="nav-link" href="#cidr">CIDR</a></li>
        <li class="nav-item"><a class="nav-link" href="#summary">{{ __('networking.summarization') }}</a></li>
        <li class="nav-item">
            <a href="#prefix-list" class="nav-link">
                {{ __('networking.prefix-list') }}
                <span class="badge bg-primary" title="{{ __('networking.ipv4-only') }}">v4</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="#masktable" class="nav-link">
                {{ __('networking.mask-table') }}
                <span class="badge bg-primary" title="{{ __('networking.ipv4-only') }}">v4</span>
            </a>
        </li>
    </ul>

    <div id="vlsm" class="tab-panel d-none">
        <form id="vlsm-form">
            <div class="js-input-wrap mb-3">
                <?php
                $shortcut_class = __('networking.shortcut-clsss');
                $shortcut_full = __('networking.shortcut-full');
                ?>
                <label class="control-label" for="vlsm-network">{{ __('networking.network') }} &bull; <small>
                        {{ __('networking.shortcuts') }}:
                        A <a href="#" class="ql-acf">{{ $shortcut_class }}/{{ $shortcut_full }}</a>,
                        B <a href="#" class="ql-bc">{{ $shortcut_class }}</a>/<a href="#"
                                                                                 class="ql-bf">{{ $shortcut_full }}</a>,
                        C <a href="#" class="ql-cc">{{ $shortcut_class }}</a>/<a href="#"
                                                                                 class="ql-cf">{{ $shortcut_full }}</a>
                    </small></label>
                <p class="text-danger" id="vlsm-network-alert" style="display:none">
                    <x-fa icon="exclamation-triangle" first></x-fa>
                    <span class="text">{{ __('networking.alert-placeholder') }}</span>
                </p>
                <div class="input-group">
                    <span class="input-group-text ipver-indicator" id="vlsm-ipver">IPv?</span>
                    <input type="text" class="form-control input-lg network-input" id="vlsm-network"
                           pattern="^([\d.]+|[\da-fA-F:]+)/\d+$"
                           placeholder="10.0.0.0/8 {{ __('global.or') }} 2001:db8:85a3::1/64"
                           title="{{ __('networking.network-input-title') }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="control-label" for="vlsm-subnets">{{ __('networking.subnet-list') }}</label>
                <p class="text-danger" id="vlsm-subnets-alert" style="display:none">
                    <x-fa icon="exclamation-triangle" first></x-fa>
                    <span class="text">{{ __('networking.alert-placeholder') }}</span>
                </p>
                <textarea class="form-control" id="vlsm-subnets"
                          placeholder="{!! __('networking.subnets-placeholder') !!}" rows=8 cols=30 required
                          title="{{ __('networking.subnets-title') }}"></textarea>
            </div>

            <p>
                <button type="submit" class="btn btn-primary">{{ __('global.calculate') }}</button>
                <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
                <button type="button" class="btn btn-secondary" id="vlsm-predefined-data">{{ __('global.demo') }}
                    (IPv4)
                </button>
                <button type="button" class="btn btn-secondary" id="vlsm-predefined-data-v6">{{ __('global.demo') }}
                    (IPv6)
                </button>
            </p>
        </form>

        <div id="vlsm-output" class="d-none">
            <ul id="vlsm-output-mode" class="nav nav-pills">
                <li class="nav-item disabled">
                    <a class="nav-link disabled">{{ __('networking.output_nav_label') }}:</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-mode="fancy">{{ __('networking.output_fancy') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-mode="simple">{{ __('networking.output_simple') }}</a>
                </li>
            </ul>

            <div class="table-responsive fancy-only">
                <table class="table table-bordered" id="vlsm-network-output">
                    <thead>
                    <tr>
                        <th colspan="2">{{ __('networking.network') }}</th>
                    </tr>
                    <tr>
                        <th>IP</th>
                        <th>{{ __('networking.mask') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td id="vlsm-ip-dec"></td>
                        <td id="vlsm-mask-dec"></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive fancy-only">
                <table class="table table-bordered" id="vlsm-subnets-output">
                    <thead>
                    <tr>
                        <th colspan="3">{{ __('networking.subnets') }}</th>
                    </tr>
                    <tr>
                        <th>{{ __('networking.name') }}</th>
                        <th>{{ __('networking.network-addr') }}</th>
                        <th>{{ __('networking.mask') }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div id="vlsm-output-simple" class="card bg-light simple-only mt-3"></div>
        </div>
    </div>
    <div id="cidr" class="tab-panel d-none">
        <form id="cidr-form">
            <div class="js-input-wrap mb-3">
                <label class="control-label" for="cidr-network">{{ __('networking.network') }} &bull; <small>
                        {{ __('networking.shortcuts') }}:
                        A <a href="#" class="ql-acf">{{ $shortcut_class }}/{{ $shortcut_full }}</a>,
                        B <a href="#" class="ql-bc">{{ $shortcut_class }}</a>/<a href="#"
                                                                                 class="ql-bf">{{ $shortcut_full }}</a>,
                        C <a href="#" class="ql-cc">{{ $shortcut_class }}</a>/<a href="#"
                                                                                 class="ql-cf">{{ $shortcut_full }}</a>
                    </small></label>
                <p class="text-danger" id="cidr-network-alert" style="display:none">
                    <x-fa icon="exclamation-triangle" first></x-fa>
                    <span class="text">{{ __('networking.alert-placeholder') }}</span>
                </p>
                <div class="input-group">
                    <span class="input-group-text ipver-indicator" id="cidr-ipver">IPv?</span>
                    <input type="text" class="form-control input-lg network-input" id="cidr-network"
                           pattern="^([\d.]+|[\da-fA-F:]+)/\d+$"
                           placeholder="10.0.0.0/8 {{ __('global.or') }} 2001:db8:85a3::1/64"
                           title="{{ __('networking.network-input-title') }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="control-label" for="cidr-subnets">{{ __('networking.desired_subnets') }}</label>
                <p class="text-danger" id="cidr-subnets-alert" style="display:none">
                    <x-fa icon="exclamation-triangle" first></x-fa>
                    <span class="text">{{ __('networking.alert-placeholder') }}</span>
                </p>
                <input type="number" class="form-control input-lg" id="cidr-subnets" placeholder="8" step="1" min="1"
                       required>
            </div>

            <p>
                <button type="submit" class="btn btn-primary">{{ __('global.calculate') }}</button>
                <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
                <button type="button" class="btn btn-secondary" id="cidr-predefined-data">{{ __('global.demo') }}
                    (IPv4)
                </button>
                <button type="button" class="btn btn-secondary" id="cidr-predefined-data-v6">{{ __('global.demo') }}
                    (IPv6)
                </button>
            </p>
        </form>

        <div id="cidr-output" class="card bg-light mt-3"></div>
    </div>
    <div id="summary" class="tab-panel d-none">
        <form id="summary-form">
            <div class="mb-3">
                <label class="control-label" for="summary-networks">{{ __('networking.networks') }} &bull;
                    <small>{{ __('networking.networks-secondary') }}</small></label>
                <p class="text-danger" id="summary-networks-alert" style="display:none">
                    <x-fa icon="exclamation-triangle" first></x-fa>
                    <span class="text">{{ __('networking.alert-placeholder') }}</span>
                </p>
                <textarea class="form-control" id="summary-networks" rows=8 cols=30
                          placeholder="{{ __('networking.networks-placeholder') }}" required
                          title="{{ __('networking.networks-title') }}"></textarea>
            </div>

            <p>
                <button type="submit" class="btn btn-primary">{{ __('global.calculate') }}</button>
                <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
                <button type="button" class="btn btn-secondary" id="summary-predefined-data">{{ __('global.demo') }}
                    (IPv4)
                </button>
                <button type="button" class="btn btn-secondary" id="summary-predefined-data-v6">{{ __('global.demo') }}
                    (IPv6)
                </button>
            </p>
        </form>

        <div id="summary-output" class="card bg-light mt-3"></div>
    </div>
    <div id="prefix-list" class="tab-panel d-none">
        <form id="prefix-list-form">
            <div class="mb-3">
                <label class="control-label"
                       for="prefix-list-show-output">{!! __('networking.show-pl-output') !!}</label>
                <p class="text-danger" id="prefix-list-show-output-alert" style="display:none">
                    <x-fa icon="exclamation-triangle" first></x-fa>
                    <span class="text">{{ __('networking.alert-placeholder') }}</span>
                </p>
                <textarea class="form-control" id="prefix-list-show-output" rows=8 cols=30
                          placeholder="{{ __('networking.show-pl-output-placeholder') }}" required></textarea>
            </div>
            <div class="mb-3">
                <label class="control-label" for="prefix-list-network">{{ __('networking.network-to-check') }}</label>
                <p class="text-danger" id="prefix-list-network-alert" style="display:none">
                    <x-fa icon="exclamation-triangle" first></x-fa>
                    <span class="text">{{ __('networking.alert-placeholder') }}</span>
                </p>
                <input type="text" class="form-control input-lg network-input" id="prefix-list-network"
                       pattern="^([\d.]+)/\d+$" placeholder="10.5.0.0/12"
                       title="{{ __('networking.network-input-title') }}" required>
            </div>

            <p>
                <button type="submit" class="btn btn-primary">{{ __('networking.detect-match') }}</button>
                <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
                <button type="button" class="btn btn-secondary"
                        id="prefix-list-predefined-data">{{ __('global.demo') }}</button>
            </p>
        </form>

        <div id="prefix-list-output"></div>
    </div>
    <div id="masktable" class="tab-panel d-none">
        <table class="table table-bordered mb-0">
            <thead>
            <tr>
                <th>{{ __('networking.slash-format') }}</th>
                <th>{{ __('networking.dotted-decimal-format') }}</th>
                <th>{{ __('networking.reverse-decimal-format') }}</th>
            </tr>
            </thead>
            <tbody id="masktable-tbody"></tbody>
        </table>
    </div>
    <div class="tab-panel not-found d-none">
        <div class="alert alert-info mb-0">
            <x-fa icon="info-circle" first></x-fa>
            {{ __('global.tool-not-found') }}
        </div>
    </div>
@endsection

@section('js-locales')
    {{ \App\Util\Core::ExportTranslations('networking',[
        'network',
        'subnets',
        'error_ipv4_only',
        'error_ipv6_only',
        'vlsm_error_ipadd_format_invalid',
        'vlsm_error_ipadd_octet_invalid',
        'vlsm_error_ipadd_octet_invalid_nan',
        'vlsm_error_ipadd_octet_invalid_range',
        'vlsm_error_ipv6add_short_invalid',
        'vlsm_error_ipv6add_block_invalid',
        'vlsm_error_ipv6add_block_invalid_nan',
        'vlsm_error_ipv6add_block_invalid_range',
        'vlsm_error_ipv6add_too_many_blocks',
        'vlsm_error_mask_length_invalid',
        'vlsm_error_mask_length_overflow',
        'vlsm_error_subnet_line_invalid',
        'vlsm_error_subnet_line_invalid_format',
        'vlsm_error_subnet_line_invalid_count',
        'vlsm_subnet_tostring',
        'vlsm_network_too_small',
        'vlsm_minimum',
        'vlsm_counted',
        'vlsm_info_line',
        'vlsm_mask_reverse',
        'cidr_error_subnet_line_invalid_format',
        'summary_error_mixed_ip_versions',
        'summary_not_enough_addresses',
        'summary_uncommon',
        'prefix_list_error_show_invalid',
        'prefix_list_error_seq_invalid',
        'prefix_list_error_seq_number_invalid',
        'prefix_list_error_gtlen',
        'prefix_list_error_ge_le',
        'prefix_list_nomatch',
        'prefix_list_match',
    ]) }}
@endsection
