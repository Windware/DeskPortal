<?php
$system = new System_1_0_0(__FILE__);
$system->cache_header(0); #Do not cache any of the results

switch($_GET['task'])
{
	case 'gui.create' : #Keep the bar
		$result = Toolbar_1_0_0::set($_POST['index'], true);
		print $system->xml_dump($result);
	break;

	case 'gui.remove' : #Remove the bar
		$result = Toolbar_1_0_0::set($_POST['index'], false);
		print $system->xml_dump($result);
	break;

	case 'gui.save' : #Remember the choice
		$data = Toolbar_1_0_0::selection($_POST['index'], $_POST['feature'], $_POST['method'], $_POST['source'], $_POST['target']);
		print $system->xml_dump($data !== false, 'select', [$data]);
	break;

	case 'gui.set' : #Set the bar options
		$data = Toolbar_1_0_0::selection($_GET['index']);
		print $system->xml_dump($data !== false, 'select', [$data]);
	break;

	case 'run' : #Get list of opened bars
		$data = Toolbar_1_0_0::get();
		print $system->xml_dump($data !== false, 'bar', [['index' => implode(',', $data)]]);
	break;
}
