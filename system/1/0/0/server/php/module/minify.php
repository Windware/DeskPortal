<?php
	class System_1_0_0_Minify
	{
		public static function css(&$system, $text) //Minify style sheets
		{
			if(!$system->is_text($text)) return '';
			if(!$system->app_conf('system', 'static', 'minify')) return $text;

			$system->file_load("{$system->system['root']}server/php/module/minify_css.php"); //Load the library manually

			$minifier = new System_1_0_0_Minify_Css($text);
			return $minifier->_process();
		}

		public static function html(&$system, $text) //Minify HTML (and included CSS/JS)
		{
			if(!$system->is_text($text)) return '';
			if(!$system->app_conf('system', 'static', 'minify')) return $text;

			foreach(array('html', 'css', 'js') as $section) $system->file_load("{$system->system['root']}server/php/module/minify_$section.php"); //Load the library manually

			$external = array('jsMinifier' => array('System_1_0_0_Minify_Js', 'minify'), 'cssMinifier' => array('System_1_0_0_Minify_Css', 'minify'));
			$minifier = new System_1_0_0_Minify_Html($text, $external);

			return $minifier->process();
		}

		public static function js(&$system, $text) //Minify JavaScript
		{
			if(!$system->is_text($text)) return '';
			if(!$system->app_conf('system', 'static', 'minify')) return $text;

			$system->file_load("{$system->system['root']}server/php/module/minify_js.php"); //Load the library manually

			$minifier = new System_1_0_0_Minify_Js($text);
			return $minifier->min();
		}
	}
