<?php

namespace App\Http\Controllers;

use App\Rules\Human;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\HTTP\Request as HTTPRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
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

    private function index(?ValidatorContract $errors = null, ?string $generr = null)
    {
        [$opensslVersion, $openssl] = $this->_opensslVersion();
        $haveZip = \extension_loaded('zip');

        $ret = view('selfsigned', [
            'openssl' => $openssl,
            'opensslVersion' => $opensslVersion,
            'zip' => $haveZip,
        ]);

        // We want to get a monitoring error when this happens
        if (!$openssl || !$haveZip) {
            http_response_code(503);
            Log::critical(__METHOD__.': $openssl == '.($openssl ? 1 : 0).'; $haveZip == '.($haveZip ? 1 : 0));
        }

        if ($errors !== null) {
            $ret->withErrors($errors);
        }
        if ($generr !== null) {
            $ret->with('generr', $generr);
        }
        return $ret;
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
            shell_exec("openssl req -x509 -new -nodes -key $cafile.key -sha256 -days 1024 -out $cafile.pem -subj \"/C=US/ST=CA/O=localhost/CN=localhost\" 2>&1");
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

    public function make(HTTPRequest $request)
    {
        if (!Request::isMethod('post')) {
            return redirect('/selfsigned');
        }

        [, $openssl] = $this->_opensslVersion();
        if ($openssl === null) {
            abort(500);
        }

        /** @var $validator \Illuminate\Validation\Validator */
        $input_data = $request->all(['commonName', 'subdomains', 'validFor']);
        $input_data['human'] = $request->isHuman();
        $validator = Validator::make($input_data, [
            'commonName' => 'bail|min:3|max:253|required|string|domain',
            'subdomains' => 'bail|string|subdomains',
            'validFor' => 'bail|int|min:1|max:3652|required',
            'human' => new Human(),
        ]);
        if ($validator->fails()) {
            return $this->_index($validator);
        }

        $commonName = $request->input('commonName');
        $validFor = $request->input('validFor');
        $alternateNames = $this->_processAlternateNames($request->input('subdomains'), $commonName);
        $conf = str_replace(
            ['%SAN%', '%CN%'],
            [$alternateNames, $commonName],
            file_get_contents(resource_path('openssl.cnf'))
        );

        $TMP = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).'/selfsigned-'.Uuid::generate(4);

        file_put_contents("$TMP.cnf", $conf);

        $RANDFILE = storage_path('.rnd-'.Uuid::generate(4));
        putenv("RANDFILE=$RANDFILE");

        $cafile = $this->_genCAFiles();

        $output = '';
        $output .= shell_exec("openssl req -out $TMP.csr -new -newkey rsa:2048 -sha256 -nodes -keyout $TMP.key -subj \"/C=US/ST=CA/O=$commonName/CN=$commonName\" -reqexts x509_ext -config $TMP.cnf 2>&1");
        $output .= shell_exec("openssl x509 -req -in $TMP.csr -CA $cafile.pem -CAkey $cafile.key -CAcreateserial -out $TMP.crt -days {$validFor} -extensions x509_ext -sha256 -extfile $TMP.cnf 2>&1");
        @unlink($RANDFILE);

        if (!file_exists("$TMP.key") || !file_exists("$TMP.crt")) {
            $this->_makeCleanup($TMP);
            return $this->_index(null, __('selfsigned.err_filefail'));
        }

        $zip = new \ZipArchive();
        $zipname = "$TMP.zip";
        if (($err = $zip->open($zipname, \ZipArchive::CREATE)) !== true) {
            return $this->_index(null, __('selfsigned.err_zipfail', ['err' => $err]));
        }
        $zip->addFile("$TMP.crt", "$commonName.crt");
        $zip->addFile("$TMP.key", "$commonName.key");
        $zip->close();
        $this->_makeCleanup($TMP);

        header('Content-Type: application/zip');
        header("Content-disposition: attachment; filename=$commonName.zip");
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
     * @param  string  $commonName
     * @return string
     */
    private function _processAlternateNames($subdomains, string $commonName)
    {
        $list = "DNS.1 = $commonName";
        if (\is_string($subdomains)) {
            $newList = explode("\n", $subdomains);
            natsort($newList);
            foreach ($newList as $i => $sd) {
                $dns = trim($sd, "\t\n\r\0\x08.");
                if (!preg_match('~\.$~', $sd)) {
                    $dns .= ".$commonName";
                }
                $newList[$i] = 'DNS.'.($i + 2)." = $dns";
            }
            $list .= "\n".implode("\n", $newList);
        }
        return $list;
    }
}
