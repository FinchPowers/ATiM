<?php
$structure_links = array(
	'index'=>array('detail' => '/ClinicalAnnotation/Participants/profile/%%Participant.id%%'),
	'bottom' => array(
			'add participant' => '/ClinicalAnnotation/Participants/add/'
	)
);

$settings = array('return' => true);
if(isset($is_ajax)){
	$settings['actions'] = false;
}else{
	$settings['header'] = __('search type', null).': '.__('participants', null);
}

// Set form structure and option
$final_atim_structure = $atim_structure;
$final_options = array(
		'type' => 'index',
		'links' => $structure_links,
		'settings' => $settings
);

// CUSTOM CODE
$hook_link = $this->Structures->hook();
if( $hook_link ) {
	require($hook_link);
}