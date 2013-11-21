<?php

class ViewCollection extends InventoryManagementAppModel {
	
	var $base_model = "Collection";
	var $base_plugin = 'InventoryManagement';
	var $primaryKey = 'collection_id';
	
	var $belongsTo = array(
		'Collection' => array(
			'className'   => 'InventoryManagement.Collection',
			'foreignKey'  => 'collection_id',
			'type'			=> 'INNER'
		),
		'Participant' => array(
			'className' => 'ClinicalAnnotation.Participant',
			'foreignKey' => 'participant_id'
		), 'DiagnosisMaster' => array(
			'className' => 'ClinicalAnnotation.DiagnosisMaster',
			'foreignKey' => 'diagnosis_master_id'
		), 'ConsentMaster' => array(
			'className' => 'ClinicalAnnotation.ConsentMaster',
			'foreignKey' => 'consent_master_id'
		)
	);
	
	static $table_query = '
		SELECT 
		Collection.id AS collection_id,
		Collection.bank_id AS bank_id,
		Collection.sop_master_id AS sop_master_id,
		Collection.participant_id AS participant_id,
		Collection.diagnosis_master_id AS diagnosis_master_id,
		Collection.consent_master_id AS consent_master_id,
		Collection.treatment_master_id AS treatment_master_id,
		Collection.event_master_id AS event_master_id,
		Participant.participant_identifier AS participant_identifier,
		Collection.acquisition_label AS acquisition_label,
		Collection.collection_site AS collection_site,
		Collection.collection_datetime AS collection_datetime,
		Collection.collection_datetime_accuracy AS collection_datetime_accuracy,
		Collection.collection_property AS collection_property,
		Collection.collection_notes AS collection_notes,
		Collection.created AS created 
		FROM collections AS Collection 
		LEFT JOIN participants AS Participant ON Collection.participant_id = Participant.id AND Participant.deleted <> 1 
		WHERE Collection.deleted <> 1 %%WHERE%%';
	
	function summary($variables=array()) {
		$return = false;
		
		if(isset($variables['Collection.id'])) {
			$collection_data = $this->find('first', array('conditions'=>array('ViewCollection.collection_id' => $variables['Collection.id']), 'recursive' => '-1'));
			
			$return = array(
				'menu' => array(null, $collection_data['ViewCollection']['acquisition_label']),
				'title' => array(null, __('collection') . ' : ' . $collection_data['ViewCollection']['acquisition_label']),
				'structure alias' 	=> 'view_collection',
				'data'				=> $collection_data
			);
			
			$consent_status = $this->getUnconsentedParticipantCollections(array('data' => $collection_data));
			if(!empty($consent_status)){
				if(!$collection_data['ViewCollection']['participant_id']){
					AppController::addWarningMsg(__('no participant is linked to the current participant collection'));
				}else if($consent_status[$variables['Collection.id']] == null){
					$link = '';
					if(AppController::checkLinkPermission('/ClinicalAnnotation/ClinicalCollectionLinks/detail/')){
						$link = sprintf(' <a href="%sClinicalAnnotation/ClinicalCollectionLinks/detail/%d/%d">%s</a>', AppController::getInstance()->request->webroot, $collection_data['ViewCollection']['participant_id'], $collection_data['ViewCollection']['collection_id'], __('click here to access it'));
					}
					AppController::addWarningMsg(__('no consent is linked to the current participant collection').'.'.$link);
				}else{
					AppController::addWarningMsg(__('the linked consent status is [%s]', __($consent_status[$variables['Collection.id']])));
				}
			}
		}
		
		return $return;
	}
	
	/**
	 * @param array $collection with either a key 'id' referring to an array
	 * of ids, or a key 'data' referring to ViewCollections.
	 * @return array The ids of participants collections with a consent status 
	 * other than 'obtained' as key. Their value will null if there is no linked
	 * consent or the consent status otherwise. 
	 */
	function getUnconsentedParticipantCollections(array $collection){
		$data = null;
		if(array_key_exists('id', $collection)){
			$data = $this->find('all', array('conditions' => array('ViewCollection.collection_id' => $collection['id']), 'recursive' => '-1'));
		}else{
			$data = array_key_exists('ViewCollection', $collection['data']) ? array($collection['data']) : $collection['data']; 
		}
		
		$results = array();
		$consents_to_fetch = array();
		foreach($data as $index => &$data_unit){
			if($data_unit['ViewCollection']['collection_property'] != 'participant collection'){
				//filter non participant collections
				unset($data[$index]);
			}else if(empty($data_unit['ViewCollection']['consent_master_id'])){
				//removing missing consents
				$results[$data_unit['ViewCollection']['collection_id']] = null;
				unset($data[$index]);
			}else{
				$consents_to_fetch[] = $data_unit['ViewCollection']['consent_master_id']; 
			}
		}
		
		if(!empty($consents_to_fetch)){
			//find all required consents
			$consent_model = AppModel::getInstance("ClinicalAnnotation", "ConsentMaster", true);
			$consent_data = $consent_model->find('all', array(
				'fields' => array('ConsentMaster.id', 'ConsentMaster.consent_status'), 
				'conditions' => array('ConsentMaster.id' => $consents_to_fetch, 'NOT' => array('ConsentMaster.consent_status' => 'obtained')),
				'recursive' => -1)
			);
			
			//put consents in array keys
			$not_obtained_consents = array();
			if(!empty($consent_data)){
				foreach($consent_data as &$consent_data_unit){
					$not_obtained_consents[$consent_data_unit['ConsentMaster']['id']] = $consent_data_unit['ConsentMaster']['consent_status'];
				}
				
				//see for each collection if the consent is found in the not obtained consent array
				foreach($data as &$data_unit){
					if(array_key_exists($data_unit['ViewCollection']['consent_master_id'], $not_obtained_consents)){
						$results[$data_unit['ViewCollection']['collection_id']] = $not_obtained_consents[$data_unit['ViewCollection']['consent_master_id']];
					}
				}
			}
		}
		
		return $results;
	}
}

