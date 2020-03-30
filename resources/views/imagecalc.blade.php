<?php
?>
@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('global.imagecalc') }}{!! \App\Util\Core::JSIcon() !!}</h2>
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
            <div class="row">
                <div class="col-12 col-lg">
                    <div class="form-group">
                        <label class="control-label" for="scale-width">{{ __('imagecalc.width') }}</label>
                        <input type="number" class="form-control input-lg" id="scale-width"
                               step="1" min="1" placeholder="1920" required>
                    </div>
                </div>

                <div class="col-auto d-none d-lg-flex align-items-center justify-content-center">
                    <i class="fa fa-2x fa-times"></i>
                </div>

                <div class="col-12 col-lg">
                    <div class="form-group">
                        <label class="control-label" for="scale-height">{{ __('imagecalc.height') }}</label>
                        <input type="number" class="form-control input-lg" id="scale-height"
                               step="1" min="1" placeholder="1080" required>
                    </div>
                </div>
            </div>

            <h3>{{ __('imagecalc.desired') }}&hellip;</h3>

            <div class="form-group">
                <div class="mb-2">
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="scale-target-width" name="scale-target" class="custom-control-input" value="width" required>
                        <label class="custom-control-label" for="scale-target-width">{{ __('imagecalc.width') }}</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="scale-target-height" name="scale-target" class="custom-control-input" value="height" required>
                        <label class="custom-control-label" for="scale-target-height">{{ __('imagecalc.height') }}</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="scale-target-scale" name="scale-target" class="custom-control-input" value="scale" required>
                        <label class="custom-control-label" for="scale-target-scale">{{ __('imagecalc.scale') }}</label>
                    </div>
                </div>

                <input type="number" class="form-control input-lg" id="scale-value"
                       step="any" placeholder="720" required>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('global.calculate') }}</button>
            <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
            <button type="button" class="btn btn-secondary" id="scale-predefined-data">{{ __('global.demo') }}</button>
        </form>

        <div id="scale-output" class="alert alert-success h1 font-weight-bold text-center d-none mt-3 mb-0"></div>
    </div>
    <div id="aspectratio" class="tab-panel d-none">
        <form id="aspectratio-form">
            <div class="row">
                <div class="col-12 col-lg">
                    <div class="form-group">
                        <label class="control-label" for="aspectratio-width">{{ __('imagecalc.width') }}</label>
                        <input type="number" class="form-control input-lg" id="aspectratio-width"
                               step="1" min="1" placeholder="1920" required>
                    </div>
                </div>

                <div class="col-auto d-none d-lg-flex align-items-center justify-content-center">
                    <i class="fa fa-2x fa-times"></i>
                </div>

                <div class="col-12 col-lg">
                    <div class="form-group">
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
            <span class="fa fa-info-circle"></span>
            {{ __('global.tool-not-found') }}
        </div>
    </div>
@endsection

@section('js-locales')
    {{ \App\Util\Core::ExportTranslations('imagecalc',[
    ]) }}
@endsection
