<?php
	$structure_links = array(
		'index'=>array('detail'=>'/Administrate/Announcements/detail/'.$atim_menu_variables['Group.id'].'/'.$atim_menu_variables['User.id'].'/%%Announcement.id%%/'),
		'bottom'=>array(
			'add'=>'/Administrate/Announcements/add/'.$atim_menu_variables['Group.id'].'/'.$atim_menu_variables['User.id'].'/'
		)
	);
	
	$this->Structures->build( $atim_structure, array('links'=>$structure_links) );
?>