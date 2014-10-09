<?php
/*****
PLASER_xml_common class
*****/

class PLASER_xml_common
{
	public $elem_index = 'item'; //Element name for the array numeric index in mixed2xml()

	//TODO It should use 'Util/php2xml.php'
	function mixed2xml($var, $utf8e = false)
	{
		//BEGIN: One-time-block This block only works the first time no inside the recursive loop
		if( !( is_array($var) || is_object($var) ) ) //is_scalar
		{
			if($utf8e) $var = utf8_encode($var);
			return htmlspecialchars($var, ENT_QUOTES, "UTF-8");
		}
		//END: One-time-block

		$xml = '';
		if(is_object($var)) $var = get_object_vars($var);
		foreach($var as $key => $value)
		{
			if(is_array($value) || is_object($value)) $tmpv = $this->mixed2xml($value, $utf8e);
			else $tmpv = htmlspecialchars($value, ENT_QUOTES, "UTF-8");
			if(is_numeric($key)) $tmpk = $this->elem_index;
			else $tmpk = $key;
			$tmpk = trim($tmpk);
			//$tmpv = trim($tmpv);
			if($utf8e) $tmpv = utf8_encode($tmpv);
			$xml .= '<'.$tmpk.'>'.$tmpv.'</'.$tmpk.'>';
		}
		return $xml;
	}

	//Returns true if $string is valid UTF-8 and false otherwise
	function is_utf8($string)
	{
		return preg_match('%(?:
		[\xC2-\xDF][\x80-\xBF] # non-overlong 2-byte
		|\xE0[\xA0-\xBF][\x80-\xBF] # excluding overlongs
		|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
		|\xED[\x80-\x9F][\x80-\xBF] # excluding surrogates
		|\xF0[\x90-\xBF][\x80-\xBF]{2} # planes 1-3
		|[\xF1-\xF3][\x80-\xBF]{3} # planes 4-15
		|\xF4[\x80-\x8F][\x80-\xBF]{2} # plane 16
		)+%xs', $string);
	}

}
?>
