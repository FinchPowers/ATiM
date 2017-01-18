<?php 

	$structure_links = array(
		'bottom'=>array(
			'edit'=>'/ClinicalAnnotation/TreatmentMasters/edit/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id'],
			'delete'=>'/ClinicalAnnotation/TreatmentMasters/delete/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id']
		)
	);
	
	// TRT DATA
	
	$structure_settings = array(
		'actions'=> ($is_ajax && !isset($extend_form_alias)), 
		'form_bottom'=> !($is_ajax && !isset($extend_form_alias)) 
	);
	
	$structure_override = array();
	
	$final_options = array('links'=>$structure_links,'settings'=>$structure_settings,'override'=>$structure_override);
	$final_atim_structure = $atim_structure; 
	
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { 
		require($hook_link); 
	}
	
	$this->Structures->build( $final_atim_structure, $final_options );

	if(isset($extend_form_alias)){
		$structure_settings = array(
			'pagination'	=> false,
			'actions'		=> $is_ajax,
			($is_ajax? 'language_heading' : 'header')		=> ($tx_extend_type? __($tx_extend_type) : __('precision'))
		);
		
		$structure_links['bottom']['add'][($tx_extend_type? __($tx_extend_type) : __('add precision'))] = array(
			'link' => '/ClinicalAnnotation/TreatmentExtendMasters/add/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id'],
			'icon' => 'treatment precision');
		if(isset($extended_data_import_process)){
			$structure_links['bottom']['add'][($tx_extend_type? __($tx_extend_type).' ('.__('from associated protocol').')' : __('import precisions from associated protocol'))] = array(
				'link' => '/ClinicalAnnotation/TreatmentExtendMasters/'.$extended_data_import_process.'/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id'],
				'icon' => 'treatment precision');
		}
		
		$structure_links['index'] = array(
			'edit'=>'/ClinicalAnnotation/TreatmentExtendMasters/edit/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id'].'/%%TreatmentExtendMaster.id%%',
			'delete'=>'/ClinicalAnnotation/TreatmentExtendMasters/delete/'.$atim_menu_variables['Participant.id'].'/'.$atim_menu_variables['TreatmentMaster.id'].'/%%TreatmentExtendMaster.id%%'
		);
		
		$final_options = array('data' => $tx_extend_data, 'type' => 'index', 'settings' => $structure_settings, 'links' => $structure_links);
		$final_atim_structure = $extend_form_alias;
		
		$display_next_sub_form = true;
		
		$hook_link = $this->Structures->hook('tx_extend_list');
		if( $hook_link ) {
			require($hook_link);
		}
		
		if($display_next_sub_form) $this->Structures->build( $final_atim_structure, $final_options);
	}
	
	if(!$is_ajax){

		$flag_use_for_ccl = $this->data['TreatmentControl']['flag_use_for_ccl'];
		
		// DIAGNOSTICS
		
		$structure_settings = array(
			'form_inputs'	=> false,
			'pagination'	=> false,
			'actions'		=> $flag_use_for_ccl? false : true,
			'form_bottom'	=> true,
			'header' 		=> __('related diagnosis', null), 
			'form_top' 		=> false
		);
		
		$structure_links['index'] = array(
			'detail' => '/ClinicalAnnotation/DiagnosisMasters/detail/%%DiagnosisMaster.participant_id%%/%%DiagnosisMaster.id%%'
		);
		
		$final_options = array('data' => $diagnosis_data, 'type' => 'index', 'settings' => $structure_settings, 'links' => $structure_links);
		$final_atim_structure = $diagnosis_structure;
		
		if(!AppController::checkLinkPermission('/ClinicalAnnotation/DiagnosisMasters/listall')){
			$final_options['type'] = 'detail';
			$final_atim_structure = array();
			$final_options['extras'] = '<div>'.__('You are not authorized to access that location.').'</div>';
		}
		
		$display_next_sub_form = true;
		
		$hook_link = $this->Structures->hook('dx_list');
		if( $hook_link ) { 
			require($hook_link); 
		}
		 
		if($display_next_sub_form) $this->Structures->build( $final_atim_structure,  $final_options);
		
		$final_atim_structure = array();
		$final_options['type'] = 'detail';
		$final_options['settings']['header'] = __('links to collections');
		$final_options['settings']['actions'] = true;
		$final_options['extras'] = $this->Structures->ajaxIndex('ClinicalAnnotation/ClinicalCollectionLinks/listall/'.$atim_menu_variables['Participant.id'].'/noActions:/filterModel:TreatmentMaster/filterId:'.$atim_menu_variables['TreatmentMaster.id']);
		
		$display_next_sub_form = $flag_use_for_ccl? true : false;
		
		$hook_link = $this->Structures->hook('ccl');
		if( $hook_link ) {
			require($hook_link);
		}
		
		if($display_next_sub_form) $this->Structures->build(array(), $final_options);
	}	
	
?>