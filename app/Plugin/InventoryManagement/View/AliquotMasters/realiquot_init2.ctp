<?php
	
	$bottom = array();
	if(isset($lab_book_ctrl_id)) $bottom['add lab book (pop-up)'] = '/labbook/LabBookMasters/add/'.$lab_book_ctrl_id.'/1/';
	$bottom['cancel'] = $url_to_cancel;
	
	$final_atim_structure = $atim_structure; 
	$final_options = array(
		'type' => 'add', 
		'settings' => array('header' => __('realiquoting process') . ' - ' . __('lab book selection')),
		'links' => array(
			'top' => '/InventoryManagement/AliquotMasters/'.$realiquoting_function.'/'.$aliquot_id,
			'bottom' => $bottom
		),
		'extras' => '<input type="hidden" name="data[sample_ctrl_id]" value="'.$sample_ctrl_id.'"/>
					<input type="hidden" name="data[realiquot_from]" value="'.$realiquot_from.'"/>
					<input type="hidden" name="data[0][realiquot_into]" value="'.$realiquot_into.'"/>
					<input type="hidden" name="data[0][ids]" value="'.$ids.'"/>
					<input type="hidden" name="data[url_to_cancel]" value="'.$url_to_cancel.'"/>'
	);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if($hook_link){
		require($hook_link); 
	}

	// BUILD FORM
	$this->Structures->build($final_atim_structure, $final_options);			
		
?>

<script>
var labBookPopup = true;
</script>