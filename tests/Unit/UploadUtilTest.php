<?php

namespace Tests\Unit;

use App\Util\UploadUtil;
use Tests\TestCase;

class UploadUtilTest extends TestCase
{
    public function test_the_limit_is_what_php_accepts_unless_configured_lower(): void
    {
        config(['app.upload_max_file_kb' => null]);
        $php = min(array_filter([
            (int) ini_get('upload_max_filesize') * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr(ini_get('upload_max_filesize'), -1))] ?? 1),
            (int) ini_get('post_max_size') * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr(ini_get('post_max_size'), -1))] ?? 1),
        ]));
        $this->assertSame($php, UploadUtil::maxFileBytes());

        config(['app.upload_max_file_kb' => 1]);
        $this->assertSame(1024, UploadUtil::maxFileBytes());

        config(['app.upload_max_file_kb' => 999999999]); // can't raise it above PHP's
        $this->assertSame($php, UploadUtil::maxFileBytes());
    }
}
