<?php 

	// ----- ORDER DETAIL -----	
	
	$structure_links = array(
		'index'=>array(),
		'bottom'=>array(
			'new search' => OrderAppController::$search_links,
			'edit'=>'/Order/Orders/edit/' . $atim_menu_variables['Order.id'] . '/',
				'delete'=>'/Order/Orders/delete/' . $atim_menu_variables['Order.id'] . '/',
			'order lines' => array('add' =>  array('link'=>'/Order/OrderLines/add/' . $atim_menu_variables['Order.id'] . '/', 'icon' => 'order line add')),	
			'order items' => array(),
			'shipments' => array('add' => array('link'=> '/Order/Shipments/add/' . $atim_menu_variables['Order.id'] . '/', 'icon' => 'create_shipment'))
		)
	);
	//Add item links management
	switch(Configure::read('order_item_type_config')) {
		case '1':
			$structure_links['bottom']['order items'][__('add items to order').' : '.__('tma slide')] =  array('link'=>'/Order/OrderItems/add/' . $atim_menu_variables['Order.id'] . '/0/TmaSlide', 'icon'=>'add_to_order');
			$structure_links['bottom']['order items'][__('add items to order').' : '.__('aliquot')] =  array('link'=>'/Order/OrderItems/add/' . $atim_menu_variables['Order.id'] . '/0/AliquotMaster', 'icon'=>'add_to_order');
			break;
		case '2':
			$structure_links['bottom']['order items'][__('add items to order').' : '.__('aliquot')] =  array('link'=>'/Order/OrderItems/add/' . $atim_menu_variables['Order.id'] . '/0/AliquotMaster', 'icon'=>'add_to_order');
			break;
		case '3':
			$structure_links['bottom']['order items'][__('add items to order').' : '.__('tma slide')] =  array('link'=>'/Order/OrderItems/add/' . $atim_menu_variables['Order.id'] . '/0/TmaSlide', 'icon'=>'add_to_order');
			break;
	}
	$structure_links['bottom']['order items']['define order items returned'] =  array('link' => '/Order/OrderItems/defineOrderItemsReturned/'.$atim_menu_variables['Order.id'].'/', 'icon'=>'order items returned');	
	//Add to shipment links management
	$add_to_shipment_links = array();
	foreach ($shipments_list as $shipment) {
		$add_to_shipment_links[$shipment['Shipment']['shipment_code']] = array(
				'link'=> '/Order/Shipments/addToShipment/' . $shipment['Shipment']['order_id'] . '/' . $shipment['Shipment']['id'],
				'icon' => 'add_to_shipment');
	}
	ksort($add_to_shipment_links);
	foreach($add_to_shipment_links as $shipment_key => $shipment_link) $structure_links['bottom']['shipments'][__('add items to shipment').' # '.$shipment_key] = $shipment_link;
	
	if(Configure::read('order_item_to_order_objetcs_link_setting') == 3)  unset($structure_links['bottom']['order lines']);
	if(Configure::read('order_item_to_order_objetcs_link_setting') == 2)  unset($structure_links['bottom']['order items']);
	
	$structure_override = array();
	
	$final_atim_structure = $atim_structure; 
	$final_options = array(
		'links'	=> $structure_links,
		'override'=>$structure_override, 
		'settings' => array(
			'header' => ($is_from_tree_view? __('order'): ''),
			'actions' => ($is_from_tree_view? true : false)), 
			'data' => $order_data);
	
	// CUSTOM CODE
	$hook_link = $this->Structures->hook();
	if( $hook_link ) { require($hook_link); }
		
	// BUILD FORM
	$this->Structures->build( $final_atim_structure, $final_options );

	if(!$is_from_tree_view) {	
		// ----- ORDER LINES -----
		
		if(Configure::read('order_item_to_order_objetcs_link_setting') != 3) {
			$final_atim_structure = array(); 
			$final_options = array(
				'links'	=> $structure_links,
				'settings' => array(
					'header' => __('order_order lines', null),
					'actions'	=> false,
				), 'extras' => array('end' => $this->Structures->ajaxIndex('Order/OrderLines/listall/'.$atim_menu_variables['Order.id']))
			);
				
			// CUSTOM CODE
			$hook_link = $this->Structures->hook('order_lines');
			if( $hook_link ) { 
				require($hook_link); 
			}
				
			// BUILD FORM
			$this->Structures->build( $final_atim_structure, $final_options );	
		}
		
		// ----- ORDER ITEMS -----
		
		$counter = 0;
		$all_status = array('pending', 'shipped', 'shipped & returned');
		foreach($all_status as $status) {
			$counter++;
			$final_atim_structure = array();
			$final_options = array(
					'links'	=> $structure_links,
					'settings' => array(
						'language_heading' => __($status, null),
						'actions'	=> false,
					), 'extras' => array('end' => $this->Structures->ajaxIndex('Order/OrderItems/listall/'.$atim_menu_variables['Order.id'].'/'.$status.'/0/0/Order/'))
			);
			if($counter == 1) $final_options['settings']['header'] = __('order items', null);
	
			// CUSTOM CODE
			$hook_link = $this->Structures->hook('order_items');
			if( $hook_link ) {
				require($hook_link);
			}
			
			// BUILD FORM
			$this->Structures->build( $final_atim_structure, $final_options );
		}
		
	
		// ----- SHIPMENTS -----
		
		$final_atim_structure = array();
		$final_options = array(
				'links'	=> $structure_links,
				'settings' => array(
						'header' => __('shipments', null)
				), 'extras' => array('end' => $this->Structures->ajaxIndex('Order/Shipments/listall/'.$atim_menu_variables['Order.id'])));
		
		// CUSTOM CODE
		$hook_link = $this->Structures->hook('shipments');
		if( $hook_link ) {
			require($hook_link);
		}
		
		// BUILD FORM
		$this->Structures->build( $final_atim_structure, $final_options );
	}
