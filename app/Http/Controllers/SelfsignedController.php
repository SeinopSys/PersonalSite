<?php

namespace App\Http\Controllers;

use App\Rules\Human;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\HTTP\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Webpatser\Uuid\Uuid;

class SelfsignedController extends Controller
{
    private function _opensslVersion()
    {
        $opensslVersion = exec('openssl version 2>&1', $out);
        if (preg_match('~^OpenSSL (\d+\.\d+.\d+[a-z]*)\s.*$~', $opensslVersion, $matches)) {
            $openssl = $matches[1];
        } else {
            $openssl = null;
        }

        return [$opensslVersion, $openssl];
    }

    public function index()
    {
        [$opensslVersion, $openssl] = $this->_opensslVersion();
        $haveZip = \extension_loaded('zip');

        // We want to get a monitoring error when this happens
        if (!$openssl || !$haveZip) {
            http_response_code(503);
            Log::critical(__METHOD__.': $openssl == '.($openssl ? 1 : 0).'; $haveZip == '.($haveZip ? 1 : 0));
        }

        return view('selfsigned', [
            'openssl' => $openssl,
            'opensslVersion' => $opensslVersion,
            'zip' => $haveZip,
            'css' => ['selfsigned'],
        ]);
    }


    /**
     * Thanks based Stack Overflow
     * https://stackoverflow.com/a/43666288/1344955
     */
    private function _genCAFiles()
    {
        $cafile = storage_path('rootCA');
        if (!file_exists("$cafile.key")) {
            shell_exec("openssl genrsa -out $cafile.key 2048 2>&1");
        }
        if (!file_exists("$cafile.pem")) {
            shell_exec("openssl req -x509 -new -nodes -key $cafile.key -sha256 -days 3652 -out $cafile.pem -subj \"/C=US/ST=CA/O=localhost/CN=localhost\" 2>&1");
        }
        return $cafile;
    }

    public function rootCA()
    {
        $pemfile = $this->_genCAFiles().'.pem';
        if (!file_exists($pemfile)) {
            return abort(404);
        }

        header('Content-Type: application/octet-stream');
        header('Content-disposition: attachment; filename=rootCA.pem');
        header('Content-Length: '.filesize($pemfile));
        readfile($pemfile);
        die();
    }

    public function make(Request $request)
    {
        [, $openssl] = $this->_opensslVersion();
        if ($openssl === null) {
            abort(500);
        }

        /** @var $validator \Illuminate\Validation\Validator */
        $input_data = $request->all(['common_name', 'subdomains', 'valid_for']);
        $input_data['human'] = $request->isHuman();
        $validator = Validator::make($input_data, [
            'common_name' => 'bail|min:3|max:253|required|string|domain',
            'subdomains' => 'bail|string|subdomains',
            'valid_for' => 'bail|int|min:1|max:3652|required',
            'human' => new Human(),
        ]);
        if ($validator->fails()) {
            return redirect()->route('selfsigned')->withErrors($validator)->withInput($validator->getData());
        }

        $common_name = strtolower($request->input('common_name'));
        $valid_for = $request->input('valid_for');
        $alternate_names = $this->_processAlternateNames($request->input('subdomains'), $common_name);
        $conf = str_replace(
            ['%SAN%', '%CN%'],
            [$alternate_names, $common_name],
            file_get_contents(resource_path('openssl.cnf'))
        );

        $generated_path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).'/selfsigned-'.Uuid::generate(4);

        file_put_contents("$generated_path.cnf", $conf);

        $rand_file = storage_path('.rnd-'.Uuid::generate(4));
        putenv("RANDFILE=$rand_file");

        $cafile = $this->_genCAFiles();

        $output = '';
        $output .= shell_exec("openssl req -out $generated_path.csr -new -newkey rsa:2048 -sha256 -nodes -keyout $generated_path.key -subj \"/C=US/ST=CA/O=$common_name/CN=$common_name\" -reqexts x509_ext -config $generated_path.cnf 2>&1");
        $output .= shell_exec("openssl x509 -req -in $generated_path.csr -CA $cafile.pem -CAkey $cafile.key -CAcreateserial -out $generated_path.crt -days {$valid_for} -extensions x509_ext -sha256 -extfile $generated_path.cnf 2>&1");
        @unlink($rand_file);

        if (!file_exists("$generated_path.key") || !file_exists("$generated_path.crt")) {
            $this->_makeCleanup($generated_path);
            return redirect()->route('selfsigned')->withErrors([
                'gen_err' => [__('selfsigned.err_filefail')],
            ])->withInput($validator->getData());
        }

        $zip = new \ZipArchive();
        $zipname = "$generated_path.zip";
        if (($err = $zip->open($zipname, \ZipArchive::CREATE)) !== true) {
            return redirect()->route('selfsigned')->withErrors([
                'gen_err' => [__('selfsigned.err_zipfail', ['err' => $err])],
            ])->withInput($validator->getData());
        }
        $zip->addFile("$generated_path.crt", "$common_name.crt");
        $zip->addFile("$generated_path.key", "$common_name.key");
        $zip->close();
        $this->_makeCleanup($generated_path);

        header('Content-Type: application/zip');
        header("Content-disposition: attachment; filename=$common_name.zip");
        header('Content-Length: '.filesize($zipname));
        readfile($zipname);
        App::terminate();
        die();
    }

    public function _makeCleanup(string $TMP)
    {
        foreach (['crt', 'cnf', 'key'] as $ext) {
            @unlink("$TMP.$ext");
        }
    }

    /**
     * @param  string|null  $subdomains
     * @param  string  $common_name
     * @return string
     */
    private function _processAlternateNames($subdomains, string $common_name)
    {
        $list = "DNS.1 = $common_name";
        if (\is_string($subdomains)) {
            $newList = explode("\n", strtolower($subdomains));
            natsort($newList);
            foreach ($newList as $i => $sd) {
                $dns = trim($sd, "\t\n\r\0\x08.");
                if (!preg_match('~\.$~', $sd)) {
                    $dns .= ".$common_name";
                }
                $newList[$i] = 'DNS.'.($i + 2)." = $dns";
            }
            $list .= "\n".implode("\n", $newList);
        }
        return $list;
    }
}
