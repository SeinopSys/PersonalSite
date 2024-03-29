@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('global.netsalary') }}<x-js-icon></x-js-icon></h2>

    <p>{{ __('netsalary.about') }}</p>

    <form id="net-salary-calc" class="row gy-2 gx-3 align-items-end">
        <div class="col">
            <label for="gross-salary" class="me-2">{{ __('netsalary.gross_salary') }}</label>
            <div class="input-group">
                <input type="number" class="form-control" id="gross-salary" step="1" max="99999999" min="0">
                <span class="input-group-text">Ft</span>
            </div>
        </div>

        <div class="col-auto">
            <div class="btn-group">
            <button type="submit" class="btn btn-primary me-sm-2">{{ __('global.calculate') }}</button>
            <button type="reset" class="btn btn-danger me-sm-2">{{ __('global.clear') }}</button>
            <button type="button" class="btn btn-secondary me-sm-2" id="min-wage-btn">{{ __('global.demo') }}</button>
            </div>
        </div>
    </form>

    <div id="net-output-wrap" class="table-responsive d-none mt-3">
        <table id="net-output" class="table table-bordered">
            <thead>
            <tr>
                <th>{{ __('netsalary.deuction_name') }}</th>
                <th>{{ __('netsalary.percent_amount') }}</th>
                <th>{{ __('netsalary.calc_amount') }}</th>
            </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
            <tr class="table-danger">
                <th>{{ __('netsalary.total_deduct') }}</th>
                <td id="total-deductions-perc"></td>
                <td id="total-deductions"></td>
            </tr>
            <tr class="table-success">
                <th>{{ __('netsalary.net_salary') }}</th>
                <td id="net-salary-perc"></td>
                <td id="net-salary"></td>
            </tr>
            </tfoot>
        </table>
    </div>
@endsection

@section('js-locales')
    {!! \App\Util\Core::ExportTranslations('netsalary',[
        'deductions',
    ]) !!}
@endsection
