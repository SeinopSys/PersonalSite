<nav class="navbar navbar-expand-lg fixed-top navbar-light bg-light shadow">
    <div class="container">
        @if(Request::path() !== '/')
            <a class="navbar-brand" href="{{ url('/') }}" title="{{ __('global.home') }}"></a>
        @else
            <a class="navbar-brand">{{ __('global.greeting') }}</a>
        @endif

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#app-navbar-collapse"
                aria-controls="app-navbar-collapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="app-navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav mr-auto">
                {!! \App\Util\Core::NavbarItem('/', __('global.about')) !!}
                @if(Auth::check())
                    {!! \App\Util\Core::NavbarItem('dashboard') !!}
                    {!! \App\Util\Core::NavbarItem('uploads') !!}
                @endif
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="tooldsdd" role="button" data-toggle="dropdown"
                       aria-haspopup="true" aria-expanded="false">
                        {{ __('global.tools') }}
                    </a>
                    <div class="dropdown-menu" aria-labelledby="tooldsdd">
                        {!! \App\Util\Core::NavbarItem('lrc', null, 'a') !!}
                        {!! \App\Util\Core::NavbarItem('networking', null, 'a') !!}
                        {!! \App\Util\Core::NavbarItem('imagecalc', null, 'a') !!}
                        {!! \App\Util\Core::NavbarItem('selfsigned', null, 'a') !!}
                        {!! \App\Util\Core::NavbarItem('netsalary', null, 'a') !!}
                        {!! \App\Util\Core::NavbarItem('inliner', null, 'a') !!}
                    </div>
                </li>
            </ul>

            <ul class="nav navbar-nav navbar-right">
                <li class="nav-item dropdown">
                    @if(isset($lang_forced)){
                    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-globe-americas"></i>
                        {{ Config::get('languages')[App::getLocale()] }}
                        ({{ __('global.language-forced') }})
                    </a>
                    @else
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                            <i class="fa fa-globe-americas"></i>
                            {{ Config::get('languages')[App::getLocale()] }}
                        </a>
                        <ul class="dropdown-menu language-selector" role="menu">
                            @php
                                $currLang = Lang::getLocale();
                            @endphp
                            @foreach(Config::get('languages') as $lang => $language)
                                <a class="dropdown-item{{ $lang === $currLang ? ' current disabled' : ''}}"
                                   @if($lang !== $currLang) href="{{ route('lang.switch', $lang)}}"
                                   @else data-current="{{__('global.current')}}"
                                    @endif
                                >
                                    <img src="/img/lang/{{$lang}}.svg" class="language-flag" alt="{{$lang}} flag">
                                    <span>{{$language}}</span>
                                </a>
                            @endforeach
                        </ul>
                    @endif
                </li>
                @if(Auth::guest())
                    @if(User::count())
                        <li class="nav-item">
                            <a href="{{ url('/login') }}" class="nav-link">{{ __('auth.login') }}</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ url('/register') }}" class="nav-link">{{ __('auth.register') }}</a>
                        </li>
                    @endif
                @else
                    <li class="nav-item dropdown user-dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" role="button"
                           aria-expanded="false">
                            <img src="{!! Auth::user()->getGravatar(20) !!}" class="gravatar" alt="user avatar">
                            {{ Auth::user()->name }}
                        </a>
                        <div class="dropdown-menu" role="menu">
                            <a class="dropdown-item" id="logout-link">
                                <span class="fa fa-sign-out-alt"></span> {{ __('auth.logout') }}
                            </a>
                        </div>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</nav>
