<?php
?>
@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('global.imagecalc') }}<x-js-icon></x-js-icon></h2>
    <p>{{ __('imagecalc.about') }}</p>
    <ul class="nav nav-pills mb-3" id="main-tabs">
        <li class="nav-item">
            <a class="nav-link" href="#scale">{{ __('imagecalc.scale_tab') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#aspectratio">{{ __('imagecalc.aspect_ratio') }}</a>
        </li>
    </ul>

    <div id="scale" class="tab-panel d-none">
        <form id="scale-form">
            <h3>{{ __('imagecalc.orig_resolution') }}</h3>
            <div class="row mb-3">
                <div class="col-12 col-lg-auto flex-grow-1">
                    <div class="mb-3">
                        <label class="control-label" for="scale-width">{{ __('imagecalc.width') }}</label>
                        <input type="number" class="form-control input-lg" id="scale-width"
                               step="1" min="1" placeholder="1920" required>
                    </div>
                </div>

                <div class="col flex-grow-0 d-none d-lg-flex align-items-center justify-content-center">
                    <x-fa icon="times" size="2x"></x-fa>
                </div>

                <div class="col-12 col-lg-auto flex-grow-1">
                    <div class="mb-3">
                        <label class="control-label" for="scale-height">{{ __('imagecalc.height') }}</label>
                        <input type="number" class="form-control input-lg" id="scale-height"
                               step="1" min="1" placeholder="1080" required>
                    </div>
                </div>
            </div>

            <h3>{{ __('imagecalc.desired') }}&hellip;</h3>

            <div class="mb-3">
                <div class="mb-2">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="scale-target-width" name="scale-target" value="width" required>
                        <label class="form-check-label" for="scale-target-width">{{ __('imagecalc.width') }}</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="scale-target-height" name="scale-target" value="height" required>
                        <label class="form-check-label" for="scale-target-height">{{ __('imagecalc.height') }}</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="scale-target-scale" name="scale-target" value="scale" required>
                        <label class="form-check-label" for="scale-target-scale">{{ __('imagecalc.scale') }}</label>
                    </div>
                </div>

                <input type="number" class="form-control input-lg" id="scale-value"
                       step="any" placeholder="720" required>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('global.calculate') }}</button>
            <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
            <button type="button" class="btn btn-secondary" id="scale-predefined-data">{{ __('global.demo') }}</button>
        </form>

        <div id="scale-output" class="alert alert-success h1 fw-bold text-center d-none mt-3 mb-0"></div>
    </div>
    <div id="aspectratio" class="tab-panel d-none">
        <form id="aspectratio-form">
            <div class="row mb-3">
                <div class="col-12 col-lg-auto flex-grow-1">
                    <div class="mb-3">
                        <label class="control-label" for="aspectratio-width">{{ __('imagecalc.width') }}</label>
                        <input type="number" class="form-control input-lg" id="aspectratio-width"
                               step="1" min="1" placeholder="1920" required>
                    </div>
                </div>

                <div class="col flex-grow-0 d-none d-lg-flex align-items-center justify-content-center">
                    <x-fa icon="times" size="2x"></x-fa>
                </div>

                <div class="col-12 col-lg-auto flex-grow-1">
                    <div class="mb-3">
                        <label class="control-label" for="aspectratio-height">{{ __('imagecalc.height') }}</label>
                        <input type="number" class="form-control input-lg" id="aspectratio-height"
                               step="1" min="1" placeholder="1080" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('global.calculate') }}</button>
            <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
            <button type="button" class="btn btn-secondary" id="aspectratio-predefined-data">{{ __('global.demo') }}</button>
        </form>

        <div id="aspectratio-output" class="alert alert-success text-center d-none mt-3 mb-0"></div>
    </div>
    <div class="tab-panel not-found d-none">
        <div class="alert alert-info mb-0">
            <x-fa icon="info-circle" first></x-fa>
            {{ __('global.tool-not-found') }}
        </div>
    </div>
@endsection

