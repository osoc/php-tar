<?php

/*
   php-tar is a single PHP class that abstracts a tar archive (gzipped
   tar archives are supported as well, but only as far as PHP's Zlib
   functions. Documentation is scattered throughout the source file. Scoot
   around and have a good time.
 */

class Tar {

	const HDR_PACK_FORMAT = 'a100' /* 'name' => file name */
				. 'a8' /* 'mode' => file mode */
				. 'a8' /* 'uid' => numeric uid */
				. 'a8' /* 'gid' => numeric gid */
				. 'a12' /* 'size' => size in bytes */
				. 'a12' /* 'mtime' => modification time */
				. 'a8' /* 'checksum' => checksum */
				. 'C' /* 'type' => type indicator */
				. 'a100' /* 'link' => name of linked file */
				. 'a6' /* 'ustar' => UStar indicator */
				. 'a2' /* 'uver' => UStar version */
				. 'a32' /* 'owner' => owner name */
				. 'a32' /* 'group' => group name */
				. 'a8' /* 'major' => device major */
				. 'a8' /* 'minor' => device minor */
				. 'a155' /* 'nameprefix' => file name prefix */
				;

	public function __construct() {
		$this->compressed = TRUE;
		$this->files = array();
	}

	public function load($filename) {
		$zf = gzopen($filename, "rb");

		if ($zf === FALSE) {
			trigger_error("Tar::load: Could not open $filename for loading");
			return FALSE;
		}

		/* XXX: there should be a way to determine this by magic
		   numbers or something */
		$this->compressed = (strtoupper(substr($filename, -2)) == "GZ");

		while ($this->load_one($zf)) {
			/* no-op */
		}

		gzclose($zf);
	}

	public function load_record($zf) {
		return gzread($zf, 512);
	}

	public function header_sum_check($data, $refsum) {
		$sum_unsigned = 0;
		$sum_signed = 0;

		for ($i=0; $i<0x200; $i++) {
			$c = ord(substr($data, $i, 1));
			$sum_unsigned += $c;
			$sum_signed += ($c > 127 ? $c - 256 : $c); /* XXX? */
		}

		return $refsum == $sum_unsigned || $refsum == $sum_signed;
	}

	public function header_read($hdr_data) {
		$hdr_info = array('ustar' => '', 'uver' => '00',
				'owner' => '', 'group' => '', 'major' => 0,
				'minor' => 0, 'nameprefix' => '');

		$hdr_data = str_pad($hdr_data, 512, "\0");

		$hdr_unpacked = unpack(Tar::HDR_PACK_FORMAT, $hdr_data);

		if (!$this->header_sum_check($hdr_data, octdec($hdr_unpacked[6])))
			return FALSE;

		$hdr_info['name'] = $hdr_unpacked[0];
		$hdr_info['mode'] = octdec($hdr_unpacked[1]);
		$hdr_info['uid'] = octdec($hdr_unpacked[2]);
		$hdr_info['gid'] = octdec($hdr_unpacked[3]);
		$hdr_info['size'] = octdec($hdr_unpacked[4]);
		$hdr_info['mtime'] = octdec($hdr_unpacked[5]);
		$hdr_info['checksum'] = octdec($hdr_unpacked[6]);
		$hdr_info['type'] = $hdr_unpacked[7];
		$hdr_info['link'] = $hdr_unpacked[8];

		if ($hdr_info['checksum'] != $sum)

		if ($hdr_unpacked[9] == 'ustar') {
			$hdr_info['ustar'] = $hdr_unpacked[9];
			$hdr_info['uver'] = $hdr_unpacked[10];
			$hdr_info['owner'] = $hdr_unpacked[11];
			$hdr_info['group'] = $hdr_unpacked[12];
			$hdr_info['major'] = octdec($hdr_unpacked[13]);
			$hdr_info['minor'] = octdec($hdr_unpacked[14]);
			$hdr_info['nameprefix'] = $hdr_unpacked[15];
		}

		return $hdr_info;
	}

	public function load_one($zf) {
		$hdr_data = $this->load_record($zf);
		if (strlen($hdr_data) < 0x200 || $hdr_data == str_repeat("\0\0\0\0\0\0\0\0", 64))
			return FALSE;

		$file = $this->header_read($this->load_record($zf));
		if ($file === FALSE)
			return FALSE;
	}

}

?>
