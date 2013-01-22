<?php
$structure_links = array(
	'top'	=> '/InventoryManagement/AliquotMasters/editSourceAliquot/'.$atim_menu_variables['SampleMaster.id'].'/'.$atim_menu_variables['AliquotMaster.id'].'/',
	'bottom'=> array('cancel' => '/InventoryManagement/SampleMasters/detail/'.$atim_menu_variables['Collection.id'].'/'.$atim_menu_variables['SampleMaster.id'])	
);

if(!$show_submit_button) unset($structure_links['top']);

$final_atim_structure = $atim_structure; 
$final_options =  array(
	'type'		=> 'edit', 
	'links'		=> $structure_links,
	'settings'	=> array('header'		=> __('listall source aliquots'))
);

// CUSTOM CODE
$hook_link = $this->Structures->hook();
if( $hook_link ) { 
	require($hook_link); 
}
	
// BUILD FORM
$this->Structures->build( $final_atim_structure, $final_options );	
