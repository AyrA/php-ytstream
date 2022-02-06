<?php
	//Renders the example configuration file if the system is not configured
	if(FALSE===@include('config.php')){
		header('HTTP/1.1 500 Internal Server Error');
		if(is_file('config.example.php')){
			echo '<h1>Example configuration</h1>';
			highlight_file('config.example.php');
		}
		else{
			echo 'Configuration file missing and no example file available. Check readme file for instructions';
		}
		die(0);
	}
	//Include dependencies after the configuration
	foreach(glob(__DIR__ . DIRECTORY_SEPARATOR . 'include.*.php') as $f){
		require($f);
	}

	//Sanity checks
	initCache();
	is_file(YTDL) or http500(YTDL . ' does not exist');
	is_file(FFMPEG) or http500(FFMPEG . ' does not exist');

	//Writes a message to the debug log if debug logging is enabled
	function debuglog($x){
		if(defined('DEBUG') && constant('DEBUG')===TRUE){
			$line="[" . gmdate('Y-m-d H:i:s') . "]:\t";
			file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.log',$line . $x . PHP_EOL,FILE_APPEND);
		}
	}

	//Lazy function to not have to use isset and is_array all the time (av=Array Value)
	//$a:  Value that's supposed to be an array
	//$k:  Key that's supposed to be a string or number
	//$d:  Value to return if $a is not an array or $k is an invalid/non-existant key
	function av($a,$k,$d=NULL){
		if(is_string($k) || is_int($k) || is_float($k)){
			return is_array($a) && isset($a[$k]) ? $a[$k] : $d;
		}
		return $d;
	}

	//Checks if the given argument is a technically valid youtube id (does not check if video exists)
	function isYtId($id){
		return is_string($id) && !!preg_match('#^[\w\-]{10}[AEIMQUYcgkosw048]=?$#',$id);
	}

	//Downloads a video or audio file and converts it to an MP3, then strips ranges
	//$sourceURL:    URL of audio file. Usually the result of getStreamUrl()
	//$outputStream: Destination where the converted and cut MP3 is sent to
	//$id:           Youtube video Id
	//$ranges:       Array of ranges. A range is an array that contains start and end timestamp as two numbers
	function pipeAudio($sourceURL,$outputStream,$id,$ranges){
		debuglog("Convert to MP3: id=$id");
		$mp3=getMp3file($id);
		//Use cached copy if available
		if(USE_CACHE && is_file($mp3)){
			if($fp=fopen($mp3,'rb')){
				strip($fp,$outputStream,NULL,$ranges);
				fclose($fp);
				return TRUE;
			}
			return FALSE;
		}
		elseif(!USE_CACHE){
			$cache=NULL;
		}
		if(!USE_CACHE || ($cache=fopen($mp3,'wb'))){
			$exec=realpath(FFMPEG) . ' -i "' . $sourceURL . '" -ab 192000 -vn -ar 44100 -acodec mp3 -f mp3 -y pipe:1';
			if($proc=popen($exec,'rb')){
				strip($proc,$outputStream,$cache,$ranges);
				pclose($proc);
				return TRUE;
			}
			return FALSE;
		}
		return FALSE;
	}

	//Sends DLNA headers for MP3 streaming
	function setAudioHeader(){
		if(!headers_sent()){
			header('Content-Type: audio/mpeg');
			header('transferMode.dlna.org: Streaming');
			header('contentFeatures.dlna.org: DLNA.ORG_PN=MP3;DLNA.ORG_OP=01;DLNA.ORG_FLAGS=01700000000000000000000000000000');
			return TRUE;
		}
		return FALSE;
	}

	//Obtains a video as MP3, downloads it, cuts it and outputs it.
	function getAndCut($id){
		$videourl="https://www.youtube.com/watch?v=$id";
		$mp3=getMp3file($id);
		if(USE_CACHE && is_file($mp3)){
			debuglog("Using cached file for id=$id");
			if($fp=fopen($mp3,'rb')){
				if($out=fopen('php://output','wb')){
					$segments=getRanges($id);
					if(!is_array($segments)){
						$segments=array();
					}
					strip($fp,$out,NULL,$segments);
					return TRUE;
				}
			}
			return FALSE;
		}

		$stream=getStreamUrl($videourl);
		if(!$stream){
			return FALSE;
		}
		debuglog("Got stream URL for id=$id");
		$segments=getRanges($id);
		if(!is_array($segments)){
			debuglog("Invalid SBlock answer for id=$id; Maybe has no ranges");
			$segments=array();
		}
		debuglog("id=$id has " . count($segments) . ' SBlock ranges');
		if($out=fopen('php://output','wb')){
			pipeAudio($stream,$out,$id,$segments);
			fclose($out);
			return TRUE;
		}
		return FALSE;
	}

	function streamFiles($ids){
		$httpMethod=strtoupper(av($_SERVER,'REQUEST_METHOD'));
		//Avoid the expensive processing steps for HEAD requests
		//and just send the appropriate headers instead.
		if($httpMethod==='HEAD'){
			debuglog("HEAD id=$id");
			setAudioHeader();
		}
		else if($httpMethod!=='GET'){
			http400('This supports only GET');
		}
		else{
			setAudioHeader();
			if(av($_GET,'rnd')==='y'){
				shuffle($ids);
			}
			foreach($ids as $id){
				debuglog("GET id=$id");
				getAndCut($id);
			}
		}
	}

	//Grab id list and split into ids
	$ids=av($_GET,'id');
	//Support multiple ids
	if(strlen($ids)>0){
		$ids=explode(',',$ids);
		foreach($ids as $id){
			if(!isYtId($id)){
				http400("Invalid youtube Id format for $id");
			}
		}
		streamFiles($ids);
		die(0);
	}

	//If we're here, no parameter has been specified and we render a simple web page

	//Set charset
	header('Content-Type: text/html;charset=utf-8');
	//Pretend IE still matters
	header('X-UA-Compatible: IE=edge');
	//Create self-referential URL. If this doesn't works for your server,
	//you can hardcode it here
	$self=
		//Detect HTTPS
		'http' . (strtoupper(av($_SERVER,'HTTPS'))==='ON'?'s':'') . '://' .
		//Detect host name
		av($_SERVER,'SERVER_NAME') .
		//Detect path
		av($_SERVER,'REQUEST_URI',av($_SERVER,'SCRIPT_NAME'));
?><!DOCTYPE html>
<html lang="en">
	<head>
		<title>PHP Youtube Stream Library</title>
		<style>
			body{margin:auto;max-width:800px;font-family:Sans-Serif}
		</style>
		<!-- Pretend a webradio stream platform cares about mobile users -->
		<meta name="viewport" content="width=device-width, initial-scale=1" />
	</head>
	<body>
		<h1>PHP Youtube Stream Library</h1>
		<p>
			This system allows you to directly stream Youtube videos to MP3.
			It has a few features that separate it from other systems.
			Options marked with an asterisk may be disabled by the application operator.
		</p>
		<ul>
			<li>
				The audio stream is delivered while being downloaded.
				No need to wait for it to finish on the server.
			</li>
			<li>
				Sends additional HTTP headers that allow streaming on DLNA devices.
			</li>
			<li>
				* Once streamed, the stream stays available,
				even if the original video is deleted.
			</li>
			<li>
				* Removal of non-music sections.
				This application uses SponsorBlock to reliably remove non-music sections from music videos.
			</li>
		</ul>
		<p>
			How to use:<br />
			Copy the watch id from any youtube video and add it as id argument to this URL.<br />
			Example:<br />
			YT: <code>https://www.youtube.com/watch?v=<b>dQw4w9WgXcQ</b></code><br />
			Stream: <code><?=$self;?>?id=<b>dQw4w9WgXcQ</b></code>
		</p>
		<p>
			You can supply multiple ids (including duplicates) using commas.<br />
			Example: <code>?id=<b>dQw4w9WgXcQ,dQw4w9WgXcQ,hJresi7z_YM,dQw4w9WgXcQ</b></code><br />
			This will play a video twice, then another one, then the first one again.
			The source files will be concatenated into a single continuous MP3 stream.<br />
			Note: Depending on how big your cache is
			you may experience a short interruption if youtube is slow to answer.
			A 5 second cache is usually sufficient.
		</p>
		<p>
			Randomized play. Adding <code>&amp;rnd=y</code> to the URL will shuffle the id list.
			For obvious reasons, this has no effect if only one id is supplied.
		</p>
		<hr />
		<p>
			php-ytstream: Live transcoding of youtube videos into MP3<br />
			Copyright (C) 2022 Kevin Gut
		</p>
		<p>
			This program is free software: you can redistribute it and/or modify
			it under the terms of the GNU Affero General Public License as published by
			the Free Software Foundation, either version 3 of the License, or
			(at your option) any later version.
		</p>
	</body>
</html>
