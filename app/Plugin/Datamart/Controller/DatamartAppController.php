<?php

class DatamartAppController extends AppController {
	
	/**
	 * The limit of records to display before considering it an 
	 * oversized resut set
	 * @var int 
	 */
	static public $display_limit = 400;
	
	static function printList($options, $label, $webroot){
		foreach($options as $option){
			$curr_label = $label." &gt; ".$option['label'];
			$curr_label_for_class = str_replace("'", "&#39;", $curr_label);
			$action = isset($option['value']) ? ', "action" : "'.$webroot.$option['value'].'" ' : "";
			$class = isset($option['class']) ? $option['class'] : "";
			echo("<li class='"."'><a href='#' class='{ \"value\" : \"".$option['value']."\", \"label\" : \"".$curr_label_for_class."\" ".$action." } ".$class."'>".$option['default']."</a>");
			if(isset($option['children'])){
				if(count($option['children']) > 15){
					$tmp_children = array();
					if($option['children'][0]['label'] == __("filter")){
						//remove filter and no filter from the pages
						$tmp_children = array_splice($option['children'], 2);
					}else{
						$tmp_children = $option['children'];
						$option['children'] = array();
					}
					$children_arr = array_chunk($tmp_children, 15);
					$page_str = __("page %d");
					$page_num = 1;
					foreach($children_arr as $child){
						$option['children'][] = array("default" => sprintf($page_str, $page_num), "value" => "", "children" => $child);
						$page_num ++;
					}
				}
				echo("<ul>");
				self::printList($option['children'], $curr_label, $webroot);
				echo("</ul>");
			}
			echo("</li>\n");
		}		
	}
}

?>