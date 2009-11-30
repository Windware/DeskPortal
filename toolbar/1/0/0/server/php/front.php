<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache any of the results

	switch($_GET['task'])
	{
		case 'gui.create' : #Keep the bar
			print $system->xml_send(Toolbar_1_0_0::set($_POST['index'], true));
		break;

		case 'gui.save' : #Remember the choice
			$result = Toolbar_1_0_0::selection($_POST['index'], $_POST['feature'], $_POST['method'], $_POST['source'], $_POST['target']);
			print $system->xml_send($result !== false, $result);
		break;

		case 'gui.remove' : #Remove the bar
			print $system->xml_send(Toolbar_1_0_0::set($_POST['index'], false));
		break;

		case 'gui.set' : #Get the choice
			$data = Toolbar_1_0_0::selection($_GET['index']);
			print $system->xml_send($data !== false, $data);
		break;

		case 'run' : #Get list of opened bars
			$data = Toolbar_1_0_0::get();
			print $system->xml_send($data !== false, $data);
		break;
	}
?>
