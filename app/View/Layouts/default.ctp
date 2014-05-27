<?php
/**
 *
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.View.Layouts
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
$use_buffer = false;
if(!headers_sent()){
	if(Configure::read('use_compression')){
		assert(ob_start('ob_gzhandler')) or die('Failed to start compression buffer. Make sure ZLIB is installed properley (http://php.net/zlib) or turn compression off via use_compression in core.php.');
		$use_buffer = true;
	}
	header ('Content-type: text/html; charset=utf-8');
	AppController::atimSetCookie(isset($skip_expiration_cookie) && $skip_expiration_cookie);
}
?>
<!DOCTYPE html>
<html>
<head>
	<?php
		$header = $this->Shell->header(array(
			'atim_menu_for_header' => $atim_menu_for_header,
			'atim_sub_menu_for_header' => $atim_sub_menu_for_header,
			'atim_menu' => $atim_menu,
			'atim_menu_variables' => $atim_menu_variables) 
		);
		$title = $this->Shell->pageTitle;
	?>
	
	<title><?php echo $title ? $title.' &laquo ATiM' : __('core_appname', true); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="shortcut icon" href="<?php echo($this->request->webroot); ?>img/favicon.ico"/>
	<?php 
		echo $this->Html->css('style')."\n"; 
		echo $this->Html->css('jQuery/themes/custom-theme/jquery-ui-1.8.20.custom')."\n";
		echo $this->Html->css('jQuery/popup/popup.css');
		echo $this->Html->css('jQuery/fg.menu.css'); 

		//set the locale
		if(__('clin_english', true) == "Anglais"){
			$locale = "fr";
		}else{
			$locale = "";
		}
		?>
		
		<script>
			var root_url = "<?php echo($this->request->webroot); ?>";
			var webroot_dir = root_url + "/app/webroot/";
			var locale = "<?php echo $locale; ?>";
			var STR_OR = "<?php echo __('or'); ?>";
			var STR_SPECIFIC = "<?php echo __('specific'); ?>";
			var STR_RANGE = "<?php echo __('range'); ?>";
			var STR_TO = "<?php echo __('to'); ?>";
			var STR_DELETE_CONFIRM = "<?php echo __( 'core_are you sure you want to delete this data?') ?>";
			var STR_YES = "<?php echo __('yes'); ?>";
			var STR_NO = "<?php echo __('no'); ?>";
			var STR_COPY = "<?php echo __("copy", null); ?>";
			var STR_PASTE = "<?php echo __("paste"); ?>";
			var STR_PASTE_ON_ALL_LINES = "<?php echo __("paste on all lines"); ?>";
			var STR_PASTE_ON_ALL_LINES_OF_ALL_SECTIONS = "<?php echo __("paste on all lines of all sections"); ?>";
			var STR_LAB_BOOK = "<?php echo __("lab book"); ?>";
			var STR_LOADING = "<?php echo __('loading'); ?>";
			var STR_OK = "<?php echo __('ok'); ?>";
			var STR_CANCEL = "<?php echo __('cancel'); ?>";
			var STR_LOADING = "<?php echo __('loading'); ?>";
			var STR_BACK = "<?php echo __('back'); ?>";
			var STR_NODE_SELECTION = "<?php echo __('nodes selection'); ?>";
						
		</script>
	<!--[if IE 7]>
	<?php
	
		echo $this->Html->css('iehacks');
	?>
	<![endif]-->
</head>
<body>
	
<?php 
	echo $header;
	
	echo $this->Session->flash();
	echo $this->Session->flash('auth');
	
	echo $content_for_layout;
	
	echo $this->Shell->footer();
	

	echo $this->element('sql_dump');
	
	// JS added to end of DOM tree...
	
	echo $this->Html->script('jquery-1.7.2.min')."\n";
	echo $this->Html->script('jquery-ui-1.8.20.custom.min')."\n";
	echo $this->Html->script('jquery.ui-datepicker-fr.js')."\n";
	echo $this->Html->script('jquery.highlight.js')."\n";
	echo $this->Html->script('jquery.popup.js')."\n";
	echo $this->Html->script('jquery.tablednd_0_5.js')."\n";
	echo $this->Html->script('jquery.mousewheel.min.js')."\n";
	echo $this->Html->script('jquery.cookie.js')."\n";
	echo $this->Html->script('fg.menu.js')."\n";
	echo $this->Html->script('default')."\n";
	echo $this->Html->script('storage_layout')."\n";
	echo $this->Html->script('copyControl')."\n";
	echo $this->Html->script('ccl')."\n";
	echo $this->Html->script('dropdownConfig')."\n";
	echo $this->Html->script('jquery.fm-menu')."\n";
	echo $this->Html->script('form/jquery.form.js')."\n";
	?>
	
	<script type="text/javascript">
		$(function(){
			initJsControls();
		});
	</script>
	<div id="default_popup" class='hidden std_popup'></div>
	<div id="hiddenImages">
		<span class="icon16 fetching"></span>
		<img src='<?php echo $this->request->webroot; ?>img/btnBackSel.png'/>
	</div>
</body>
</html>
<?php
if($use_buffer){ 
	ob_end_flush();
}
?>
