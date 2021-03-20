<?php

if (!function_exists('http_response_code')) {
	// The http_response_code() function was introduced in PHP 5.4.
	function http_response_code($code = NULL) {
		if ($code !== NULL) {
			switch ($code) {
				case 100: $text = 'Continue'; break;
				case 101: $text = 'Switching Protocols'; break;
				case 200: $text = 'OK'; break;
				case 201: $text = 'Created'; break;
				case 202: $text = 'Accepted'; break;
				case 203: $text = 'Non-Authoritative Information'; break;
				case 204: $text = 'No Content'; break;
				case 205: $text = 'Reset Content'; break;
				case 206: $text = 'Partial Content'; break;
				case 300: $text = 'Multiple Choices'; break;
				case 301: $text = 'Moved Permanently'; break;
				case 302: $text = 'Moved Temporarily'; break;
				case 303: $text = 'See Other'; break;
				case 304: $text = 'Not Modified'; break;
				case 305: $text = 'Use Proxy'; break;
				case 400: $text = 'Bad Request'; break;
				case 401: $text = 'Unauthorized'; break;
				case 402: $text = 'Payment Required'; break;
				case 403: $text = 'Forbidden'; break;
				case 404: $text = 'Not Found'; break;
				case 405: $text = 'Method Not Allowed'; break;
				case 406: $text = 'Not Acceptable'; break;
				case 407: $text = 'Proxy Authentication Required'; break;
				case 408: $text = 'Request Time-out'; break;
				case 409: $text = 'Conflict'; break;
				case 410: $text = 'Gone'; break;
				case 411: $text = 'Length Required'; break;
				case 412: $text = 'Precondition Failed'; break;
				case 413: $text = 'Request Entity Too Large'; break;
				case 414: $text = 'Request-URI Too Large'; break;
				case 415: $text = 'Unsupported Media Type'; break;
				case 500: $text = 'Internal Server Error'; break;
				case 501: $text = 'Not Implemented'; break;
				case 502: $text = 'Bad Gateway'; break;
				case 503: $text = 'Service Unavailable'; break;
				case 504: $text = 'Gateway Time-out'; break;
				case 505: $text = 'HTTP Version not supported'; break;
				default:
					exit('Unknown http status code "' . htmlentities($code, ENT_COMPAT, 'UTF-8') . '"');
			}

			$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

			header($protocol . ' ' . $code . ' ' . $text);

			$GLOBALS['http_response_code'] = $code;

		} else {
			$code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
		}

		return $code;
	}
}

if ( ! function_exists( 'Requests\\TestServer\\get_routes' ) ) {
	require dirname( __DIR__ ) . '/lib/utils.php';
	require dirname( __DIR__ ) . '/lib/routes.php';
}

ini_set('html_errors', false);
header('Content-Type: application/json; charset=utf-8');

$base_url = 'http://' . $_SERVER['HTTP_HOST'];

$headers = null;
if (function_exists('apache_request_headers')) {
	$headers = apache_request_headers();
}
elseif (function_exists('getallheaders')) {
	$headers = getallheaders();
}
else {
	$headers = array();
	foreach ($_SERVER as $name => $value) {
		if ($name === 'CONTENT_TYPE') {
			if ($value !== '') {
				$headers['content-type'] = $value;
			}
			continue;
		}
		if ($name === 'CONTENT_LENGTH') {
			if ($value !== '') {
				$headers['content-length'] = $value;
			}
			continue;
		}
		if (strpos($name, 'HTTP_') !== 0) {
			continue;
		}

		// Strip HTTP_ prefix and lowercase
		$key = strtolower(substr($name, 5));
		$key = str_replace('_', ' ', $key);
		$key = ucwords($key);
		$key = str_replace(' ', '-', $key);
		$headers[$key] = $value;
	}
}

// Are we reverse proxied?
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	// Ensure caching is off
	header('Cache-Control: no-cache');
}

$request_data = array(
	'url' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
	'headers' => $headers,
	'origin' => $_SERVER['REMOTE_ADDR'],
	'args' => empty($_SERVER['QUERY_STRING']) ? new stdClass : Requests\TestServer\parse_params_rfc( $_SERVER['QUERY_STRING'] ),
);

$routes = Requests\TestServer\get_routes();

$data = null;
$here = $_SERVER['REQUEST_URI'];
if (strpos($here, '?') !== false) {
	$here = substr($here, 0, strpos($here, '?'));
}

try {
	foreach ($routes as $route => $callback) {
		$route = preg_replace('#<(\w+)>#i', '(?P<\1>\w+)', $route);
		$match = preg_match('#^' . $route . '$#i', $here, $matches);
		if (empty($match))
			continue;

		$data = $callback;
		break;
	}

	if (empty($data)) {
		throw new Exception('Requested URL not found', 404);
	}

	while (is_callable($data)) {
		$data = call_user_func($data, $matches);
	}
}
catch (Exception $e) {
	http_response_code($e->getCode());
	$data = array( 'message' => $e->getMessage() );
}

if (defined('JSON_PRETTY_PRINT')) {
	echo json_encode($data, JSON_PRETTY_PRINT);
} else {
	echo json_encode($data);
}
