<?php 
	$options = array(
			"links"		=> array(
				"top" => "/InventoryManagement/AliquotMasters/realiquot/".$aliquot_id,
				'bottom' => array('cancel' => $url_to_cancel))
	);
	
	$options_parent = array_merge($options, array(
		"type" => "edit",
		"settings" 	=> array("actions" => false, "form_top" => false, "form_bottom" => false, "stretch" => false, "language_heading" => __('parent aliquot (for update)'), 'section_start' => true)
	));
	$options_children = array_merge($options, array(
		"type" => "addgrid",
		"settings" 	=> array("add_fields" => true, "del_fields" => true, "actions" => false, "form_top" => false, "form_bottom" => false, "language_heading" => __('created children aliquot(s)'), 'section_end' => true)
	));
		
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if($hook_link){
		require($hook_link); 
	}
	
	// Display empty structure with hidden fields to fix issue#2243 : Derivative in batch: control id not posted when last record is hidden
	$empty_structure_options = $options_parent;
	$empty_structure_options['settings']['language_heading'] = '';
	$empty_structure_options['settings']['form_top'] = true;
	$empty_structure_options['data'] = array();
	$empty_structure_options['extras'] =
		'<input type="hidden" name="data[ids]" value="'.$parent_aliquots_ids.'"/>
		<input type="hidden" name="data[sample_ctrl_id]" value="'.$sample_ctrl_id.'"/>
		<input type="hidden" name="data[realiquot_from]" value="'.$realiquot_from.'"/>
		<input type="hidden" name="data[realiquot_into]" value="'.$realiquot_into.'"/>
		<input type="hidden" name="data[Realiquoting][lab_book_master_code]" value="'.$lab_book_code.'"/>
		<input type="hidden" name="data[Realiquoting][sync_with_lab_book]" value="'.$sync_with_lab_book.'"/>
		<input type="hidden" name="data[url_to_cancel]" value="'.$url_to_cancel.'"/>';
	$this->Structures->build($empty_structure, $empty_structure_options);
	
	//print the layout	
	
	$hook_link = $this->Structures->hook('loop');

	$counter = 0;
	$final_parent_structure = null;
	$final_children_structure = null;
	while($data = array_shift($this->request->data)){
		$counter++;
		$parent = $data['parent'];
		$final_options_parent = $options_parent;
		$final_options_children = $options_children;
		if(count($this->request->data) == 0){
			$final_options_children['settings']['form_bottom'] = true;
			$final_options_children['settings']['actions'] = true;
			$final_options_children['settings']['actions'] = true;
			if (empty($aliquot_id)) $final_options_children['settings']['confirmation_msg'] = __('multi_entry_form_confirmation_msg');
		}
		$final_options_parent['settings']['header'] = __('realiquoting process') . ' - ' . __('children creation') . (empty($aliquot_id)? " #".$counter : '');
		$final_options_parent['settings']['name_prefix'] = $parent['AliquotMaster']['id'];
		$final_options_parent['data'] = $parent;
		
		$final_options_children['settings']['name_prefix'] = $parent['AliquotMaster']['id'];
		$final_options_children['override']= $created_aliquot_override_data;
		$final_options_children['data'] = $data['children'];
		
		if(empty($parent['AliquotControl']['volume_unit'])){
			$final_parent_structure = $in_stock_detail;
		}else{
			$final_parent_structure = $in_stock_detail_volume;
		}
				
		if( $hook_link ) { 
			require($hook_link); 
		}
		
		$this->Structures->build($final_parent_structure, $final_options_parent);
		$this->Structures->build($atim_structure, $final_options_children);
	}
?>

<script type="text/javascript">
var copyStr = "<?php echo(__("copy", null)); ?>";
var pasteStr = "<?php echo(__("paste")); ?>";
var copyingStr = "<?php echo(__("copying")); ?>";
var pasteOnAllLinesStr = "<?php echo(__("paste on all lines")); ?>";
var copyControl = true;
var labBookFields = new Array("<?php echo implode('", "', $lab_book_fields); ?>");
var labBookHideOnLoad = true;
</script>
