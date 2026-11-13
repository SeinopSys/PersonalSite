<?php

namespace Tests\Unit;

use App\Http\Middleware\AddContentSecurityPolicy;
use App\Util\ContentSecurityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class ContentSecurityPolicyTest extends TestCase
{
    private static function hash(string $script): string
    {
        return "'sha256-" . base64_encode(hash('sha256', $script, true)) . "'";
    }

    public function test_hashes_executable_inline_scripts_exactly_as_they_appear(): void
    {
        $theme = "\n    (function () { document.documentElement.setAttribute('data-bs-theme', 'dark'); })();\n  ";
        $laravel = 'window.Laravel = JSON.parse("{\"csrfToken\":\"abc\"}");';
        $html = "<html><head><script>$theme</script><script type=\"text/javascript\">$laravel</script></head></html>";

        $this->assertSame([self::hash($theme), self::hash($laravel)], ContentSecurityPolicy::inlineScriptHashes($html));
    }

    public function test_leaves_out_external_scripts_data_blocks_empty_scripts_and_duplicates(): void
    {
        $html = '<script src="https://cdnjs.cloudflare.com/x.js" integrity="sha512-abc" crossorigin="anonymous"></script>'
            . '<script type="application/json">{"a":1}</script>'
            . '<script type="application/ld+json">{"@type":"Person"}</script>'
            . '<script>   </script>'
            . '<script>var a=1;</script><script>var a=1;</script>';

        $this->assertSame([self::hash('var a=1;')], ContentSecurityPolicy::inlineScriptHashes($html));
        $this->assertSame([], ContentSecurityPolicy::inlineScriptHashes('<html><body>hi</body></html>'));
    }

    public function test_policy_lists_only_known_sources(): void
    {
        $policy = ContentSecurityPolicy::build([self::hash('a')], 'https://csp.example/r');

        $this->assertStringContainsString("script-src 'self' https://cdnjs.cloudflare.com 'wasm-unsafe-eval' " . self::hash('a') . ';', $policy);
        $this->assertStringContainsString("script-src-attr 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('https://api.friendlycaptcha.com', $policy);
        // every host a Gravatar URL is built with (the navbar avatar uses s., the about page the bare domain)
        foreach (['https://gravatar.com', 'https://www.gravatar.com', 'https://s.gravatar.com'] as $host) {
            $this->assertMatchesRegularExpression('/img-src [^;]*' . preg_quote($host, '/') . '(?: |;|$)/', $policy);
        }
        $this->assertStringContainsString('report-uri https://csp.example/r; report-to csp-endpoint', $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertSame('csp-endpoint="https://csp.example/r"', ContentSecurityPolicy::reportingEndpoints('https://csp.example/r'));
    }

    private static function through(BaseResponse $response, string $reportUri = 'https://csp.example/r'): BaseResponse
    {
        config(['csp.report_uri' => $reportUri]);

        return (new AddContentSecurityPolicy())->handle(Request::create('/'), fn () => $response);
    }

    public function test_middleware_adds_the_report_only_policy_computed_from_the_final_body(): void
    {
        $script = 'window.Laravel = JSON.parse("{\"csrfToken\":\"per-request\"}");';
        $result = self::through(new Response("<html><script>$script</script></html>", 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        $this->assertStringContainsString(self::hash($script), $result->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertSame('csp-endpoint="https://csp.example/r"', $result->headers->get('Reporting-Endpoints'));
        $this->assertFalse($result->headers->has('Content-Security-Policy'), 'must never be enforced');
    }

    /** @return array<string, array{BaseResponse, string}> */
    public static function untouchedResponses(): array
    {
        $html = ['Content-Type' => 'text/html'];

        return [
            'no endpoint configured' => [new Response('<html></html>', 200, $html), ''],
            'JSON (the API)' => [new JsonResponse(['ok' => true]), 'https://csp.example/r'],
            'redirect' => [new Response('', 302, $html), 'https://csp.example/r'],
            'not modified' => [new Response('', 304, $html), 'https://csp.example/r'],
            'page with its own policy (share pages)' => [new Response('<html></html>', 200, $html + ['Content-Security-Policy' => "default-src 'none'"]), 'https://csp.example/r'],
        ];
    }

    #[DataProvider('untouchedResponses')]
    public function test_middleware_leaves_other_responses_alone(BaseResponse $response, string $reportUri): void
    {
        $own = $response->headers->get('Content-Security-Policy');
        $result = self::through($response, $reportUri);

        $this->assertFalse($result->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($result->headers->has('Reporting-Endpoints'));
        $this->assertSame($own, $result->headers->get('Content-Security-Policy'));
    }
}
