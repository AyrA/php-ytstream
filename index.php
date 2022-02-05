<?php
	require('config.php');
	//Include dependencies after the configuration
	foreach(glob(__DIR__ . DIRECTORY_SEPARATOR . 'include.*.php') as $f){
		require($f);
	}

	initCache();
	is_file(YTDL) or http500(YTDL . ' does not exist');
	is_file(FFMPEG) or http500(FFMPEG . ' does not exist');

	$id=av($_GET,'id');
	$httpMethod=strtoupper(av($_SERVER,'REQUEST_METHOD'));

	if(!isYtId($id)){
		http400(($id?'Invalid':'No') . ' youtube Id supplied');
	}

	set_time_limit(10);

	if($httpMethod==='HEAD'){
		debuglog("HEAD id=$id");
		setAudioHeader();
		die(0);
	}
	else if($httpMethod!=='GET'){
		http400('This supports only GET');
	}

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
		if(is_file($mp3)){
			if($fp=fopen($mp3,'rb')){
				strip($fp,$outputStream,NULL,$ranges);
				fclose($fp);
			}
			http500("Cannot open $mp3");
		}
		if($cache=fopen($mp3,'wb')){
			$exec=realpath(FFMPEG) . ' -i "' . $sourceURL . '" -ab 192000 -vn -ar 44100 -acodec mp3 -f mp3 -y pipe:1';
			if($proc=popen($exec,'rb')){
				strip($proc,$outputStream,$cache,$ranges);
				pclose($proc);
				die(0);
			}
			http500("Failed to start $exec");
		}
		http500("Failed to write to $mp3");
	}
	
	function setAudioHeader(){
		header('Content-Type: audio/mpeg');
		header('transferMode.dlna.org: Streaming');
		header('contentFeatures.dlna.org: DLNA.ORG_PN=MP3;DLNA.ORG_OP=01;DLNA.ORG_FLAGS=01700000000000000000000000000000');
	}

	//Obtains a video as MP3, downloads it, cuts it and outputs it.
	function getAndCut($id){
		$videourl="https://www.youtube.com/watch?v=$id";
		$mp3=getMp3file($id);
		if(is_file($mp3)){
			debuglog("Using cached file for id=$id");
			if($fp=fopen($mp3,'rb')){
				if($out=fopen('php://output','wb')){
					$segments=getRanges($id);
					if(!is_array($segments)){
						$segments=array();
					}
					setAudioHeader();
					strip($fp,$out,NULL,$segments);
					die(0);
				}
				http500('Failed to open STDOUT');
			}
			http500("Failed to read $mp3");
		}

		$stream=getStreamUrl($videourl);
		if(!$stream){
			http500('Failed to load youtube video. Check if Id is correct and video is unrestricted.');
		}
		debuglog("Got stream URL for id=$id");
		$segments=getRanges($id);
		if(!is_array($segments)){
			debuglog("Invalid SBlock answer for id=$id; Maybe has no ranges");
			$segments=array();
		}
		debuglog("id=$id has " . count($segments) . ' SBlock ranges');
		if($out=fopen('php://output','wb')){
			setAudioHeader();
			pipeAudio($stream,$out,$id,$segments);
			fclose($out);
			die(0);
		}
		http500('Failed to open STDOUT');
	}

	debuglog("GET id=$id");
	getAndCut($id);
	http500();