<?php
	//Compares a timestamp (in seconds) against ranges obtained via getRanges()
	function inRange($pos,$ranges){
		foreach($ranges as $range){
			if($pos>=$range[0] && $pos<=$range[1]){
				return TRUE;
			}
		}
		return FALSE;
	}

	//Losslessly strips sections out of an MP3 file
	//The resulting MP3 file consists exclusively of audio blocks (no tags or other non-audio data)
	//$in:    Open stream to an MP3 file
	//$out:   Open stream where the cut MP3 is sent
	//$cache: Open stream where $in is piped to as-is for caching purposes
	//        (optional, can be NULL)
	//$mask:  array of arrays, each inner array has two numbers which represent start and end timestamps
	//        (optional, can be NULL to not cut anything)
	function strip($in,$out,$cache,$mask){
		if(!is_array($mask)){
			$mask=array();
		}
		debuglog("Stripping segments");
		if($cache){
			debuglog("Writing to cached mp3");
		}
		$total=0;
		while(!feof($in)){
			//Extend time for as long as we can stream data
			//This gets called for every MP3 frame which is a few milliseconds,
			//so 30 is very generous actually.
			set_time_limit(30);
			$buffer=fread($in,4);
			//find MP3 header. This reads individual bytes until a header is detected
			while(!isHeader($buffer) && !feof($in)){
				$buffer=substr($buffer,1) . fgetc($in);
			}
			//If we're at the end here the input stream lacks MP3 frames
			if(!feof($in)){
				$hdr=decodeHeader($buffer);
				//Not a valid header if it has no data
				if($hdr['datalen']>0){
					$audio=fread($in,$hdr['datalen']);
					if(count($mask)===0 || !inRange($total/1000,$mask)){
						fwrite($out,$buffer . $audio);
					}
					//Always write to cache the uncut MP3
					if($cache){
						fwrite($cache,$buffer . $audio);
					}
					$total+=$hdr['duration'];
				}
			}
		}
		if($cache){
			debuglog("Cutting operation complete");
		}
		//Return total runtime in milliseconds
		echo $total;
	}