<?php 

	$structure_links = array(
		'top'=>'/ClinicalAnnotation/TreatmentMasters/edit/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id'],
	);
	
	// 1- TRT
	$structure_settings = array(
		'actions'		=> false, 
		'header' 		=> __('data', null),
		'form_bottom'	=> false
	);
	
			 	
	$final_options = array(
		'links'		=> $structure_links,
		'settings'	=> $structure_settings 
	);
	$final_atim_structure = $atim_structure; 
	
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { 
		require($hook_link); 
	}
	
	$this->Structures->build( $final_atim_structure, $final_options );	
	
	// 2- SEPARATOR & HEADER
	
	$structure_settings = array(
		'header' 		=> __('related diagnosis', null),
		'form_top' 		=> false,
		'tree'			=> array('DiagnosisMaster' => 'DiagnosisMaster'),
		'form_inputs'	=> false
	);
	
	// Define radio should be checked
	
	$structure_links = array(
		'top'=>'/ClinicalAnnotation/TreatmentMasters/edit/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id'],
		'tree'	=> array(
			'DiagnosisMaster' => array(
				'radiolist' => array('TreatmentMaster.diagnosis_master_id' => '%%DiagnosisMaster.id'.'%%') 
			)
		), 'bottom' => array(
			'cancel' => '/ClinicalAnnotation/TreatmentMasters/detail/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id']
		)
	);

	$final_options = array(
		'type'		=> 'tree',
		'data'		=> $data_for_checklist, 
		'settings'	=> $structure_settings,
		'links'		=> $structure_links,
		'extras' 	=> array('start' => '<input type="radio" name="data[TreatmentMaster][diagnosis_master_id]" '.(!$radio_checked ? ' checked="checked"' : '').' value=""/>'.__('n/a', null))
	);
	
	$final_atim_structure = array('DiagnosisMaster' => $diagnosis_structure);

	$display_next_sub_form = true;
	
	$hook_link = $this->Structures->hook('dx_list');
	if($hook_link){
		require($hook_link);
	}	
	
	if($display_next_sub_form) $this->Structures->build($final_atim_structure, $final_options);
?>
<script>
var treeView = true;
</script>