<?php 
	$structure_links = array(
		'index'=>array('detail'=>'/Administrate/StructureFormats/detail/'.$atim_menu_variables['Structure.id'].'/%%StructureFormat.id%%'),
	);
	
	$this->Structures->build( $atim_structure, array('links'=>$structure_links) );
?>