<?php 

	$structure_links = array(
		'index'=>array(
			'detail'=>'/ClinicalAnnotation/ReproductiveHistories/detail/'.$atim_menu_variables['Participant.id'].'/%%ReproductiveHistory.id%%/',
			'edit'=>'/ClinicalAnnotation/ReproductiveHistories/edit/'.$atim_menu_variables['Participant.id'].'/%%ReproductiveHistory.id%%/',
			'delete'=>'/ClinicalAnnotation/ReproductiveHistories/delete/'.$atim_menu_variables['Participant.id'].'/%%ReproductiveHistory.id%%/'
		),
		'bottom'=>array(
			'add'=>'/ClinicalAnnotation/ReproductiveHistories/add/'.$atim_menu_variables['Participant.id'].'/'
		)
	);
	
	// Set form structure and option 
	$final_atim_structure = $atim_structure; 
	$final_options = array('type'=>'index','links'=>$structure_links);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );

?>