<?php
$this->Structures->build($atim_structure, array(
	'type' => 'index', 
	'data' => array(
		array('PermissionsPreset' => array('name' => __('readonly'), 'description' => __('atim_preset_readonly'), 'id' => '-1')),
		array('PermissionsPreset' => array('name' => __('reset'), 'description' => __('atim_preset_reset'), 'id' => '-2'))),
	'links' => array('index' => array('detail' => array('link' => 'javascript:applyPreset(%%PermissionsPreset.id%%);', 'icon' => 'detail'))), 
	'settings' => array(
		'header' => __('atim presets'), 
		'pagination' => false,
		'actions' => false,
		'form_bottom' => false
	)
));

$can_delete = !empty($this->request->data) && AppController::checkLinkPermission($this->request->data[0]['PermissionPreset']['delete']);
$this->Structures->build($atim_structure, array(
	'type' => 'index', 
	'data' => $this->request->data, 
	'links' => array(
		'index' => array('detail' => array('link' => '#', 'icon' => 'detail jsApplyPreset', 'json' => '%%PermissionsPreset.json%%'), 'delete' => $can_delete ? 'javascript:deletePreset(%%PermissionsPreset.id%%);' : '/underdev/'),
		'bottom' => array(
			__('save preset') => array('link' => AppController::checkLinkPermission('/Administrate/Permissions/savePreset/') ? 'javascript:savePresetPopup();' : '/noright', 'icon' => 'submit')
		)
	), 
	'settings' => array(
		'header' => __('saved presets'), 
		'pagination' => false),
	)
);

?>
<script>
function deletePreset(id){
	$("#deleteConfirmPopup").popup('close');
	$("#frame").html("<div class='loading'>--- " + STR_LOADING + " ---</div>");
	$.post(root_url + "Administrate/Permissions/deletePreset/" + id, "", function(data){
		$.get(root_url + "Administrate/Permissions/loadPreset/", null, function(data){
			$("#frame").html(data);
		});
	});
}

function applyPreset(data){
	if(data == -2){
		//built-in reset
		$(".tree_root").find("select").val("");
		$(".tree_root").find("select").first().val(1);
	}else if(data == -1){
		//built-in readonly
		$(".tree_root").find("select").each(function(){
			var selectElement = this;
			$($(this).parent().parent().children()[2]).each(function(){
				var html = $(this).html();
				if(html.indexOf("add") > -1
					|| html.indexOf("edit") > -1
					|| html.indexOf("delete") > -1
					|| html.indexOf("define") > -1
					|| html.indexOf("realiquot") > -1
					|| html.indexOf("remove") > -1
					|| html.indexOf("save") > -1
					|| html.indexOf("batch") > -1
				){
					$(selectElement).val(-1);
				}else{
					$(selectElement).val("");
				}
			});
		});
		$(".tree_root").find("select").first().val(1);
	}else{
		//acos ids operations
		data.allow = data.allow.split(",");
		data.deny = data.deny.split(",");

		$(".tree_root").find("select").val("");
		for(var i in data.allow){
			$(".tree_root").find("select[name=data\\[" + data.allow[i] + "\\]\\[Aco\\]\\[state\\]]").val(1);
		}
		for(var i in data.deny){
			$(".tree_root").find("select[name=data\\[" + data.deny[i] + "\\]\\[Aco\\]\\[state\\]]").val(-1);
		}
	}
	$("#loadPresetPopup").popup('close');
}

function savePresetPopup(){
	if($("#savePresetPopup").length == 0){
		buildDialog("savePresetPopup", null, null, null);
		$("#savePresetPopup").find("div").first().html("<div class='loading'>--- " + STR_LOADING + " ---</div>");
		$.get(root_url + "Administrate/Permissions/savePreset/", null, function(data){
			var isOpened = $("#savePresetPopup:visible").length; 
			$("#savePresetPopup").popup('close');
			$("#savePresetPopup").find("div").first().html(data);
			if(isOpened){
				$("#savePresetPopup").popup();
				$("#savePresetPopup input:first").focus();
			}
		});
	}

	$("#savePresetPopup").popup();
	$("#savePresetPopup input:first").focus();
}

function savePreset(){
	$(".tree_root").find("a.submit").hide();
	var allow = new Array();
	var deny = new Array();
	$(".tree_root").find("select").each(function(){
		if($(this).val() == 1){
			allow.push($(this).attr("name").match("[0-9]+")[0]);
		}else if($(this).val() == -1){
			deny.push($(this).attr("name").match("[0-9]+")[0]);
		}
	});
	$("#savePresetPopup").find("form").append(
		"<input name='data[0][allow]' type='hidden' value='" + allow.join(",") + "'/>" +
		"<input name='data[0][deny]' type='hidden' value='" + deny.join(",") + "'/>"
	);
	$.post(root_url + "Administrate/Permissions/savePreset/", $("#savePresetPopup").find("form").serialize(), function(data){
		if(data == 200){
			$("#savePresetPopup").popup('close');
			$("#savePresetPopup").remove();
			loadPresetFrame();
		}else{
			var isVisible = $("#savePresetPopup:visible").length;
			$("#savePresetPopup").popup('close');
			$("#savePresetPopup").find("div").first().html(data);
			if(isVisible){
				$("#savePresetPopup").popup();
			}
		}
	});
}
</script>
