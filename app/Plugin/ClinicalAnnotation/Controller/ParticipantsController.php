<?php

class ParticipantsController extends ClinicalAnnotationAppController {
	
	var $components = array(); 
		
	var $uses = array(
		'ClinicalAnnotation.Participant',
		'ClinicalAnnotation.ConsentMaster',
		'ClinicalAnnotation.ParticipantContact',
		'ClinicalAnnotation.ParticipantMessage',
		'ClinicalAnnotation.EventMaster',
		'ClinicalAnnotation.DiagnosisMaster',
		'ClinicalAnnotation.FamilyHistory',
		'ClinicalAnnotation.MiscIdentifier',
		'ClinicalAnnotation.ReproductiveHistory',
		'ClinicalAnnotation.TreatmentMaster',
		'ClinicalAnnotation.MiscIdentifierControl',
		'Codingicd.CodingIcd10Who',
		'Codingicd.CodingIcd10Ca',
	);
	var $paginate = array(
		'Participant'=>array('order'=>'Participant.last_name ASC, Participant.first_name ASC'),
		'MiscIdentifier'=>array('order'=>'MiscIdentifier.study_summary_id ASC,MiscIdentifierControl.misc_identifier_name ASC')); 
	
	function search($search_id = ''){
		$this->searchHandler($search_id, $this->Participant, 'participants', '/ClinicalAnnotation/Participants/search');
	
		// CUSTOM CODE: FORMAT DISPLAY DATA
		$hook_link = $this->hook('format');
		if( $hook_link ) { 
			require($hook_link); 
		}
		
		if(empty($search_id)){
			//index
			$this->request->data = $this->Participant->find('all', array(
				'conditions' => array('Participant.created_by' => $this->Session->read('Auth.User.id')),
				'order' => array('Participant.created DESC'),
				'limit' => 5)
			);
			$this->render('index');
		}
	}

	function profile($participant_id){
		// MANAGE DATA
		$this->request->data = $this->Participant->getOrRedirect($participant_id);
		
		// Set data for identifier list
		$participant_identifiers_data = $this->paginate($this->MiscIdentifier, array('MiscIdentifier.participant_id'=>$participant_id));
		$this->set('participant_identifiers_data', $participant_identifiers_data);
		
		// MANAGE FORM, MENU AND ACTION BUTTONS
		$this->set( 'atim_menu_variables', array('Participant.id'=>$participant_id) );
		
		// Set form for identifier list
		$this->Structures->set('miscidentifiers', 'atim_structure_for_misc_identifiers');
		
		$mi = $this->MiscIdentifier->find('all', array(
				'fields' => array('MiscIdentifierControl.id'),
				'conditions' => array('MiscIdentifier.deleted' => 1, 'MiscIdentifier.tmp_deleted' => 1),
				'group' => array('MiscIdentifierControl.id')
		));
		$reusable = array();
		foreach($mi as $mi_unit){
			$reusable[$mi_unit['MiscIdentifierControl']['id']] = null;
		}
		$conditions = array('flag_active' => '1');
		if(!$this->Session->read('flag_show_confidential')){
			$conditions["flag_confidential"] = 0;
		}
		$identifier_controls_list = $this->MiscIdentifierControl->find('all', array('conditions' => $conditions));
		foreach($identifier_controls_list as &$unit){
			if(!empty($unit['MiscIdentifierControl']['autoincrement_name']) && array_key_exists($unit['MiscIdentifierControl']['id'], $reusable)){
				$unit['reusable'] = true;
			}
		}
		$this->set('identifier_controls_list', $identifier_controls_list);
		$this->Structures->set('empty', 'empty_structure');
		
		// CUSTOM CODE: FORMAT DISPLAY DATA
		$hook_link = $this->hook('format');
		if($hook_link){
			require($hook_link); 
		}
		
		$this->set('is_ajax', $this->request->is('ajax'));
	}
	
	function add() {
		// MANAGE FORM, MENU AND ACTION BUTTONS
		$this->set( 'atim_menu', $this->Menus->get('/ClinicalAnnotation/Participants/search') );
		
		// CUSTOM CODE: FORMAT DISPLAY DATA
		$hook_link = $this->hook('format');
		if( $hook_link ) { require($hook_link); }
		
		if ( !empty($this->request->data) ) {
			$this->Participant->patchIcd10NullValues($this->request->data);
			$submitted_data_validates = true;
			// ... special validations
			
			// CUSTOM CODE: PROCESS SUBMITTED DATA BEFORE SAVE
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) { 
				require($hook_link); 
			}				
			
			if($submitted_data_validates) {
				if ( $this->Participant->save($this->request->data) ) {
					
					$hook_link = $this->hook('postsave_process');
					if( $hook_link ) { 
						require($hook_link); 
					}
					
					$this->atimFlash(__('your data has been saved'), '/ClinicalAnnotation/Participants/profile/'.$this->Participant->getLastInsertID());
				}
			}
		}
	}
	
	function edit( $participant_id ) {
		// MANAGE DATA
		$participant_data = $this->Participant->getOrRedirect($participant_id);
		
		// MANAGE FORM, MENU AND ACTION BUTTONS
		$this->set( 'atim_menu_variables', array('Participant.id'=>$participant_id) );
		
		// CUSTOM CODE: FORMAT DISPLAY DATA
		$hook_link = $this->hook('format');
		if( $hook_link ) { 
			require($hook_link); 
		}
		if(empty($this->request->data)) {
			$this->request->data = $participant_data;
		} else {
			$this->Participant->patchIcd10NullValues($this->request->data);
			$submitted_data_validates = true;
			// ... special validations
			// CUSTOM CODE: PROCESS SUBMITTED DATA BEFORE SAVE
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) { 
				require($hook_link); 
			}

			if($submitted_data_validates) {
				$this->Participant->id = $participant_id;
				$this->Participant->data = array();
				if ( $this->Participant->save($this->request->data) ){
					$hook_link = $this->hook('postsave_process');
					if( $hook_link ) {
						require($hook_link);
					}
					$this->atimFlash(__('your data has been updated'), '/ClinicalAnnotation/Participants/profile/'.$participant_id );		
				}
			}
		}
	}

	function delete( $participant_id ) {

		// MANAGE DATA
		$this->request->data = $this->Participant->getOrRedirect($participant_id);

		$arr_allow_deletion = $this->Participant->allowDeletion($participant_id);
		
		// CUSTOM CODE	
		$hook_link = $this->hook('delete');
		if( $hook_link ) { require($hook_link); }
		
		if ($arr_allow_deletion['allow_deletion']) {
			if ( $this->Participant->atimDelete( $participant_id ) ) {
				$hook_link = $this->hook('postsave_process');
				if( $hook_link ) { 
					require($hook_link); 
				}
				$this->atimFlash(__('your data has been deleted'), '/ClinicalAnnotation/Participants/search/');
			} else {
				$this->flash(__('error deleting data - contact administrator'), '/ClinicalAnnotation/Participants/search/');
			}
		} else {
			$this->flash(__($arr_allow_deletion['msg']), '/ClinicalAnnotation/Participants/profile/'.$participant_id.'/');
		}
	}

	function chronology($participant_id){
		$tmp_array = array();
		$this->set( 'atim_menu_variables', array('Participant.id'=>$participant_id) );
		$this->Structures->set('chronology', 'chronology');
		
		//*** Load model being used to populate chronology_details (for values of fields linked to drop down list)  
		
		$this->StructurePermissibleValuesCustom = AppModel::getInstance("", "StructurePermissibleValuesCustom", true); // Use of $StructurePermissibleValuesCustom->getTranslatedCustomDropdownValue()
		App::uses('StructureValueDomain', 'Model');
		$this->StructureValueDomain = new StructureValueDomain();
		
		$hook_link = $this->hook('start');
		if( $hook_link ) {
			require($hook_link);
		}
		
		//accuracy_sort
		$a_s = array(
			'±'	=> 0,
			'y'	=> 1,
			'm'	=> 2,
			'd'	=> 3,
			'h'	=> 4,
			'i'	=> 5,
			'c'	=> 6,
			'' => 7
		);
		
		$add_to_tmp_array = function(array $in) use($a_s, &$tmp_array){
			if($in['date']){
				$tmp_array[$in['date'].$a_s[$in['date_accuracy']]][] = $in;
			}else{
				$tmp_array[' '][] = $in;
			}
		};
		
		//*** load every wanted information into the tmpArray ***
		
		// 1-Participant
		
		$participant = $this->Participant->find('first', array('conditions' => array('Participant.id' => $participant_id)));
		$chronolgy_data_participant_birth = array(
			'date'			=> $participant['Participant']['date_of_birth'],
			'date_accuracy'	=> $participant['Participant']['date_of_birth_accuracy'],
			'event' 		=> __('date of birth'),
			'chronology_details' => '',
			'link'			=> '/ClinicalAnnotation/Participants/profile/'.$participant_id.'/'
		);
		$chronolgy_data_participant_death = false;
		if(strlen($participant['Participant']['date_of_death']) > 0){
			$chronolgy_data_participant_death = array(
				'date'			=> $participant['Participant']['date_of_death'],
				'date_accuracy'	=> $participant['Participant']['date_of_death_accuracy'],
				'event'			=> __('date of death'),
				'chronology_details' => '',
				'link'			=> '/ClinicalAnnotation/Participants/profile/'.$participant_id.'/'
			);
		}
		$hook_link = $this->hook('participant');
		if( $hook_link ) {
			require($hook_link);
		}
		if($chronolgy_data_participant_birth) $add_to_tmp_array($chronolgy_data_participant_birth);
		if($chronolgy_data_participant_death) $add_to_tmp_array($chronolgy_data_participant_death);
				
		// 2-Consent
		
		$consent_status = $this->StructureValueDomain->find('first', array('conditions' => array('StructureValueDomain.domain_name' => 'consent_status'), 'recursive' => 2));
		$consent_status_values = array();
		if($consent_status) {
			foreach($consent_status['StructurePermissibleValue'] as $new_value) {
				$consent_status_values[$new_value['value']] = __($new_value['language_alias']);
			}
		}
		$consents = $this->ConsentMaster->find('all', array('conditions' => array('ConsentMaster.participant_id' => $participant_id)));
		foreach($consents as $consent){
			$chronolgy_data_consent = array(
				'date'			=> $consent['ConsentMaster']['consent_signed_date'],
				'date_accuracy'	=> isset($consent['ConsentMaster']['consent_signed_date_accuracy']) ? $consent['ConsentMaster']['consent_signed_date_accuracy'] : 'c',
				'event'			=> __('consent').', '.__($consent['ConsentControl']['controls_type']),
				'chronology_details' => isset($consent_status_values[$consent['ConsentMaster']['consent_status']])? $consent_status_values[$consent['ConsentMaster']['consent_status']] : $consent['ConsentMaster']['consent_status'],
				'link'			=> '/ClinicalAnnotation/ConsentMasters/detail/'.$participant_id.'/'.$consent['ConsentMaster']['id']
			);
			$hook_link = $this->hook('consent');
			if( $hook_link ) {
				require($hook_link);
			}
			if($chronolgy_data_consent) $add_to_tmp_array($chronolgy_data_consent);
		}
		
		// 2-Diagnosis
		
		$dxs = $this->DiagnosisMaster->find('all', array('conditions' => array('DiagnosisMaster.participant_id' => $participant_id)));
		foreach($dxs as $dx){
			$chronolgy_data_diagnosis = array(
				'date'			=> $dx['DiagnosisMaster']['dx_date'],
				'date_accuracy'	=> $dx['DiagnosisMaster']['dx_date_accuracy'],
				'event'			=> __('diagnosis').', '.__($dx['DiagnosisControl']['category']).' - '.__($dx['DiagnosisControl']['controls_type']),
				'chronology_details' => '',
				'link'			=> '/ClinicalAnnotation/DiagnosisMasters/detail/'.$participant_id.'/'.$dx['DiagnosisMaster']['id']
			);
			$hook_link = $this->hook('diagnosis');
			if( $hook_link ) {
				require($hook_link);
			}
			if($chronolgy_data_diagnosis) $add_to_tmp_array($chronolgy_data_diagnosis);
		}
		
		// 3-Event
		
		$annotations = $this->EventMaster->find('all', array('conditions' => array('EventMaster.participant_id' => $participant_id)));
		foreach($annotations as $annotation){
			$chronolgy_data_annotation = array(
				'date'			=> $annotation['EventMaster']['event_date'],
				'date_accuracy' => isset($annotation['EventMaster']['event_date_accuracy']) ? $annotation['EventMaster']['event_date_accuracy'] : 'c',
				'event'			=> __($annotation['EventControl']['event_group']).', '.__($annotation['EventControl']['event_type']),
				'chronology_details' => '',
				'link'			=> '/ClinicalAnnotation/EventMasters/detail/'.$participant_id.'/'.$annotation['EventMaster']['id']
			);
			$hook_link = $this->hook('annotation');
			if( $hook_link ) {
				require($hook_link);
			}
			if($chronolgy_data_annotation) $add_to_tmp_array($chronolgy_data_annotation);
		}
		
		// 4-Treatment
		
		$txs = $this->TreatmentMaster->find('all', array('conditions' => array('TreatmentMaster.participant_id' => $participant_id)));
		foreach($txs as $tx){
			$chronolgy_data_treatment_start = array(
				'date'			=> $tx['TreatmentMaster']['start_date'],
				'date_accuracy' => $tx['TreatmentMaster']['start_date_accuracy'],
				'event'			=> __('treatment').", ".__($tx['TreatmentControl']['tx_method'])." (".__("start").")",
				'chronology_details' => '',
				'link'			=> '/ClinicalAnnotation/TreatmentMasters/detail/'.$participant_id.'/'.$tx['TreatmentMaster']['id']
			);
			$chronolgy_data_treatment_finish = false;
			if(!empty($tx['TreatmentMaster']['finish_date'])){
				$chronolgy_data_treatment_finish = array(
					'date'			=> $tx['TreatmentMaster']['finish_date'],
					'date_accuracy' => $tx['TreatmentMaster']['finish_date_accuracy'],
					'event'			=> __('treatment').", ".__($tx['TreatmentControl']['tx_method'])." (".__("end").")",
					'chronology_details' => '',
					'link'			=> '/ClinicalAnnotation/TreatmentMasters/detail/'.$participant_id.'/'.$tx['TreatmentMaster']['id']
				);
			}
			$hook_link = $this->hook('treatment');
			if( $hook_link ) {
				require($hook_link);
			}
			if($chronolgy_data_treatment_start) $add_to_tmp_array($chronolgy_data_treatment_start);
			if($chronolgy_data_treatment_finish) $add_to_tmp_array($chronolgy_data_treatment_finish);
		}
		
		$collection_model = AppModel::getInstance('InventoryManagement', 'Collection', true);
		$collections = $collection_model->find('all', array('conditions' => array('Collection.participant_id' => $participant_id), 'recursive' => -1));
		foreach($collections as $collection){
			$chronolgy_data_collection = array(
				'date'			=> $collection['Collection']['collection_datetime'],
				'date_accuracy' => $collection['Collection']['collection_datetime_accuracy'],
				'event'			=> __('collection'),
				'chronology_details' => $collection['Collection']['acquisition_label'],
				'link'			=> '/InventoryManagement/Collections/detail/'.$collection['Collection']['id']
			);
			$hook_link = $this->hook('collection');
			if( $hook_link ) {
				require($hook_link);
			}
			if($chronolgy_data_collection) $add_to_tmp_array($chronolgy_data_collection);
		}
		
		$hook_link = $this->hook('end');
		if( $hook_link ) {
			require($hook_link);
		}
		
		//*** Sort data ***
		
		//sort the tmpArray by key (key = date)
		ksort($tmp_array);
		$tmp_array2 = array();
		foreach($tmp_array as $date_w_accu => $elements){
			$date = substr($date_w_accu, 0, -1);
			if($date == 0){
				$date = '';
			}
			if(isset($tmp_array2[$date])){
				$tmp_array2[$date] = array_merge($tmp_array2[$date], $elements);
			}else{
				$tmp_array2[$date] = $elements;
			}
		}
		$tmp_array = $tmp_array2;
		
		//transfer the tmpArray into $this->request->data
		$this->request->data = array();
		foreach($tmp_array as $key => $values){
			foreach($values as $value){
				$date = $key;
				$time = null;
				if(strpos($date, " ") > 0){
					list($date, $time) = explode(" ", $date);
				}
				$this->request->data[] = array('custom' => array(
					'date' => $date,
					'date_accuracy' => $value['date_accuracy'],
					'time' => $time,
					'event' => $value['event'],
					'chronology_details' => $value['chronology_details'],
					'link' => isset($value['link']) ? $value['link'] : null));
			}
		}
	}
	
	function batchEdit(){
//TODO not supported anymore
$this->redirect('/Pages/err_plugin_system_error?method='.__METHOD__.',line='.__LINE__, null, true);		
		$this->set('atim_menu', $this->Menus->get('/ClinicalAnnotation/Participants/search'));
		if(empty($this->request->data)){
			$this->redirect('/Pages/err_plugin_no_data?method='.__METHOD__.',line='.__LINE__, null, true);
		}	
		if(isset($this->request->data['Participant']['id']) && (is_array($this->request->data['Participant']['id']) || $this->request->data['Participant']['id'] == 'all')){
			//display
			if(isset($this->request->data['node']) && $this->request->data['Participant']['id'] == 'all') {
				$this->BrowsingResult = AppModel::getInstance('Datamart', 'BrowsingResult', true);
				$browsing_result = $this->BrowsingResult->find('first', array('conditions' => array('BrowsingResult.id' => $this->request->data['node']['id'])));
				$this->request->data['Participant']['id'] = explode(",", $browsing_result['BrowsingResult']['id_csv']);
			}
			$ids = array_filter($this->request->data['Participant']['id']);
			$this->request->data[0]['ids'] = implode(",", $ids);
			
			$hook_link = $this->hook('initial_display');
			if( $hook_link ) {
				require($hook_link);
			}
			
		}else if(isset($this->request->data[0]['ids']) && strlen($this->request->data[0]['ids'])){
			//save
			$participants = $this->Participant->find('all', array('conditions' => array('Participant.id' => explode(",", $this->request->data[0]['ids']))));
			$this->Structures->set('participants');
			//fake participant to validate
			AppController::removeEmptyValues($this->request->data['Participant']);
			$this->Participant->set($this->request->data);
			$submitted_data_validates = $this->Participant->validates();
			$this->request->data = $this->Participant->data	;	
				
			$hook_link = $this->hook('presave_process');
			if( $hook_link ) {
				require($hook_link);
			}
			
			if($submitted_data_validates){
				$ids = explode(",", $this->request->data[0]['ids']);			
				foreach($ids as $id){
					$this->Participant->id = $id;
					$this->Participant->save($this->request->data['Participant'], array('validate' => false, 'fieldList' => array_keys($this->request->data['Participant'])));
				}
				
				$datamart_structure = AppModel::getInstance("Datamart", "DatamartStructure", true);
				$batch_set_model = AppModel::getInstance('Datamart', 'BatchSet', true);
				$batch_set_data = array('BatchSet' => array(
						'datamart_structure_id' => $datamart_structure->getIdByModelName('Participant'),
						'flag_tmp' => true
				));
				$batch_set_model->check_writable_fields = false;
				$batch_set_model->saveWithIds($batch_set_data, $ids);
				
				$hook_link = $this->hook('postsave_process');
				if( $hook_link ) {
					require($hook_link);
				}
				
				$this->atimFlash(__('your data has been saved'), '/Datamart/BatchSets/listall/'.$batch_set_model->getLastInsertId());
			}
			
		}else{
			$this->redirect('/Pages/err_plugin_no_data?method='.__METHOD__.',line='.__LINE__, null, true);
		}
	}
}

?>