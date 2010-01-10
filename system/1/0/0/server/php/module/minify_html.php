<?php
/**
 * Class Minify_HTML  
 * @package Minify
 */

/**
 * Compress HTML
 *
 * This is a heavy regex-based removal of whitespace, unnecessary comments and 
 * tokens. IE conditional comments are preserved. There are also options to have
 * STYLE and SCRIPT blocks compressed by callback functions. 
 * 
 * A test suite is available.
 * 
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */

	class System_1_0_0_Minify_Html
	{
	/**
	 * "Minify" an HTML page
	 *
	 * @param string $html
	 *
	 * @param array $options
	 *
	 * 'cssMinifier' : (optional) callback function to process content of STYLE
	 * elements.
	 * 
	 * 'jsMinifier' : (optional) callback function to process content of SCRIPT
	 * elements. Note: the type attribute is ignored.
	 * 
	 * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
	 * unset, minify will sniff for an XHTML doctype.
	 * 
	 * @return string

		public static function minify($html, $options = array())
		{
			$min = new System_1_0_0_Htmlmin($html, $options);
			return $min->process();
		}
	 */

	/**
	 * Create a minifier object
	 *
	 * @param string $html
	 *
	 * @param array $options
	 *
	 * 'cssMinifier' : (optional) callback function to process content of STYLE
	 * elements.
	 * 
	 * 'jsMinifier' : (optional) callback function to process content of SCRIPT
	 * elements. Note: the type attribute is ignored.
	 * 
	 * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
	 * unset, minify will sniff for an XHTML doctype.
	 * 
	 * @return null
	 */
		public function __construct($html, $options = array())
		{
			$this->_html = str_replace("\r\n", "\n", trim($html));

			if(isset($options['xhtml'])) $this->_isXhtml = (bool)$options['xhtml'];
			if(isset($options['cssMinifier'])) $this->_cssMinifier = $options['cssMinifier'];
			if(isset($options['jsMinifier'])) $this->_jsMinifier = $options['jsMinifier'];
		}

	/**
	 * Minify the markeup given in the constructor
	 * 
	 * @return string
	 */

		public function process()
		{
			if($this->_isXhtml === null) $this->_isXhtml = (false !== strpos($this->_html, '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML'));

			$this->_replacementHash = 'MINIFYHTML'.md5($_SERVER['REQUEST_TIME']);
			$this->_placeholders = array();

			#Replace SCRIPTs (and minify) with placeholders
			$this->_html = preg_replace_callback('/(\\s*)(<script\\b[^>]*?>)([\\s\\S]*?)<\\/script>(\\s*)/i', array($this, '_removeScriptCB'), $this->_html);

			#Replace STYLEs (and minify) with placeholders
			$this->_html = preg_replace_callback('/\\s*(<style\\b[^>]*?>)([\\s\\S]*?)<\\/style>\\s*/i', array($this, '_removeStyleCB'), $this->_html);

			#Remove HTML comments (not containing IE conditional comments).
			$this->_html = preg_replace_callback('/<!--([\\s\\S]*?)-->/', array($this, '_commentCB'), $this->_html);

			#Replace PREs with placeholders
			$this->_html = preg_replace_callback('/\\s*(<pre\\b[^>]*?>[\\s\\S]*?<\\/pre>)\\s*/i', array($this, '_removePreCB'), $this->_html);

			#Replace TEXTAREAs with placeholders
			$this->_html = preg_replace_callback('/\\s*(<textarea\\b[^>]*?>[\\s\\S]*?<\\/textarea>)\\s*/i', array($this, '_removeTextareaCB'), $this->_html);

			#Trim each line.
			$this->_html = preg_replace('/^\\s+|\\s+$/m', '', $this->_html); #TODO - take into account attribute values that span multiple lines.

			#Remove ws around block/undisplayed elements
			$this->_html = preg_replace('/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body'
				.'|caption|center|cite|col(?:group)?|dd|dir|div|dl|dt|fieldset|form'
				.'|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta'
				.'|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)'
				.'|ul)\\b[^>]*>)/i', '$1', $this->_html);

			#Remove ws outside of all elements
			$this->_html = preg_replace_callback('/>([^<]+)</', array($this, '_outsideTagCB'), $this->_html);

			#Use newlines before 1st attribute in open tags (to limit line lengths)
			$this->_html = preg_replace('/(<[a-z\\-]+)\\s+([^>]+>)/i', "$1\n$2", $this->_html);

			#Fill placeholders
			return $this->_html = str_replace(array_keys($this->_placeholders), array_values($this->_placeholders), $this->_html);
		}

		protected function _commentCB($m) { return (0 === strpos($m[1], '[') || false !== strpos($m[1], '<![')) ? $m[0] : ''; }

		protected function _reservePlace($content)
		{
			$placeholder = '%'.$this->_replacementHash.count($this->_placeholders).'%';
			$this->_placeholders[$placeholder] = $content;

			return $placeholder;
		}

		protected $_isXhtml = null;

		protected $_replacementHash = null;

		protected $_placeholders = array();

		protected $_cssMinifier = null;

		protected $_jsMinifier = null;

		protected function _outsideTagCB($m) { return '>'.preg_replace('/^\\s+|\\s+$/', ' ', $m[1]).'<'; }

		protected function _removePreCB($m) { return $this->_reservePlace($m[1]); }

		protected function _removeTextareaCB($m) { return $this->_reservePlace($m[1]); }

		protected function _removeStyleCB($m)
		{
			$css = $this->_removeCdata(preg_replace('/(?:^\\s*<!--|-->\\s*$)/', '', $m[2])); #Remove HTML comments and CDATA section markers
			$css = call_user_func($this->_cssMinifier ? $this->_cssMinifier : 'trim', $css); #Minify

			return $this->_reservePlace($this->_needsCdata($css) ? "{$m[1]}/*<![CDATA[*/{$css}/*]]>*/</style>" : "{$m[1]}{$css}</style>");
		}

		protected function _removeScriptCB($m)
		{
			$openScript = $m[2];
			$js = $m[3];

			#Whitespace surrounding? preserve at least one space
			$ws1 = ($m[1] === '') ? '' : ' ';
			$ws2 = ($m[4] === '') ? '' : ' ';
	 
			$js = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/', '', $js); #Remove HTML comments (and ending "//" if present)
			$js = $this->_removeCdata($js); #Remove CDATA section markers

			$js = call_user_func($this->_jsMinifier ? $this->_jsMinifier : 'trim', $js); #Minify
			return $this->_reservePlace($this->_needsCdata($js) ? "{$ws1}{$openScript}/*<![CDATA[*/{$js}/*]]>*/</script>{$ws2}" : "{$ws1}{$openScript}{$js}</script>{$ws2}");
		}

		protected function _removeCdata($str) { return (false !== strpos($str, '<![CDATA[')) ? str_replace(array('<![CDATA[', ']]>'), '', $str) : $str; }

		protected function _needsCdata($str) { return ($this->_isXhtml && preg_match('/(?:[<&]|\\-\\-|\\]\\]>)/', $str)); }
	}
?>
