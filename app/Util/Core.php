<?php

namespace App\Util;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Request;

class Core
{
    const
        FIXPATH_EMPTY = '#',
        FIXPATH_PERM = 301,
        FIXPATH_TEMP = 302;

    const COMMIT_INFO_REDIS_KEY = 'psite_commit_info';

    /**
     * Forces an URL rewrite to the specified path
     *
     * @param  string  $fix_uri  URL to forcibly redirect to
     * @param  int  $http  HTPP status code for the redirect
     */
    public static function FixPath(string $fix_uri, int $http = self::FIXPATH_TEMP)
    {
        $_split = explode('?', $_SERVER['REQUEST_URI'], 2);
        $path = $_split[0];
        $query = empty($_split[1]) ? '' : "?{$_split[1]}";

        $_split = explode('?', $fix_uri, 2);
        $fix_path = $_split[0];
        $fix_query = empty($_split[1]) ? '' : "?{$_split[1]}";

        if (empty($fix_query)) {
            $fix_query = $query;
        } else {
            $query_assoc = self::QueryStringAssoc($query);
            $fix_query_assoc = self::QueryStringAssoc($fix_query);
            $merged = $query_assoc;
            foreach ($fix_query_assoc as $key => $item) {
                $merged[$key] = $item;
            }
            $fix_query_arr = [];
            foreach ($merged as $key => $item) {
                if (!isset($item) || $item !== self::FIXPATH_EMPTY) {
                    $fix_query_arr[] = $key.(!empty($item) ? '='.urlencode($item) : '');
                }
            }
            $fix_query = empty($fix_query_arr) ? '' : '?'.implode('&', $fix_query_arr);
        }
        if ($path !== $fix_path || $query !== $fix_query) {
            header("Location: $fix_path$fix_query", $http);
        }
    }

    /**
     * Turn query string into an associative array
     *
     * @param  string  $query
     *
     * @return array
     */
    public static function QueryStringAssoc($query)
    {
        $assoc = [];
        if (!empty($query)) {
            parse_str(ltrim($query, '?'), $assoc);
        }
        return $assoc;
    }

    // http://stackoverflow.com/a/11562766/1344955
    public static function GetAge(int $bdtime): int
    {
        return floor((time() - $bdtime) / 31556926);
    }

    public static function AssetURL(string $name, string $type): string
    {
        $pathStart = public_path("$type/");
        switch ($type) {
            case 'css':
                $fpath = "$name.css";
                $templ = ["<link rel='stylesheet' href='", "'>"];
                break;
            case 'js':
                $fpath = "$name.js";
                $templ = ["<script src='", "'></script>"];
                break;
        }
        if (file_exists("$pathStart$fpath")) {
            return "{$templ[0]}/$type/$fpath?".filemtime("$pathStart$fpath").$templ[1]."\n";
        }
        return "<!-- Missing resource ignored: /$type/$fpath -->\n";
    }

    /**
     * Truncates a filename to a specified number of characters, preserving the file extension
     *
     * @param $string
     * @param  int  $length
     *
     * @return string
     */
    public static function TruncateFilename(string $string, int $length = 12): string
    {
        return preg_replace('~^(.{'.($length - 1).'}).*(\.[a-z]+)$~', '$1…$2', $string);
    }

    /**
     * Converts bytes ot a human-readable file size string
     * http://stackoverflow.com/a/23888858/1344955
     *
     * @param  int  $bytes
     * @param  int  $dec
     *
     * @return string
     */
    public static function ReadableFilesize(int $bytes, int $dec = 2): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = (int) floor((\strlen($bytes) - 1) / 3);

        $n = $bytes / (1024 ** $factor);
        return ($n > 0 ? rtrim(number_format($n, $dec, '.', ''), '0.') : 0).' '.$size[$factor];
    }

    public static function ExportTranslations(string $namespace, array $translation)
    {
        $out = [];
        foreach ($translation as $name) {
            $out[$name] = __("$namespace.$name");
        }
        $json = JSON::Encode($out);
        /** @noinspection BadExpressionStatementJS */
        echo "<script>$.extend(window.Laravel.jsLocales, $json)</script>";
    }

    public static function NavbarItem($ident, $text = null, $tag = 'li')
    {
        $active = Request::path() === $ident ? ' active' : '';
        $href = url('/'.ltrim("/$ident", '/'));
        if ($text === null) {
            $text = __("global.$ident");
        }
        switch ($tag) {
            case 'li':
                return "<li class='nav-item$active'><a href='$href' class='nav-link'>$text</a></li>";
            case 'a':
                return "<a href='$href' class='dropdown-item$active'>$text</a>";
            default:
                throw new \RuntimeException(__METHOD__.": Unhandled \$tag $tag");
        }
    }

    public static function JSIcon()
    {
        return "<span class='js-required' title='".__('global.js_required')."'></span>";
    }

    /**
     * Returns the HTML of the GIT information in the website's footer
     *
     * @return array
     */
    public static function GetFooterGitInfo(): array
    {
        $key = self::COMMIT_INFO_REDIS_KEY;
        $commit_info = Redis::get($key);
        if ($commit_info === null) {
            $commit_info = rtrim(shell_exec('git log -1 --date=short  --pretty="format:%h;%ci"'));
            Redis::set($key, $commit_info, 'EX', 3600);
        }
        $data = [];
        if (!empty($commit_info)) {
            [$commit_id, $commit_time] = explode(';', $commit_info);
            $data['commit_id'] = $commit_id;
            $data['commit_time'] = Time::Tag($commit_time);
        }

        return $data;
    }

    public static function getDomain(bool $secondary_domain): string
    {
        return $secondary_domain ? config('app.secondary_domain') : config('app.primary_domain');
    }
}
