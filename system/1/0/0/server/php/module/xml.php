<?php

class System_1_0_0_Xml
{
	public static function build(&$system, $data, $exclude = []) //Create a XML component from an array
	{
		$xml = '';
		if(!is_array($data) || !is_array($exclude)) return $xml;

		//Iterate over the array and create the XML component by excluding keys from the exclusion list
		foreach($data as $key => $value) if(!in_array($key, $exclude)) $xml .= "<$key>$value</$key>\n";

		return $xml; //Return the built XML
	}

	public static function data(&$system, $string) //Create a safely packed CDATA section from a string
	{
		if(!is_string($string)) return '';
		return '<![CDATA[' . str_replace([']]&', ']]>'], [']]&amp;', ']]&gt;'], $string) . ']]>'; //Replace the parts that overlap with CDATA declaration
	}

	public static function dump(&$system, $status, $name = null, $list = [], $exclude = [], $compress = false) //Create lines of XML from an array ready for output
	{
		$xml = '';

		if($system->is_text($name) && is_array($list)) foreach($list as $row) $xml .= $system->xml_node($name, $row, null, $exclude);
		return $system->xml_send($status, $xml, null, !!$compress);
	}

	public static function entity(&$system, $string) //Converts HTML entity names into XML numeric entities
	{
		if(!$system->is_text($string)) return '';

		$named = '&quot; &amp; &lt; &gt; &nbsp; &iexcl; &cent; &pound; &curren; &yen; &brvbar; &sect; &uml; &copy; &ordf; &laquo; &not; &shy; &reg; &macr; &deg; &plusmn; &sup2; &sup3; &acute; &micro; &para; &middot; &cedil; &sup1; &ordm; &raquo; &frac14; &frac12; &frac34; &iquest; &Agrave; &Aacute; &Acirc; &Atilde; &Auml; &Aring; &AElig; &Ccedil; &Egrave; &Eacute; &Ecirc; &Euml; &Igrave; &Iacute; &Icirc; &Iuml; &ETH; &Ntilde; &Ograve; &Oacute; &Ocirc; &Otilde; &Ouml; &times; &Oslash; &Ugrave; &Uacute; &Ucirc; &Uuml; &Yacute; &THORN; &szlig; &agrave; &aacute; &acirc; &atilde; &auml; &aring; &aelig; &ccedil; &egrave; &eacute; &ecirc; &euml; &igrave; &iacute; &icirc; &iuml; &eth; &ntilde; &ograve; &oacute; &ocirc; &otilde; &ouml; &divide; &oslash; &ugrave; &uacute; &ucirc; &uuml; &yacute; &thorn; &yuml; &euro;';
		$numeric = '&#34; &#38; &#60; &#62; &#160; &#161; &#162; &#163; &#164; &#165; &#166; &#167; &#168; &#169; &#170; &#171; &#172; &#173; &#174; &#175; &#176; &#177; &#178; &#179; &#180; &#181; &#182; &#183; &#184; &#185; &#186; &#187; &#188; &#189; &#190; &#191; &#192; &#193; &#194; &#195; &#196; &#197; &#198; &#199; &#200; &#201; &#202; &#203; &#204; &#205; &#206; &#207; &#208; &#209; &#210; &#211; &#212; &#213; &#214; &#215; &#216; &#217; &#218; &#219; &#220; &#221; &#222; &#223; &#224; &#225; &#226; &#227; &#228; &#229; &#230; &#231; &#232; &#233; &#234; &#235; &#236; &#237; &#238; &#239; &#240; &#241; &#242; &#243; &#244; &#245; &#246; &#247; &#248; &#249; &#250; &#251; &#252; &#253; &#254; &#255; &#8364;';

		return str_replace(explode(' ', $named), explode(' ', $numeric), $string);
	}

	public static function header(&$system, $declare = true) //Return the XML header and send xml content type header
	{
		if($declare) //If set to delare the HTTP header
		{
			$log = $system->log(__METHOD__);
			$log->dev(LOG_INFO, 'Declaring the output as XML');

			if(!headers_sent()) header('Content-Type: text/xml; charset=utf-8'); //Send the HTTP content type header
			else $log->dev(LOG_ERR, 'Cannot send text/xml header', 'Do not send content before sending headers');
		}

		//NOTE : Concatenate the XML header string not to trick editors into thinking it's start of a XML from here
		return '<' . '?xml version="1.0" encoding="utf-8"?' . ">\n"; //Send the XML header string
	}

	public static function fill(&$system, $template, $values) //Insert values into XML template string with the passed array
	{
		if(!is_string($template) || !is_array($values)) return $template;
		return preg_replace('/%(\w+?)%/e', '$values["$1"]', $template); //Replace the signs with given values
	}

	//Create a complete XML from XML contents, send out XML content header if specified at '$declare' as true
	public static function format(&$system, $content, $declare = false)
	{
		static $_template; //Keep the XML template in memory instead of loading it everytime accessed

		if(!is_string($content)) return '';
		return $system->xml_header($declare) . "<root>\n$content\n</root>\n"; //Fill in the string in the template
	}

	public static function node(&$system, $name, $params, $child = null, $exclude = null) //Create a XML node from a hash
	{
		if($exclude === null) $exclude = [];

		if(!$system->is_text($name) || !is_array($exclude)) return '';
		if(!is_array($params)) $params = [];

		$name = htmlspecialchars($name);
		$xml = "\t<$name"; //Node name

		foreach($params as $key => $value) //TODO - Check for the key and value data types
		{
			if(in_array($key, $exclude)) continue; //Leave the excluded items

			//Turn newline into a character instead
			$key = str_replace("\n", '\\n', $key);
			$value = str_replace("\n", '\\n', $value);

			$xml .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"'; //Add the attributes
		}

		if(!is_string($child)) $xml .= " />\n"; //End of a XML node
		else $xml .= ">$child</$name>\n"; //Add a child node if specified

		return $xml;
	}

	public static function output(&$system, $body, $compressed = false) //Add XML header to the crafted XML component and compress it if possible
	{
		$output = $system->xml_format($body, true); //Create the XML component
		if($compressed) $output = $system->compress_output($output); //Compress if specified

		if($output === false) return ''; //On error, return an empty string

		if(!headers_sent()) header('Content-Length: ' . strlen($output)); //Send the content length header if possible
		else
		{
			$log = $system->log(__METHOD__);
			$log->dev(LOG_WARNING, 'Cannot send content length header', 'Do not send content before sending headers');
		}

		return $output; //Return the formatted XML
	}

	public static function send(&$system, $status = false, $result = null, $key = null, $compressed = false) //Outputs the status XML line along with the given XML
	{
		if($status === true) $status = 0;
		elseif($status === false) $status = 1;

		$state = $system->xml_status($status, $key);
		return $system->xml_output($system->is_text($result) ? $state . $result : $state, $compressed);
	}

	public static function status(&$system, $value, $key = null) //Create a simple status XML entries
	{ //TODO - Escape the strings for XML or forbid bad characters
		$key = $system->is_text($key) ? " name=\"$key\"" : ''; //Add the key entry if specified
		return "\t<status$key value=\"$value\" />\n"; //Return the crafted XML piece
	}
}
