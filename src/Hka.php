<?php

namespace Hka;
class Hka
{
    const _TIMEOUT = 30;
    const _REDIRECT_MAX = 9;
    const _EXP_CODE = 9999;
    const _EXP_MSG = 'Unknown Error';
    protected static $_timeout;
    protected static $_header;
    protected static $_host;
    protected static $_port;
    protected static $_scheme;
    protected static $_isProxy;
    protected static $_path;
    protected static $_query;
    protected static $_reqHeaderStr;
    protected static $_fd;
    protected static $_isCurl;
    protected static $_sslCerts;
    protected static $_redirectNum;
    protected static $_return;
    protected static $_option;
    protected static $_lastStats;
    protected static $_repHeader;

    protected static $_codes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    );

    protected static function init()
    {
        self::$_timeout = self::_TIMEOUT;
        self::$_header = [
            'Connection' => 'keep-alive',
            'Accept' => '*/*',
        ];
        self::$_host = '';
        self::$_port = '';
        self::$_scheme = '';
        self::$_isProxy = false;
        self::$_path = '/';
        self::$_query = '';
        self::$_reqHeaderStr = '';
        self::$_fd = NULL;
        self::$_isCurl = false;
        self::$_sslCerts = [];
        self::$_option = [];
        self::$_return = false;
        self::$_lastStats = [
            'code' => '400',
            'msg' => 'Bad Request'
        ];
        self::$_repHeader = [];
        if (!self::$_redirectNum) {
            self::$_redirectNum = 0;
        }
    }

    protected static function conn()
    {
        if (self::$_port == 443) {
            self::$_fd = @pfsockopen("ssl://" . self::$_host, self::$_port, $errno, $errstr, self::$_timeout);
        } else {
            self::$_fd = @pfsockopen(self::$_host, self::$_port, $errno, $errstr, self::$_timeout);
        }
        if (!self::$_fd) {
            throw new \RuntimeException('fsockopen fail');
        }
        stream_set_timeout(self::$_fd, self::$_timeout);
        if (strlen($errstr) || $errno != 0) {
            throw new \RuntimeException(sprintf('[errno:%s;errstr:%s]', $errno, $errstr));
        }
    }

    protected static function parse($url)
    {
        $parse = parse_url($url);
        if (!$parse) {
            throw new \InvalidArgumentException("url error");
        }
        if (!isset($parse['scheme'])) {
            throw new \InvalidArgumentException("scheme error");
        }
        if (!isset($parse['host'])) {
            throw new \InvalidArgumentException("host error");
        }

        self::$_scheme = strtolower($parse['scheme']);
        self::$_host = $parse['host'];

        if (self::$_scheme == 'https') {
            self::$_port = 443;
        } else {
            self::$_port = 80;
        }
        if (isset($parse['port'])) {
            self::$_port = $parse['port'];
        }
        if (isset($parse['path'])) {
            self::$_path = $parse['path'];
        }
        if (isset($parse['query'])) {
            self::$_query = $parse['query'];
        }
    }

    protected static function option($option)
    {
        self::$_option = $option;
        //header
        if (isset($option['header']) && is_array($option['header'])) {
            $header = $option['header'];
            self::$_header = array_merge(self::$_header, $header);
        }
        //proxy
        if (isset($option['proxy_host']) && isset($option['proxy_port'])) {
            self::$_host = $option['proxy_host'];
            self::$_port = $option['proxy_port'];
            self::$_isProxy = true;
        }
        //timeout
        if (isset($option['timeout'])) {
            self::$_timeout = intval($option['timeout']);
        }

        //curl
        if (isset($option['curl'])) {
            self::$_isCurl = true;
        }

        //cert
        if (isset($option['cert_pem']) && isset($option['key_pem'])) {
            self::$_isCurl = true;
            self::$_sslCerts['cert_pem'] = $option['cert_pem'];
            self::$_sslCerts['key_pem'] = $option['key_pem'];
        }
    }

    protected static function setReqHeaderStr($method, $data)
    {
        self::$_header['Host'] = self::$_host;

        if ($method == 'POST') {
            if (is_array($data)) {
                self::$_header['Content-Type'] = 'application/x-www-form-urlencoded';
                $body_str = http_build_query($data);
                self::$_header['Content-Length'] = strlen($body_str);
            } else {
                $body_str = $data;
            }
        }

        if (self::$_query) {
            self::$_path .= "?" . self::$_query;
        }
        $path = self::$_path;
        $header_str = "{$method} {$path} HTTP/1.1\r\n";
        foreach (self::$_header as $k => $v) {
            $header_str .= "{$k}:{$v}\r\n";
        }
        $header_str .= "\r\n";
        if (!empty($data) && is_array($data)) {
            $header_str .= $body_str;
        }
        self::$_reqHeaderStr = $header_str;
    }

    protected static function setRepHeader($headerStr)
    {
        $tmp = explode("\r\n", $headerStr);
        $headers = [];
        foreach ($tmp as $k => $v) {
            if ($k == 0) {
                $http_code = substr($v, 9, 3);
                $http_msg = substr($v, 13);
                if (!strlen(trim($http_msg))) {
                    if (isset(self::$_codes[$http_code])) {
                        $http_msg = self::$_codes[$http_code];
                    } else {
                        $http_msg = self::_EXP_MSG;
                    }
                }
                self::$_lastStats['code'] = $http_code;
                self::$_lastStats['msg'] = $http_msg;
            }
            $h2 = explode(':', $v);
            $key = trim($h2[0]);
            $value = preg_replace('/[a-zA-Z- ]*: /', '', $v);
            if (isset($headers[$key])) {
                if (!is_array($headers[$key])) {
                    $headers[$key] = [];
                }
                $headers[$key][] = trim($value);
            } else {
                $headers[$key] = trim($value);
            }
        }
        self::$_repHeader = $headers;
    }

    protected static function getHttpContent($url, $method = 'GET', $postData = array(), $header = [])
    {
        $data = '';
        if (!empty($url)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, self::$_timeout);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                if (strtoupper($method) == 'POST') {
                    $curlPost = is_array($postData) ? http_build_query($postData) : $postData;
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);

                    if (self::$_sslCerts) {
                        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                        curl_setopt($ch, CURLOPT_SSLCERT, self::$_sslCerts['cert_pem']);

                        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                        curl_setopt($ch, CURLOPT_SSLKEY, self::$_sslCerts['key_pem']);
                    }
                }
                $data = curl_exec($ch);

                if (!curl_errno($ch)) {
                    self::$_lastStats['code'] = $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    self::$_lastStats['msg'] = isset(self::$_codes[$code]) ? self::$_codes[$code] : self::_EXP_MSG;
                } else {
                    self::$_lastStats['code'] = curl_errno($ch);
                    self::$_lastStats['msg'] = curl_error($ch);
                }

                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $header = substr($data, 0, $headerSize);
                if ($header) {
                    self::setRepHeader(rtrim($header));
                }
                $data = substr($data, $headerSize);
                curl_close($ch);
            } catch (\Exception $e) {
                self::$_lastStats['code'] = $e->getCode() ?: self::_EXP_CODE;
                self::$_lastStats['msg'] = get_class($e) . ":" . $e->getMessage();
                $data = false;
            } catch (\Throwable $throwable) {
                self::$_lastStats['code'] = $throwable->getCode() ?: self::_EXP_CODE;
                self::$_lastStats['msg'] = get_class($throwable) . ":" . $throwable->getMessage();
                $data = false;
            }
        }
        return $data;
    }

    protected static function req($url, $method, $data)
    {
        if (!self::$_isCurl) {
            fwrite(self::$_fd, self::$_reqHeaderStr);
        } else {
            foreach (self::$_header as $k => $v) {
                $header[] = "{$k}:{$v}";
            }
            self::$_return = self::getHttpContent($url, $method, $data, $header);
        }
    }


    protected static function rep()
    {
        if (self::$_isCurl) {
            return self::$_return;
        }
        $responseHeader = '';
        $responseContent = '';

        $recv_err = false;
        while (true) {
            $responseHeader .= @fread(self::$_fd, 1);
            if (!strlen($responseHeader)) {
                $recv_err = true;
                break;
            }
            if (preg_match('/\\r\\n\\r\\n$/', $responseHeader)) {
                break;
            }
        }
        if ($recv_err) {
            self::$_lastStats['code'] = 504;
            self::$_lastStats['msg'] = self::$_codes[504];
            return false;
        }
        self::setRepHeader(rtrim($responseHeader));

        //redirect
        if (in_array(self::$_lastStats['code'], [301, 302])) {
            if (self::$_redirectNum >= self::_REDIRECT_MAX) {
                throw new \RuntimeException('too many redirects', 310);
            }
            $location = self::$_repHeader['Location'];
            self::$_redirectNum++;
            return self::get($location, self::$_option);
        }

        //Content-Length
        if (isset(self::$_repHeader['Content-Length']) && self::$_repHeader['Content-Length']) {
            $content_length = self::$_repHeader['Content-Length'];
            $readLength = 0;
            while ($readLength < $content_length) {
                $responseContent .= @fread(self::$_fd, $content_length - $readLength);
                $readLength = strlen($responseContent);
            }
        }

        //Chunked
        if (isset(self::$_repHeader['Transfer-Encoding']) && self::$_repHeader['Transfer-Encoding'] == 'chunked') {
            while ($chunkLength = hexdec(@fgets(self::$_fd))) {
                $responseContentChunk = '';
                $readLength = 0;

                while ($readLength < $chunkLength) {
                    $responseContentChunk .= @fread(self::$_fd, $chunkLength - $readLength);
                    $readLength = strlen($responseContentChunk);
                }

                $responseContent .= $responseContentChunk;
                @fgets(self::$_fd);
            }
        }
        self::$_return = $responseContent ? $responseContent : false;
        return self::$_return;
    }


    public static function get($url, $option = [])
    {
        return self::handle($url, __FUNCTION__, $option);
    }

    public static function post($url, $data = [], $option = [])
    {
        return self::handle($url, __FUNCTION__, $option, $data);
    }

    public static function handle($url, $method, $option = [], $data = [])
    {
        try {
            self::init();
            self::parse($url);
            self::option($option);
            if (!self::$_isCurl) {
                self::conn();
                self::setReqHeaderStr(strtoupper($method), $data);
            }
            self::req($url, strtoupper($method), $data);
            return self::rep();
        } catch (\Exception $e) {
            self::$_lastStats['code'] = $e->getCode() ?: self::_EXP_CODE;
            self::$_lastStats['msg'] = get_class($e) . ":" . $e->getMessage();
            return false;
        } catch (\Throwable $throwable) {
            self::$_lastStats['code'] = $throwable->getCode() ?: self::_EXP_CODE;
            self::$_lastStats['msg'] = get_class($throwable) . ":" . $throwable->getMessage();
            return false;
        }
    }

    public static function getLastStats()
    {
        return self::$_lastStats;
    }

    public static function getLastVars()
    {
        $vars = get_class_vars(self::class);
        if (isset($vars['_return'])) unset($vars['_return']);
        if (isset($vars['_codes'])) unset($vars['_codes']);
        return $vars;
    }

    public static function getLastRepHeaders()
    {
        return self::$_repHeader;
    }
}

