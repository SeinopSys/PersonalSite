@extends('layouts.app')

@section('content')
    @include('layouts.navbar')
    <div class="py-4 @if(!empty($bg)) bg-{{$bg}} @endif">
        <div class="container">
            @php
                $ts = 1638230400; // 2021-11-30T00:00:00Z
                $now = time();
            @endphp
            @if($now < $ts)
                <aside class="alert alert-info alert-dismissable fade in" id="hu-sunset-notice" hidden>
                    <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('global.dismiss') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    {!! __('global.hu_sunset', ['time' => \App\Util\Time::Tag($ts)]) !!}
                </aside>
            @endif

            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card">
                        @hasSection('panel-heading')
                            <div class="card-header">
                                @yield('panel-heading')
                            </div>
                        @endif
                        <div class="card-body">
                            @yield('panel-body')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
