<?php 
	$structure_links = array(
		'top' => '/Administrate/AdminUsers/edit/'.$atim_menu_variables['Group.id'].'/'.$atim_menu_variables['User.id'].'/',
		'bottom'=>array('cancel'=>'/Administrate/AdminUsers/detail/'.$atim_menu_variables['Group.id'].'/'.$atim_menu_variables['User.id'].'/')
	);
	
	$final_atim_structure = $atim_structure; 
	$final_options = array('links' => $structure_links, 'type' => 'edit');
	
	$hook_link = $this->Structures->hook();
	if( $hook_link ) {
		require($hook_link); 
	}
		
	$this->Structures->build( $final_atim_structure, $final_options );
	
?>