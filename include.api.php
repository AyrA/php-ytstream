<?php
	//uses YTDL to obtain the stream URL with best audio quality.
	//We do not care about the video stream
	function getStreamUrl($yt){
		set_time_limit(10);
		$exec=realpath(YTDL) . " --skip-download --dump-json --format bestaudio $yt";
		exec($exec,$output);
		$json=json_decode(implode(PHP_EOL,$output),TRUE);
		if(json_last_error()===JSON_ERROR_NONE){
			return av($json,'url',FALSE);
		}
		return FALSE;
	}
	
	//Obtains blockable ranges from SponsorBlock
	//Returns the ranges in the format they're expected by the cutting functions.
	function getRanges($id){
		//Don't actually bother if we're not using SponsorBlock
		if(!SB_ENABLE){
			return array();
		}
		if(!isYtId($id)){
			return FALSE;
		}
		set_time_limit(10);
		$cache=FALSE;
		$data=getSegCache($id);
		$cache=$data!==FALSE;
		//Download if cache not present or out of date
		if(!$cache){
			$url='https://' . SB_API . '/api/skipSegments?videoID=' . urlencode($id) . '&category=music_offtopic';
			$data=@file_get_contents($url);
		}
		//Download and cache failed if FALSE
		if($data!==FALSE){
			$data=json_decode($data,TRUE);
			if(json_last_error()===JSON_ERROR_NONE && is_array($data)){
				if(!$cache){
					if(!setSegCache($id,$data)){
						http500("Failed to save json data to $file");
					}
				}
				$ret=array();
				foreach($data as $seg){
					$range=av($seg,'segment');
					if(is_array($range) && count($range)===2){
						$ret[]=$range;
					}
				}
				return $ret;
			}
		}
		return FALSE;
	}