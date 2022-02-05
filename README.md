# PHP Youtube Stream Library

php-ytstream: A PHP application that transcodes the audio of youtube videos for webradio devices.
Requires only very minimal setup.

## Notable Features

- The audio stream is delivered while being downloaded. No need to wait for it to finish on the server.
- Sends additional HTTP headers that allow streaming on DLNA devices.
- Deals with `HEAD` requests to further accelerate the start of the stream.
- Once streamed, the stream stays available, even if the original video is deleted.
- Removal of non-music sections using SponsorBlock

*Cache and SBlock can be disabled in the configuration*

## Setup

1. Put all PHP files into a directory on your webserver
2. Rename `config.example.php` into `config.php`
3. Edit `config.php` to match your setup
4. Access `index.php?id=dQw4w9WgXcQ` to test your installation

## Dependencies

- [youtube-dl](https://youtube-dl.org/)
- [ffmpeg](https://ffmpeg.org/)

Please install these dependencies and properly configure their paths in your configuration file.

## SponsorBlock

This application (provided you enable it) makes use of SponsorBlock (SBlock)
to cut non-music sections from music videos.

SBlock operates on a custom license that requires attribution.
See here for details:

https://github.com/ajayyy/SponsorBlock/wiki/Database-and-API-License

### License Notes

The license no logner applies to you if you:

- set `SB_ENABLE` to `FALSE`
- set `SB_API` to a server with a different license
- Do not publish php-ytstream

Note that php-ytstream uses the AGPL which considers SaaS/hosting a form of publishing

## Limitations

Because this is written in PHP, it has a few limitations.
Most notably, transcoding aborts if a client closes the connection.
This means that you will end up with a partial MP3 file in the cache.

You can disable the cache if you have unreliable clients connecting.
This has the implication that it takes a few seconds for the stream to start
because it takes time to obtain the stream URL.

## Quality

The MP3 streaming quality is 192 kbps using 44.1 kHz Joint Stereo.
There's not really any reason to increase this since youtube audio is already compressed a lot.

## Configuration

The following `config.php` configuration values are known:

*Unless otherwise stated, they're mandatory. Use config.example.php to get started.*

## FFMPEG

The path and file name to the ffmpeg executable

- Type: string
- Default: No default

## YTDL

The path and file name to the youtube-dl executable

- Type: string
- Default: No default

## USE_CACHE

Enable or disable the MP3 and SBlock cache

- Type: bool
- Default: TRUE

## CACHE

Base cache directory

- Type: string
- Default: "cache" in the directory where index.php is.

## SB_ENABLE

Enable or disable the use of SBlock to remove non-music sections

- Type: bool
- Default: TRUE

## SB_API

Set the SBlock API server address

- Type: string
- Default: sponsor.ajay.app

## SB_CACHE_LIFETIME

How long to keep successful SBlock answers in the cache (in seconds).
Note: The API will always be queried if no ranges were found.

- Type: number
- Default: 604800 (7 days)

## DEBUG

Enables debug logging to `debug.log` (same directory where index.php is located in)

- Type: bool
- Default: FALSE

# Technical Information

*This section is intended for developers*

## How it works

This chapter explains how acquisition and streaming works.

In short it's this:

1. Pipe audio data from youtube into ffmpeg to get MP3 formatted data
2. Pipe the output from ffmpeg into a cutting function to remove unwanted audio segments
3. Pipe the output from the cutting function to the client

A more detailed description including auxilliary steps is below.

### 1. Obtain the stream URL using youtube-dl

This is pretty straight forward.
The application calls youtube-dl using an argument to dump the json information
instead of actually downloading the streams.
An additional argument is supplied to make it select the best audio stream.
The json will then have the requested URL in the "url" property of the root object.

Note: This step is skipped if an MP3 exists already in the cache.

### 2. Obtain blocked ranges from SBlock

This is also very easy. We call the API at `/api/skipSegments?videoID=YT_ID_HERE&category=music_offtopic`
to get a json with all ranges that are non-music sections.
The sections contain two floating point values, these are the start and end of a segment we want to cut.
The value is in seconds.

Note: We do not select any other category because some categories (such as the end cards)
can overlap with the music, resulting in a harsh cut.

These non-music sections can be inside of the clip as well and not only at the start and end.
Example: Hypnodancer by little big (YT id: RhMYBfF7-hE)

### 3. Use ffmpeg to convert the stream to mp3

The input is the URL obtained in the first step and stdout is used as output.

Note: ffmpeg runs as fast as it can convert but this is usually not an issue.
The output stream will choke eventually when the client won't pull the data fast enough.
There is an ffmpeg argument to make it transcode at live stream speed,
but this will create problems if the next step cuts a lot of data.

Note: This step is skipped if an MP3 exists in the cache already.

### 4. Cut the MP3

This step strips audio frames inside of blockable ranges obtained in step 2 above.

The result is sent to the client. At the sime time, the uncut version is saved to the cache.

## About MP3

MP3 is notoriously primitive, which is not surprising considering how old it is.

The format consists of frames, which themselves are made up of 4 byte header,
followed by audio data that's a few milliseconds in length.

In other words, it's `header+audio+header+audio+header+audio+...`.

The format has a few properties that make it interesting for streaming purposes:

- It's a well known format that's supported by about every device that can play compressed audio
- Individual "header + audio" sections can be dropped without having to alter other sections
- There is no global header or footer in the file
- Calculating the length of audio frames does not require decoding the audio data

The downside is that an MP3 file can contain non-mp3 data which decoders have to deal with.

### MP3 Header

*This only documents the part of the header we're interested in*

The MP3 header consists of only 4 bytes.
It starts with the first 12 bits set, so the first two bytes are `FF-F?`.
An MP3 decoder will read until these bytes are encountered, discarding everything else.
The remaining 4 bits determine the mpeg version (1, 2, 2.5),
the layer type (1, 2, 3), and if CRC is in use.

Note: Mpeg 2.5 has only 11 bits set because the 12th is used as part of the mpeg version bitfield.

php-ytstream only deals with mpeg 1 layer 3 files which is the most common type.
This means that the first 15 bits are always the same,
only the last bit (CRC bit) differs, meaning only the sequences `FF-FA` and `FF-FB` are accepted header starts.

A few bit combinations inside of the remaining two bytes are considered invalid too.
For example, one frequency slot and two bitrate slots are not used.
If bits are set to these slots, php-ytstream will discard the header and look for the next.

**php-ytstream does not contain an MP3 audio decoder, only a header decoder.**

Data that's decoded from the header:

- Sample rate (4 bits)
- Sample frequency (2 bits)
- Padding bit (1 bit)
- Checksum bit (1 bit, this is part of the first two bytes, not the last two)

Data that is calculated from these values:

- Duration of the audio
- Number of bytes of audio in this block

Because these values are read from the header,
you can change the quality parameters of the ffmpeg invocation at any time.
You can even tell ffmpeg to output VBR data.

### Calculating Duration

An audio frame in version 1 layer 3 contains 1152 samples of audio.
The total time of audio material in a frame depends on the frequency and not the bitrate.
The duration is almost always around 26 ms because that's what you get for 44'100 Hz which is most commonly used.
Time in ms is `1152/sampleRateInHertz*1000`

With this value, php-ytstream can calculate exactly how far into the MP3 stream it is.
The cutting stage will read each MP3 frame, and if inside of a blockable range, silently discard it.
If outside of a blockable range, it's sent to the client.

Note: All frames are stored in the local cached copy regardless of the ranges,
meaning the local MP3 copy is always the full video.

### Calculating Audio Bytes

Calculating the audio data length is necessary to accurately cut entire frames.
Contrary to some other formats, stereo doesn't takes more space than mono.
Each channel just gets less bitrate.

As explained in the previous chapter,
every frame has a constant size for a given frequency.
This means that more or less bytes of data are in a frame, depending on the bitrate.

The length is calculated as follows:

1. Take the samples per frame (1152) and multiply by the bitrate (in bps, not kbps)
2. Divide by the sample rate (in Hz, not kHz) to get the total bits of data
3. Divide the result by 8 to convert from bits to bytes
4. Subtract 4 for the header we've already read
5. Add 1 if the padding bit is set
6. Add 2 if the protection bit is **unset**

The final result will not be an integer. Decimals are cut off.
Because decimals are cut off, an MP3 frame is technically a few bits too short.
To compensate for this, the padding bit can be set to get 8 bits back again.

Notes:

The padding bit contains audio data.
The protection is placed directly after the header
and is a CRC16 of the audio data. php-ytstream doesn't validates the checksum.
The checksum is almost never present.

# Todo

Additional features that may or may not be included

- Support for playlists
- Support for multiple ids in a single request (creating a concatenated output)
- Randomization of ids if multiple are provided
- Find a way to make it detect broken downloads which leave the cached file only partially complete
- Stream the MP3 with metadata
