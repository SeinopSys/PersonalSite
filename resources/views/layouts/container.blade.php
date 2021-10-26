@extends('layouts.app')

@section('content')
  @include('layouts.navbar')
  @hasSection('panel-before')
    <div class="pt-2">
      @yield('panel-before')
    </div>
  @endif
  <div class="py-4 @if(!empty($bg)) bg-{{$bg}} @endif">
    <div class="container">
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
