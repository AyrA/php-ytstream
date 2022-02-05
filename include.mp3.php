<?php
	//This checks if the given bytes represent an MP3 header (mpeg 1 layer III only)
	function isHeader($buffer){
		return
			strlen($buffer)===4 &&
			$buffer[0]==="\xFF" &&
			$buffer[1]==="\xFB";
	}
	//Decodes an MP3 header
	//Note: Decodes only bits necessary for editing and size computation
	function decodeHeader($buffer){
		if(!isHeader($buffer)){
			return FALSE;
		}
		//This number is type specific, but for mpeg 1 layer III it's always 1152
		$samplesPerFrame=1152;
		//Possible bitrates. Zero means it's for private use or not assigned. 4 bits
		$brates=array(0,32000,40000,48000,56000,64000,80000,96000,112000,128000,160000,192000,224000,256000,320000,0);
		//Sample rates. Zero is invalid. 2 bits
		$srates=array(44100,48000,32000,0);
		//Protection bit. If **unset** there are 2 bytes of additional data after the header for CRC16
		$prot=(ord($buffer[1])&1)===0;
		//Read bitrate, samplerate and padding bits
		$num=ord($buffer[2]);
		$br=($num&0b11110000)>>4;
		$sr=($num&0b1100)>>2;
		$pad=($num&0b10)===0b10;

		//Convert the extracted bits into usable information
		$ret=array();
		$ret['protected']=$prot;
		$ret['padding']=$pad;
		$ret['bitrate']=$brates[$br];
		$ret['samplerate']=$srates[$sr];
		//Do not calculate length and duration if the header is invalid
		if($ret['samplerate']===0 || $ret['bitrate']===0){
			$ret['datalen']=0;
			$ret['duration']=0;
		}
		else{
			//Data length includes the header, checksum, and padding.
			//We subtract 4 bytes for the header but add 2 for the checksum if it's present.
			//We also factor in the potential padding byte.
			//
			//Note: The result is not an integer and is always rounded down.
			//Because of this, the bitrate is lower than it should be.
			//The padding byte is used to compensate for this problem.
			//The padding byte contains a valid audio sample.
			$ret['datalen']=floor(($samplesPerFrame/8)*$brates[$br]/$srates[$sr]+($pad?1:0))-4+($prot?2:0);
			//Formula for duration: https://stackoverflow.com/questions/6220660
			//Note: Because this doesn't depends on the bitrate, the duration is almost always around 26 ms
			//Because that's what you get for 44.1kHz which is most commonly used.
			$ret['duration']=$samplesPerFrame / $srates[$sr] * 1000;
		}
		return $ret;
	}