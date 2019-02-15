<?php

namespace system\helpers\api\instagram;

class Login
{
	private $url = [
		'index' => 'https://www.instagram.com',
		'login' => 'https://www.instagram.com/accounts/login/ajax/'
	];

	private $ua = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36';

	private $proxy = [
		'username' 		=> 'lum-customer-hl_8dbd0b4b-zone-zone1',
		'password' 		=> '9pec9kbd2z1w',
		'port'		 		=> 22225,
		'super_proxy'	=> 'zproxy.lum-superproxy.io'
	];

	public function getCookies($response)
	{
		$cookieOBJ = new \stdClass;
		$cookieOBJ->cookies = '';

		if (preg_match_all('/^Set-Cookie: \s*([^;]*)/mi', $response, $matches))
		{
			foreach ($matches[1] as $cookies)
			{
				$cookies = explode('=', str_replace('"', '', $cookies));
				
				$key = !(empty($cookies[0])) ? $cookies[0] : '';
				$val = !(empty($cookies[1])) ? $cookies[1] : '';

				$cookieOBJ->{$key} = $val;

				$cookieOBJ->cookies .= $key . '=' . $cookieOBJ->{$key} . '; ';
			}
			return $cookieOBJ;
		}
		return false;
	}

	public function getLogin()
	{
		$options = [
			CURLOPT_URL							=> $this->url['index'],
			CURLOPT_HEADER					=> true,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_SSL_VERIFYHOST	=> 0,
			CURLOPT_SSL_VERIFYPEER	=> 0,
		];

		$request = curl_init();

		curl_setopt_array($request, $options);

		$response = curl_exec($request);

		curl_close($request);

		return $this->getCookies($response);
	}

	public function postLogin($data, $cookies)
	{

		if (!is_array($data) || !is_array($cookies))
		{
			return false;
		}

		$data = http_build_query($data);

		$session = mt_rand();

		$options = [
			CURLOPT_URL							=> $this->url['login'],
			CURLOPT_CUSTOMREQUEST		=> 'POST',
			CURLOPT_POSTFIELDS			=> $data,
			CURLOPT_PROXY						=> 'http://' . $this->proxy['super_proxy']. ':' . $this->proxy['port'],
			CURLOPT_PROXYUSERPWD		=> $this->proxy['username'] . '-country-br-session-' . $session . ':' . $this->proxy['password'],
			CURLOPT_HEADER					=> true,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_SSL_VERIFYHOST	=> 0,
			CURLOPT_SSL_VERIFYPEER	=> 0,
			CURLOPT_USERAGENT       => $this->ua,
			CURLOPT_COOKIE					=> $cookies[0]->cookies,
			CURLOPT_HTTPHEADER			=> [
				'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
				'content-length: ' . strlen($data),
				'content-type: application/x-www-form-urlencoded',
				'origin: https://www.instagram.com',
				'referer: https://www.instagram.com/',
				'x-csrftoken: ' . $cookies[0]->csrftoken,
				'x-instagram-ajax: 1',
				'x-requested-with: XMLHttpRequest'
			]
		];

		$request = curl_init();

		curl_setopt_array($request, $options);

		$response = curl_exec($request);

		$header_size = curl_getinfo($request, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$json = substr($response, $header_size);
		$json = json_decode($json);

		curl_close($request);

		if (isset($json->authenticated) && $json->authenticated === false && $json->user === true)
		{
			return ['incorrect_password'];
		}
		else if (isset($json->authenticated) && $json->authenticated === false && $json->user === false)
		{
			return ['incorrect_user'];
		}
		else if (isset($json->status) && $json->status === 'fail' && $json->message === 'checkpoint_required' && $json->lock === false)
		{
			return [
				'checkpoint_required',
				$cookies,
				$json
			];
		}
		elseif (isset($json->message) && $json->message === 'Please wait a few minutes before you try again.' && $json->status === 'fail')
		{
			return ['wait_minutes'];
		}
		else
		{
			return [$this->getCookies($response)];
		}
	}

	public function getChallenge($url, $cookies)
	{
		if (empty($url) || !is_array($cookies))
		{
			return false;
		}

		$data = http_build_query(
			[
				'choice' => 1
			]
		);

		$session = mt_rand();

		$options = [
			CURLOPT_URL							=> $this->url['index'] . $url,
			CURLOPT_CUSTOMREQUEST		=> 'POST',
			CURLOPT_POSTFIELDS			=> $data,
			CURLOPT_PROXY						=> 'http://' . $this->proxy['super_proxy']. ':' . $this->proxy['port'],
			CURLOPT_PROXYUSERPWD		=> $this->proxy['username'] . '-country-br-session-' . $session . ':' . $this->proxy['password'],
			CURLOPT_HEADER					=> true,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_SSL_VERIFYHOST	=> 0,
			CURLOPT_SSL_VERIFYPEER	=> 0,
			CURLOPT_USERAGENT       => $this->ua,
			CURLOPT_COOKIE					=> $cookies[0]->cookies,
			CURLOPT_HTTPHEADER			=> [
				'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
				'content-length: ' . strlen($data),
				'content-type: application/x-www-form-urlencoded',
				'origin: https://www.instagram.com',
				'referer: https://www.instagram.com' . $url,
				'x-csrftoken: ' . $cookies[0]->csrftoken,
				'x-instagram-ajax: 1',
				'x-requested-with: XMLHttpRequest'
			]
		];

		$request = curl_init();

		curl_setopt_array($request, $options);

		$response = curl_exec($request);

		$header_size = curl_getinfo($request, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$json = substr($response, $header_size);
		$json = json_decode($json);

		curl_close($request);

		if (isset($json->message) && $json->message === 'Please wait a few minutes before you try again.')
		{
			return 'wait_minutes';
		}
		elseif (isset($json->challenge->errors[0]) && $json->challenge->errors[0] === 'Select a valid choice. 1 is not one of the available choices.')
		{
			return 'invalid_choice';
		}

		return $json;
	}

	public function postChallenge($url, $cookies, $code)
	{

		if (empty($url) || !is_array($cookies) || empty($code))
		{
			return false;
		}

		$data = http_build_query(
			[
				'security_code' => $code
			]
		);

		$session = mt_rand();

		$options = [
			CURLOPT_URL							=> $this->url['index'] . $url,
			CURLOPT_CUSTOMREQUEST		=> 'POST',
			CURLOPT_POSTFIELDS			=> $data,
			CURLOPT_PROXY						=> 'http://' . $this->proxy['super_proxy']. ':' . $this->proxy['port'],
			CURLOPT_PROXYUSERPWD		=> $this->proxy['username'] . '-country-br-session-' . $session . ':' . $this->proxy['password'],
			CURLOPT_HEADER					=> true,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_SSL_VERIFYHOST	=> 0,
			CURLOPT_SSL_VERIFYPEER	=> 0,
			CURLOPT_USERAGENT       => $this->ua,
			CURLOPT_COOKIE					=> $cookies[0]->cookies,
			CURLOPT_HTTPHEADER			=> [
				'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
				'content-length: ' . strlen($data),
				'content-type: application/x-www-form-urlencoded',
				'origin: https://www.instagram.com',
				'referer: https://www.instagram.com' . $url,
				'x-csrftoken: ' . $cookies[0]->csrftoken,
				'x-instagram-ajax: 1',
				'x-requested-with: XMLHttpRequest'
			]
		];

		$request = curl_init();

		curl_setopt_array($request, $options);

		$response = curl_exec($request);

		$header_size = curl_getinfo($request, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$json = substr($response, $header_size);
		$json = json_decode($json);

		curl_close($request);

		if (isset($json->challenge->errors[0]) && $json->challenge->errors[0] === 'Please check the code we sent you and try again.')
		{
			return 'incorrect_code';
		}
		elseif (isset($json->message) && $json->message === 'Please wait a few minutes before you try again.')
		{
			return 'wait_minutes';
		}
		elseif (isset($json->type) && $json->type === 'CHALLENGE_REDIRECTION')
		{
			return $this->getCookies($header);
		}

		return $json;
	}
}

