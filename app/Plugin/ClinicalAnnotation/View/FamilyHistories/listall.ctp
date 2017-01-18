<?php 

	$structure_links = array(
		'index'=>array(
			'detail'=>'/ClinicalAnnotation/FamilyHistories/detail/'.$atim_menu_variables['Participant.id'].'/%%FamilyHistory.id%%/',
			'edit'=>'/ClinicalAnnotation/FamilyHistories/edit/'.$atim_menu_variables['Participant.id'].'/%%FamilyHistory.id%%/',
			'delete'=>'/ClinicalAnnotation/FamilyHistories/delete/'.$atim_menu_variables['Participant.id'].'/%%FamilyHistory.id%%/'
		),
		'bottom'=>array(
			'add'		=> '/ClinicalAnnotation/FamilyHistories/add/'.$atim_menu_variables['Participant.id']
		)
	);
	
	// Set form structure and option 
/* ==> Note: Set both form structure and option into 2 variables to allow hooks to modify them */
	$final_atim_structure = $atim_structure; 
	$final_options = array('type'=>'index','links'=>$structure_links);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );

?>