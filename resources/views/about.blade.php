<?php
/** @var $email string */
use App\Util\Core; ?>
@extends('layouts.app')

@section('content')
  @include('layouts.navbar')
  <div id="header" class="start-at-top">
    <div class=logo></div>
  </div>

  <div id="content">
    <div class="essentials pb-4">
      <div class="avatar-wrap">
        <img src="https://gravatar.com/avatar/{{ md5($email) }}?s=200&amp;r=g" alt="{{ config('app.name') }}">
      </div>
      <h1>{{ App::isLocale('hu') ? 'Guzsik Dávid József' : 'David Joseph Guzsik' }}</h1>
      <div class="detail">
                <span class="age" data-append='<?=__('about.yearsold')?>'><time
                    datetime="<?=$BDTimeStr = '1998-10-28T00:00+01:00'?>" class="nodt"
                    id="age"
                  ><?=Core::GetAge(strtotime($BDTimeStr))?></time></span>
        <span
          class="gender"
          data-<?=App::isLocale('hu') ? 'append' : 'prepend'?>='<?=__('about.gender')?>'
        ><?=__('about.male')?></span>
        <span class="loc" data-prepend='<?=__('about.loc')?>'>
				<a target="_blank" href="https://www.google.com/maps/place/<?=$HU = __('about.hungary')?>/"><?=$HU?></a>
			</span>
        <br>
        <span class="localtime" data-prepend='<?=__('about.localtime')?>'><span id="localtime"><?php
            $parts = explode(':', date(App::isLocale('en') ? 'g:i A' : 'G:i'));
            echo "<span class='start'>{$parts[0]}</span><span class='tick'>:</span><span class='end'>{$parts[1]}</span>";
            ?></span></span>
      </div>
    </div>
    <div class="social">
      <h2>{{ __('about.contactproj') }}</h2>
      <div class="tiles">
        <a class="gh" href="https://github.com/SeinopSys">
          <span>GitHub</span>
        </a>
        <a class="so" href="https://stackoverflow.com/users/1344955/seinopsys">
          <span>Stack Overflow</span>
        </a>
        <a class="sei" href="{{ config('app.github_url') }}">
          <span>{{ __('about.this_site') }}</span>
        </a>
        <a class="vc" href="https://mlpvector.club/">
          <span>MLP Vector Club {{ __('about.website') }}</span>
        </a>
        <a class="lr" href="https://github.com/SeinopSys/LightningReopen">
          <span>{{ __('about.lightning_reopen') }}</span>
        </a>
        <a class="dt" href="https://github.com/SeinopSys/Derpi-NewTab">
          <span>Derpi-New Tab</span>
        </a>
        <a class="ytms" href="https://github.com/SeinopSys/YTMySubs">
          <span>{{ __('about.yt_my_subs') }}</span>
        </a>
        <a class="pm" href="mailto:{{ $email }}">
          <span>{{ __('about.sendmail') }}</span>
        </a>
      </div>
      <p>{{ __('about.copynotice') }}</p>
    </div>
  </div>
@endsection
