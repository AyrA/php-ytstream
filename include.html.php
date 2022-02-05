<?php
	//Send "400 bad request" and end processing
	function http400($msg='Unspecified reason'){
		if(!headers_sent()){
			header('HTTP/1.1 400 Bad Request');
			header('Content-Type: text/html; charset=utf-8');
		}
		echo '<!DOCTYPE html><h1>HTTP 400 - Bad Request</h1>' . he($msg);
		die(400);
	}

	//Send "500 server error" and end processing
	function http500($msg='Unspecified reason'){
		if(!headers_sent()){
			header('HTTP/1.1 500 Bad Request');
			header('Content-Type: text/html; charset=utf-8');
		}
		echo '<!DOCTYPE html><h1>HTTP 500 - Internal server error</h1>' . he($msg);
		die(500);
	}

	//Provides a short function name to HTML encode
	function he($x){
		return htmlspecialchars($x,ENT_HTML5|ENT_SUBSTITUTE);
	}