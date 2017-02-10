<?php
	$atim_content = array(
		'menu'			=> ''
	);
	
	if(count($menu_data)){
		
		$atim_content['menu'] .= '
				<ul id="big_menu_main" class="big_menu">
		';
		
		$count = 0;
		$class = null;
		foreach ( $menu_data as $menu ) {
			
			$title = __($menu['Menu']['language_title']);
					
			if(!$menu['Menu']['language_description']){
				$menu['Menu']['language_description'] = $menu['Menu']['language_title'];
			}
					
			if(!$menu['Menu']['allowed']){
				$class = 'icon32 not_allowed';
			}else{
				$class = 'icon32 '.$this->Structures->generateLinkClass( 'plugin '.$menu['Menu']['use_link'] );
			}
			
			$atim_content['menu'] .= '
				<!-- '.$menu['Menu']['id'].' -->
				<li class="'.( $menu['Menu']['at'] ? 'at ' : '' ).'count_'.$count.'">'
					.$this->Html->link('<div class="row"><span class="cell"><span class="'.$class.'"></span></span><span class="menuLabel cell">'. __($menu['Menu']['language_title']).'<span class="menuDesc">'.__($menu['Menu']['language_description']).'</span></span></div>', $menu['Menu']['use_link'], array('title' => $title, 'escape' => false)).'
				</li>
			';
			
			$count++;
		}
			
		$atim_content['menu'] .= '
			</ul>
		';
		
	}
	
	$due_msg_cond = isset($due_messages_count) && $due_messages_count > 0 && AppController::checkLinkPermission('/ClinicalAnnotation/ParticipantMessages/search/');
	$coll_cond = isset($unlinked_part_coll) && $unlinked_part_coll > 0 && AppController::checkLinkPermission('/InventoryManagement/Collections/search/');  
	$complete_forgotten_password_answers = isset($missing_forgotten_password_reset_answers) && $missing_forgotten_password_reset_answers && AppController::checkLinkPermission('/Customize/Profiles/index/');  
	$due_annoucements_cond = isset($due_annoucements_count) && $due_annoucements_count > 0 && AppController::checkLinkPermission('/Customize/Announcements/index/');
	
	if($due_msg_cond || $coll_cond || $complete_forgotten_password_answers || $due_annoucements_cond){
		$atim_content['messages'] = '';
		if($due_msg_cond){
			$atim_content['messages'] = '<ul class="warning"><li><span class="icon16 warning mr5px"></span>'.__('not done participant messages having reached their due date').': '.$due_messages_count.'.
				<a id="goToNotDue" href="javascript:goToNotDoneDueMessages()">'.__('click here to see them').'</a>.
				</li></ul>
				<form action="'.$this->request->webroot.'ClinicalAnnotation/ParticipantMessages/search/'.AppController::getNewSearchId().'" method="POST" id="doneDueMessages">
					<input type="hidden" name="data[ParticipantMessage][done]" value="0">
					<input type="hidden" name="data[ParticipantMessage][due_date_end]" value="'.now().'">
				</form>
			';
		}
		if($coll_cond){
			$for_bank_part = isset($bank_filter) ? __('for your bank') : __('for all banks');
			$atim_content['messages'] .= '<ul class="warning"><li><span class="icon16 warning mr5px"></span>'.__('unlinked participant collections').' ('.$for_bank_part.'): '.$unlinked_part_coll.'.
				 <a id="goToUnlinkedColl" href="'.$this->request->webroot.'InventoryManagement/Collections/search/'.AppController::getNewSearchId().'/unlinkedParticipants:/ ">'.__('click here to see them').'</a>.
				</li></ul>
			';
		}
		if($complete_forgotten_password_answers){
			$atim_content['messages'] .= '<ul class="warning"><li><span class="icon16 warning mr5px"></span>'.__('user questions to reset forgotten password are not completed - update your profile with the customize tool').'
				 <a id="goToNotDue" href="'.$this->request->webroot.'Customize/Profiles/index/">'.__('click here to update').'</a>.
				</li></ul>
			';
		}
		if($due_annoucements_cond) {
			$atim_content['messages'] .= '<ul class="warning"><li><span class="icon16 warning mr5px"></span>'.__('you have %s due annoucements', $due_annoucements_count).'
				 <a id="goToNotDue" href="'.$this->request->webroot.'Customize/Announcements/index/">'.__('click here to see them').'</a>.
				</li></ul>
			';
		}
	}
	
	$hook_link = $this->Structures->hook();
	if($hook_link){
		require($hook_link);
	}
	
	if(isset($set_of_menus)){
		echo $this->Structures->generateContentWrapper($atim_content, array('links'=>array('bottom'=>array('back to main menu'=>'/Menus'))));
	}else{
		echo $this->Structures->generateContentWrapper($atim_content);
	}
?>
<script>
function goToNotDoneDueMessages(){
	$("#doneDueMessages").submit();
}
</script>