var toolTarget = null;
var useHighlighting = jQuery.browser.msie == undefined || jQuery.browser.version >= 9;
var submitData = new Object();
var orgAction = null;
var removeConfirmed = false;
var contentMargin = parseInt($("#wrapper").css("border-left-width")) + parseInt($("#wrapper").css("margin-left"));
var sessionTimeout = new Object();


jQuery.fn.fullWidth = function(){
	return parseInt($(this).width()) + parseInt($(this).css("margin-left")) + parseInt($(this).css("margin-right")) + parseInt($(this).css("padding-left")) + parseInt($(this).css("padding-right")) + parseInt($(this).css("border-left-width")) + parseInt($(this).css("border-right-width")); 
};

var header_total_width = $("#header div:first").fullWidth() + $("#header div:first").offset().left;

//Slide down animation (show) for action menu
var actionMenuShow = function(){
	var action_hover = $(this);
	var action_popup = action_hover.find('div.filter_menu');
	if ( action_popup.length > 0 ) {
		//show current menu
		action_popup.slideDown(100);
	}
};
	
//Slide up (hide) animation for action menu.
var actionMenuHide = function(){
	var action_hover = $(this);
	var action_popup = action_hover.find('div.filter_menu');
	if (action_popup.length > 0) {
		action_popup.slideUp(100).queue(function(){
			$(this).clearQueue();
		});
	}
};

var menuMoveDistance = 174;
var actionClickUp = function() {
	var span_up = $(this);
	var move_ul = span_up.parent('div.filter_menu').find('ul');
	
	var position = move_ul.position();
	
	// only scroll if not already at edge...
	if ( position.top < 0 ) {
		
		move_ul.animate(
			{
				top: '+=' + menuMoveDistance
			},
			150,
			'linear'
		);
		
	}
	
	return false;
};

var actionClickDown = function() {
	
	var span_up = $(this);
	var move_ul = span_up.parent('div.filter_menu').find('ul');
	
	var position = move_ul.position();
	
	
	// only scroll if not already at edge...
	if ( (position.top - menuMoveDistance) > (-1 * move_ul.height()) ) {
		move_ul.animate(
			{
				top: '-=' + menuMoveDistance
			},
			150,
			'linear'
		);
	}
	
	return false;
};

/**
 * Handling mouse scroll on action menus. Sending up/down commands if there is
 * no ongoing animations
 * @param event
 * @param delta
 * @returns false so that the page never scrolls
 */
function actionMouseweelHandler(event, delta){
	if($(event.currentTarget).find("ul:animated").length == 0){
		if(delta > 0){
			$(event.currentTarget).find(".up").click();
		}else{
			$(event.currentTarget).find(".down").click();
		}
	}
	return false;
}

/**
 * Inits actions bars (main one and ajax loaded ones). Unbind the actions before rebinding them to avoid duplicate bindings
 */
function initActions(){
	$('div.actions div.bottom_button').unbind('mouseenter', actionMenuShow).unbind('mouseleave', actionMenuHide).bind('mouseenter', actionMenuShow).bind('mouseleave', actionMenuHide);
	$('div.actions a.down').unbind('click', actionClickDown).click(actionClickDown);
	$('div.actions a.up').unbind('click', actionClickUp).click(actionClickUp);
	$('div.filter_menu.scroll').bind('mousewheel', actionMouseweelHandler);
	
	if(window.menuItems){
		function actionDisplay(data){
			return '<span class="row" id="' + data.value + '"><span class="cell"><span class="icon16 ' + (data.style ? data.style : 'blank') + '"></span></span><span class="cell" style="padding-left: 5px;">' + data.label + '</span></span>';
		}
		
		function validateSubmit(){
			var errors = new Array();
			if($("#actionsTarget input[type=hidden]").val() == ""){
				errors.push(errorYouMustSelectAnAction);
			}
			if($(":checkbox").length > 0 && $(":checkbox:checked").length == 0){
				errors.push(errorYouNeedToSelectAtLeastOneItem);
			}else if($("form").prop("action").indexOf("remove") != -1 && !removeConfirmed){
				//popup do you wish do remove
				$("#popup").popup();
				return false;
			}
			
			if(errors.length > 0){
				alert(errors.join("\n"));
				return false;
			}
			
			return true;
		}
		
		if(menuItems.length > 0){
    		$("#actionsTarget").fmMenu({
    			data : $.parseJSON(menuItems), 
    			displayFunction : actionDisplay, 
    			defaultLabel : STR_SELECT_AN_ACTION, 
    			inputName : "data[Browser][search_for]",
    			strBack : STR_BACK
    		});
		}
		
		if(!window.errorYouMustSelectAnAction){
			window.errorYouMustSelectAnAction = "js untranslated errorYouMustSelectAnAction";	
		}
		if(!window.errorYouNeedToSelectAtLeastOneItem){
			window.errorYouNeedToSelectAtLeastOneItem = "js untranslated errorYouNeedToSelectAtLeastOneItem";	
		}

		orgAction = $("form").prop("action");

		$("a.submit").unbind('click').prop("onclick", "").click(function(){
			if(validateSubmit()){
				var action = null;
				var actionTargetValue = $("#actionsTarget input[type=hidden]").val();
				if(actionTargetValue.indexOf('javascript:') == 0){
					action = actionTargetValue;
				}else if(isNaN(actionTargetValue[0])){
					action = root_url + actionTargetValue; 
				}else{
					action = orgAction + actionTargetValue;
				}
				$(this).parents("form:first").prop("action", action).submit();
			}
			return false;
		});

		//popup to confirm removal (batchset)
		$(".button.confirm").click(function(){
			removeConfirmed = true;
			$("#popup").popup('close');
			$("form").submit();
		});
		$(".button.close").click(function(){
			$("#popup").popup('close');
		});

	}
}

	function initDatepicker(scope){
		$(scope).find(".datepicker").each(function(){
			var dateFields = $(this).parent().parent().find('input, select');
			var yearField = null;
			var monthField = null;
			var dayField = null;
			var date = null;
			for(var i = 0; i < dateFields.length; i ++){
				var tmpStr = $(dateFields[i]).prop("name");
				var tmpLen = tmpStr.length;
				if(dateFields[i].nodeName != "SPAN"){
					if(tmpStr.substr(tmpLen - 7) == "][year]"){
						yearField = dateFields[i];
					}else if(tmpStr.substr(tmpLen - 8) == "][month]"){
						monthField = dateFields[i];
					}else if(tmpStr.substr(tmpLen - 6) == "][day]"){
						dayField = dateFields[i];
					}
				}
			}
			if($(yearField).val().length > 0 && $(monthField).val().length > 0 && $(dayField).val().length > 0){
				date = $(yearField).val() + "-" + $(monthField).val() + "-" + $(dayField).val();
				//set the current field date into the datepicker
				$(this).data(date);
			}
			
			$(this).datepicker({
				changeMonth: true,
				changeYear: true,
				dateFormat: 'yy-mm-dd',
				maxDate: '2100-12-31',
				yearRange: '-100:+100',
				firstDay: 0,
				beforeShow: function(input, inst){
					//put the date back in place
					//because of datagrids copy controls we cannot keep the date in tmp
					var month = parseInt($(monthField).val(), 10);
					var day = parseInt($(dayField).val(), 10);
					if(isNaN(month)){
						month = "";
					}else if(month < 10 && month > 0){
						month = "0" + month;
					}
					if(isNaN(day)){
						day = "";
					}else if(day < 10 && day > 0){
						day = "0" + day;
					}	
					var tmpDate = $(yearField).val() + "-" + month + "-" + day;
					if(tmpDate.length == 10){
						$(this).datepicker('setDate', tmpDate);
						
					}
				},
				onClose: function(dateText,picker) {
					//hide the date
					$(this).val(" ");//space required for Safari and Chome or the button disappears
					var dateSplit = dateText.split(/-/);
					if(dateSplit.length == 3){
						$(yearField).val(dateSplit[0]); 
			        	$(monthField).val(dateSplit[1]);
			        	$(dayField).val(dateSplit[2]);
					}
			    }
			});
			
			//bug fix for Safari and Chrome
			$(this).click(function(){
				$(this).datepicker('show');
			});
		});
		
		$(scope).find(".tooltip").each(function() {
			$(this).find("input").each(function(){
				$(this).focus(function(){
					//fixes a datepicker issue where the calendar stays open
					$("#ui-datepicker-div").stop(true, true);
					$(".datepicker").datepicker('hide');
					$(this).parent().find("div").css("display", "inline-block");
				});
				$(this).blur(function(){
					$(this).parent().find("div").css("display", "none");
				});
			});
			$(this).find("div").addClass("ui-corner-all").css({"border" : "1px solid", "padding" : "3px"});
		});
	}
	
	function setFieldSpan(clickedButton, spanClassToDisplay){
		$(clickedButton).parent().find("a").show();
		$(clickedButton).hide();
		$(clickedButton).parent().children("span").hide().each(function(){
			//store all active field names into their data and remove the name
			$(this).find("input, select").each(function(){
				if($(this).prop('name').length > 0){
					$(this).data('name', $(this).prop('name')).prop('name', ''); 
				}
			});
		});
		$(clickedButton).parent().find("span." + spanClassToDisplay).show().find('input, select').each(function(){
			//activate names of displayed fields
			$(this).prop('name', $(this).data('name'));
		});
	}
	
	/**
	 * Advanced controls are search OR options and RANGE buttons
	 */
	function initAdvancedControls(scope){
		//for each add or button
		$(scope).find(".btn_add_or").each(function(){
			var $field = $(this).prev();
			//non range value, the OR is made to allow fields with CSV to be renamed but not cloned
			if($($field).find("input, select").length == 1 || ($($field).find("input").length == 2 && $($($field).find("input")[1]).prop("type") == "file")){
				$($field).find("input, select").first().each(function(){
					$(this).prop("name", $(this).prop("name") + "[]");
				});
				
				if($($field).find("input").length == 2){
					//rename complete, hide and bail out
					$(this).remove();
					return;
				}
				//html template for that field
				var fieldHTML = $($field).html();
				
				//when we click
				$(this).click(function(){
					//append it into the text field with "or" string + btn_remove
					$(this).parent().append("<span class='adv_ctrl " + $($field).prop("class") + "' style='" + $($field).prop("style") + "'>" + STR_OR + " " + fieldHTML + "<a href='#' onclick='return false;' class='adv_ctrl btn_rmv_or icon16 delete_mini'></a></span> ");
					//find the newly generated input
					var $newField = $(this).parent().find("span.adv_ctrl:last");
					
					initDatepicker($newField);
					
					initAutocomplete($newField);
					initToolPopup($newField);
					
					//bind the remove command to the remove button
					$(this).parent().find(".btn_rmv_or:last").click(function(){
						$(this).parent().remove();
					});
					//move the add button to the end
					$(this).parent().append($(this));
					
					if(useHighlighting){
						//reset the highlighting
						$('form').highlight('tr');
					}
				});
			}else{
				//range values, no add options
				$(this).remove();
			}
		});
		
		if($(scope).find(".btn_add_or:first").length == 1){
			var tabindex = null;
			$(scope).find(".range").each(function(){
				//uses .btn_add_or to know if this is a search form and if advanced controls are on
				var cell = $(this).parent().parent().parent(); 
				$(cell).append(" <a href='#' class='icon16 range range_btn' title='" + STR_RANGE + "'></a> " +
						"<a href='#' class='icon16 specific specific_btn'></a>").data('mode', 'specific').find(".specific_btn").hide();
				$(cell).find("span:first").addClass("specific_span");
				
				var baseName = $(cell).find("input").prop("name");
				baseName = baseName.substr(0, baseName.length - 3);
				tabindex = $(cell).find("input").prop("tabindex");
				$(cell).prepend("<span class='range_span hidden'><input type='text' tabindex='" + tabindex + "' name='" + baseName + "_start]'/> " 
						+ STR_TO 
						+ " <input type='text' tabindex='" + tabindex + "' name='" + baseName + "_end]'/></span>");					
			});
			
			$(scope).find(".file").each(function(){
				var cell = $(this).parent().parent().parent();
				$(cell).append(" <a href='#' class='icon16 csv_upload file_btn'></a>").data('mode', 'specific');
				
				if($(cell).find(".specific_btn").length == 0){
					$(cell).append(" <a href='#' class='icon16 specific specific_btn'></a>").find(".specific_btn").hide();
					$(cell).find("span:first").addClass("specific_span");
					tabindex = $(cell).find("input").prop("tabindex");
				}
				var name = $(cell).find("input:last").prop("name");
				name = name.substr(0, name.length -3) + "_with_file_upload]";
				$(cell).prepend("<span class='file_span hidden'><input type='file' tabindex='" + tabindex + "' name='" + name + "'/></span>");
			});
			//store hidden field names into their data
			$(scope).find("span.range_span input, span.file_span input").each(function(){
				$(this).data('name', $(this).prop('name')).prop('name', "");
			});
			
			//trigger buttons
			$(scope).find(".range_btn").click(function(){
				setFieldSpan(this, "range_span");
				return false;
			});
			$(scope).find(".specific_btn").click(function(){
				setFieldSpan(this, "specific_span");
				return false;
			});
			$(scope).find(".file_btn").click(function(){
				setFieldSpan(this, "file_span");
				return false;
			});
		}
	}
	
	/**
	 * Remove the row that contains the element
	 * @param element The element contained within the row to remove
	 */
	function removeParentRow(element){
		element = $(element).parents("tr:first");
		
		if($(element)[0].nodeName == "TR"){
			$(element).remove();
		}
	}
	
	function initAutocomplete(scope){
		$(scope).find(".jqueryAutocomplete").each(function(){
//			var element = $(this);
			$(this).autocomplete({
				//if the generated link is ///link it doesn't work. That's why we have a "if" statement on root_url
				source: (root_url == "/" ? "" : root_url) + $(this).attr("url")
				//alternate source for debugging
//				source: function(request, response) {
//					$.post(root_url + "/" + $(element).attr("url"), request, function(data){
//						console.log(data);
//					});
//				}
			});
		});
	}
	
	function initAliquotVolumeCheck(){
		var checkFct = function(){
			var fctMod = function(param){ return parseFloat(param.replace(/,/g, ".")); };
			var denom = fctMod($("#AliquotMasterCurrentVolume").val());
			var nom = fctMod($("#AliquotUseUsedVolume").val());
			if($("#AliquotUseUsedVolume").val().length > 0 && nom > denom){
				$("input, textarea, a").blur();
				$("#popup").popup();
				return false;
			}else{
				$("#submit_button").click();
			}
		};
		
		$("form").submit(checkFct);
		$(".form.submit").unbind('click').prop("onclick", "return false;");
		$(".form.submit").click(checkFct);

		$(".button.confirm").click(function(){
			$("#popup").popup('close');
			$("#submit_button").click();
		});
		$(".button.close").click(function(){
			$("#popup").popup('close');
		});
	}
	
	function refreshTopBaseOnAction(){
		$("form").prop("action", root_url + actionControl + $("#0Action").val());
	}
	
	function initActionControl(actionControl){
		$($(".adv_ctrl.btn_add_or")[1]).parent().parent().find("select").change(function(){
			refreshTopBaseOnAction(actionControl);
		});
		$(".adv_ctrl.btn_add_or").remove();
		refreshTopBaseOnAction(actionControl);
	}
	
	/**
	 * Initialises check/uncheck all controls
	 * @param scope The scope where to look for those controls. In a popup, the scope will be the popup box
	 */
	function initCheckAll(scope){
		$(scope).find(".checkAll").each(function(){
			var elemParent = $(this).parents("table:first");
			$(this).click(function(){
				$(elemParent).find('input[type=checkbox]').prop("checked", true);
				$(elemParent).find('input[type=checkbox]:first').parents("tr:first").addClass("chkLine").siblings().addClass("chkLine");
				return false;
			});
			$(elemParent).find(".uncheckAll").click(function(){
				$(elemParent).find('input[type=checkbox]').prop("checked", false);
				$(elemParent).find('input[type=checkbox]:first').parents("tr:first").removeClass("chkLine").siblings().removeClass("chkLine");
				return false;
			});
		});
	}
	
	/**
	 * @param id
	 * @param title
	 * @param content
	 * @param buttons Array containing json containing keys icon, label and action
	 */
	function buildDialog(id, title, content, buttons){
		var buttonsHtml = "";
		if(buttons != null && buttons.length > 0){
			for(i in buttons){
				buttonsHtml += 
					'<div id="' + id + i +'" class="bottom_button"><a href="#" class="' + buttons[i].icon + '"><span class="icon16 ' + buttons[i].icon + '"></span>' + buttons[i].label + '</a></div>';
			}
			buttonsHtml = '<div class="actions split">' + buttonsHtml + '</div>';
		}
		$("#" + id).remove();
		$("body").append('<div id="' + id + '" class="std_popup question">' +
			'<div class="wrapper">' +
				'<h4>' + title + '</h4>' +
				(content == null ? '' : ('<div style="padding: 10px; background-color: #fff;">' + content + '</div>')) +
				buttonsHtml +
			'</div>' +
		'</div>');
		
		for(i in buttons){
			$("#" + id + i).click(buttons[i].action);
		}
	}
	
	function buildConfirmDialog(id, question, buttons){
		buildDialog(id, question, null, buttons);
	}
	
	//tool_popup
	function initToolPopup(scope){
		$(scope).find(".tool_popup").click(function(){
			var parent_elem = $(this).parent().children();
			toolTarget = null;
			for(var i = 0; i < parent_elem.length; i ++){
				//find the current element
				if(parent_elem[i] == this){
					for(var j = i - 1; j >= 0; j --){
						//find the previous input
						if(parent_elem[j].nodeName == "INPUT"){
							toolTarget = parent_elem[j];
							break;
						}
					}
					break;
				}
			}
			$.get($(this).prop("href"), null, function(data){
				$("#default_popup").html("<div class='wrapper'><div class='frame'>" + data + "</div></div>").popup();
				$("#default_popup input[type=text]").first().focus();
			});
			return false;
		});
	}
	
	function initAddLine(scope){
		$(scope).find(".addLineLink").each(function(){
			//get the table row
			var table = $(this).parents("table:first");
			var tableBody = $(table).find("tbody");
			var lastLine = $(tableBody).find("tr:last");
			var templateLineHtml = lastLine.html();
			$(lastLine).remove();
			var lineIncrement = $(tableBody).find("tr").length;
			$(this).click(function(){
				var counter = $(table).find(".addLineCount").length == 1 ? $(table).find(".addLineCount").val() : 1;
				while(counter > 0){
					$(tableBody).append("<tr class='newLine'>" + templateLineHtml.replace(/\[%d\]\[/g, '[' + lineIncrement ++ + '][') + "</tr>");
					counter --;
				}
				var newLines = $(tableBody).find("tr.newLine");
				if(newLines.length){
					initAutocomplete(newLines);
					initDatepicker(newLines);
					initToolPopup(newLines);
					initCheckboxes(newLines);
					$('form').highlight('td');
					if(window.copyControl){
						bindCopyCtrl(newLines);
					}
					if(window.labBookFields){
						initLabBook(newLines);
					}
					initAccuracy(newLines);
					initFlyOverCellsLines(newLines);
					flyOverComponents();
					
					
					$(newLines).removeClass("newLine");
				}
				return false;
			});
		});
		
		$(scope).find(".addLineCount").keydown(function(e){
			if(e.keyCode == 13){
				return false;
			}
		}).keyup(function(e){
			if(e.keyCode == 13){
				$(this).siblings(".addLineLink").click();
				return false;
			}
		});
		
		if($(".jsChronology").length > 0){
			$(".jsChronology").each(function(){
				var href = $(this).attr("href");
				if(href.indexOf('/Participants/') != -1){
					$(this).addClass('participant');
				}else if(href.indexOf('/DiagnosisMasters/') != -1){
					$(this).addClass('diagnosis');
				}else if(href.indexOf('/TreatmentMasters/') != -1){
					$(this).addClass('treatments');
				}else if(href.indexOf('/Collections/') != -1){
					$(this).addClass('collection');
				}else if(href.indexOf('/EventMasters/') != -1){
					$(this).addClass('annotation');
				}else if(href.indexOf('/ConsentMasters/') != -1){
					$(this).addClass('consents');
				}
			}).click(function(){
				var link = this;
				//remove highlighted stuff
				var td = $(".at").removeClass("at").find("td:last-child");
				$(td).html($(td).data('content'));
				var tr = $(link).parents("tr").first().addClass("at");
				td = $(tr).find("td").last();
				$(td).data('content', $(td).html());
				$(td).html('<div style="position: relative;">' + $(td).html() + '<div class="treeArrow" style="display: block"></div></div>');
				
				if($(link).data('cached_result')){
					$("#frame").html($(link).data('cached_result'));
					initActions();
				}else{
					$("#frame").html("<div class='loading'></div>");
					$.get($(this).attr("href") + "?t=" + new Date().getTime(), function(data){
						$("#frame").html(data);
						$(link).data('cached_result', data);
						initActions();
					});
				}
				return false;
			});
			$(".this_column_1.total_columns_1").removeClass("total_columns_1").addClass("total_columns_2").css("width", "1%").parent().append(
					'<td class="this_column_2 total_columns2"><div id="frame"></td>'
			);
			
		}
	}
	
	function removeLine(){
		var floatingBckGrnd = $(this).parents("table:first").find(".floatingBckGrnd");
		$(this).parents("tr:first").remove();
		resizeFloatingBckGrnd(floatingBckGrnd);
		return false;
	}
	
	function initAjaxClass(scope){
		//ajax controls
		//evals the json within the class of the element and calls the method defined in callback
		//the callback method needs to take this and json as parameters
		$(scope).find(".ajax").click(function(){
			try{
				json = $(this).data('json');
				var fct = eval("(" + json.callback + ")");
				fct.apply(this, [this, json]);
			}catch(e){
				console.log(e);
			}
			return false;
		});
	}
	
	function initLabBook(scope){
		var fields = new Array();
		var checkbox = null;
		var codeInputField = null;
		
		$(scope).find("input, select, textarea").each(function(){
			var currName = $(this).prop("name");
			for(var i in labBookFields){
				if(labBookFields[i].length == 0){
					continue;
				}
				if(currName.indexOf(labBookFields[i]) > -1){
					fields.push($(this));
					var parentTd = $(this).parents("td:first");
					if($(parentTd).find(".labBook").length == 0){
						$(this).after("<span class='labBook'>[" + STR_LAB_BOOK + "]</span>");
					}
					fields.push($(parentTd).find(".datepicker, .accuracy_target_blue"));
				}
			}
			if(currName.indexOf("[sync_with_lab_book]") > 0){
				checkbox = $(this);
			}else if(currName.indexOf("[lab_book_master_code]") > 0){
				codeInputField = $(this);
			}
		});
		
		var tmpFunc = function(){
			labBookFieldsToggle(scope, fields, codeInputField, checkbox);
		}; 
		
		if(checkbox != null){
			$(checkbox).click(tmpFunc);
		}
		if(codeInputField != null){
			$(codeInputField).change(tmpFunc).keyup(tmpFunc).blur(tmpFunc);
		}
		
		if(window.labBookHideOnLoad){
			$(fields).toggle();
			$(scope).find(".labBook").show();
		}else{
			tmpFunc();
		}
	}
	
	function labBookFieldsToggle(scope, fields, codeInputField, checkbox){
		var toggle = false;
		if($(scope).find(".labBook:visible").length == 0){
			//current input are visible, see if we need to hide
			if((checkbox != null && $(checkbox).prop("checked")) || (codeInputField != null && $(codeInputField).val().length > 0)){
				toggle = true;
			}
		}else{
			//current input are hidden, see if we need to display
			if((checkbox == null || !$(checkbox).prop("checked")) && (codeInputField == null || $(codeInputField).val().length == 0)){
				toggle = true;
			}
		}
		if(toggle){
			$(fields).toggle();
			$(scope).find(".labBook").toggle();
		}
	}
	
	function initLabBookPopup(){
		$("div.bottom_button a:not(.not_allowed).add").first().click(function(){
			$.get($(this).prop("href"), labBookPopupAddForm);
			return false;
		});
	}
	
	function labBookPopupAddForm(data){
		$("#default_popup").html("<div class='wrapper'><div class='frame'>" + data + "</div></div>").popup();
		initDatepicker("#default_popup");
		initAccuracy("#default_popup");
		$("#default_popup a.submit").unbind('click').prop('onclick', '').click(function(){
			$(this).hide();
			$.post($("#default_popup form").prop("action"), $("#default_popup form").serialize(), function(data2){
				if(data2.length < 100){
					//saved
					$("#default_popup").popup('close');
					$("input, select").each(function(){
						if($(this).prop("name").indexOf('lab_book_master_code') != -1){
							$(this).val(data2);
						}
					});
				}else{
					//redisplay
					labBookPopupAddForm(data2);
				}
			});
			return false;
		});
		$("#default_popup form").submit(event, function(){
			event.preventDefault();
			return false;
		});
		$("#default_popup input[type=text]").first().focus();
	}
	
	function initCheckboxes(scope){
		$(scope).find("input[type=checkbox]").each(function(){
			if(!$(this).data("exclusive")){
				var checkboxes = $(this).parent().find("input[type=checkbox]");
				$(checkboxes).each(function(){
					$(this).data("exclusive", true);
				});
				$(checkboxes).click(function(){
					var checked = $(this).prop("checked"); 
					$(checkboxes).prop("checked", false);
					$(this).prop("checked", checked);
				});
			}
		});
	}
	
	function initAccuracy(scope){
		$(scope).find(".accuracy_target_blue").click(function(){
			if($(this).find("input").length == 0){
				//accuracy going to year
				$(this).parent().find("input, select").each(function(){
					if($(this).prop("name").indexOf("year") == -1){
						$(this).hide();
					}
				});
				var name = $(this).parent().find("input, select").first().prop("name");
				$(this).html("<input type='hidden' class='accuracy' name='" + name.substr(0, name.lastIndexOf("[")) + "[year_accuracy]' value='1'/>");
			}else{
				//accuracy going to manual
				$(this).html("");
				$(this).parent().find("input, select").show();
			}
			return false;
		});
		
		$(scope).find(".accuracy_target_blue").each(function(){
			if($(this).siblings().find(".labBook:visible").length > 0){
				$(this).hide();
			}else{
				var current_accuracy_btn = this;
				$(this).parent().find("input, select").each(function(){
					if($(this).prop("name").indexOf("year") != -1 && $(this).hasClass('year_accuracy')){
						$(this).removeClass('year_accuracy');
						$(current_accuracy_btn).click();
					}
				});
			}
		});
	}
	
	/**
	 * Moves the submit button so that it always appear in the screen.
	 * Moves the top right menu so that it's almost always in the screen. It 
	 * will not overlap with the title.
	 */
	function flyOverComponents(){
		var scrollLeft = $(document).scrollLeft();
		//submit
		$(".flyOverSubmit").each(function(){
			if($(this).parents(".popup_container:first").length == 0){
				$(this).css("right", (Math.max($(".submitBar").width() - $(window).width() - scrollLeft + 20, 0)) + "px");
			}
		});
		
		//top menu
		var r_pos = $(document).width() - $(window).width() - $(document).scrollLeft() + 10;
		var l_pos = $(window).width() + scrollLeft - $(".root_menu_for_header").width();
		if(l_pos < header_total_width){
			r_pos -= header_total_width - l_pos;
		}
		
		$(".root_menu_for_header, .main_menu_for_header").css("right", r_pos);
		
		//cells
		if(scrollLeft > contentMargin){
			$(".floatingBckGrnd .left").css("opacity", 1);
		}else{
			$(".floatingBckGrnd .left").css("opacity", 0);
		}
		$(".floatingBckGrnd .right div").css("opacity", Math.min(15, scrollLeft) * 0.03);
		$(".testScroll").css("left", scrollLeft);
	}
	
	function initAutoHideVolume(){
		$("input[type=radio]").click(function(){
			if($.inArray($(this).val(), volumeIds) > -1){
				$("input[name=data\\[QualityCtrl\\]\\[used_volume\\]]").attr("disabled", false);
			}else{
				$("input[name=data\\[QualityCtrl\\]\\[used_volume\\]]").attr("disabled", true).val("");
			}
		});
		$("input[type=radio]:checked").click();
	}
	
	function handleSearchResultLinks(){
		$(".ajax_search_results thead a, .ajax_search_results tfoot a").click(function(){
			$(".ajax_search_results").html("<div class='loading'>--- " + STR_LOADING + " ---</div>");
			$.get($(this).attr("href"), function(data){
				try{
					data = $.parseJSON(data);
					$(".ajax_search_results").html(data.page);
					history.replaceState(data.page, "foo");//storing result in history
					handleSearchResultLinks();
				}catch(exception){
					//simply submit the form then
					document.location = $(this).attr("href"); 
				}				
			});
			return false;
		});
	}
	
	function databrowserToggleSearchBox(cell){
		$(cell).parent().find("span, a").toggle();
		return false;
	}
	
	function warningMoreInfoClick(event){
		//only called on the first click of each element, then toggle function handles it
		if($(event.target).data('opened')){
			$(this).html("[+]").siblings("pre.warningMoreInfo").hide();
			$(event.target).data('opened', false);
		}else{
			$(this).html("[-]").siblings("pre.warningMoreInfo").show();
			$(event.target).data('opened', true);
		}
	}
	
	function sessionExpired(){
		if($("#loginPopup").length == 0){
			$("body").append("<div id='loginPopup' class='std_popup'><div class='loading'>--- " + STR_LOADING + " ---</div></div>");
		}
		var submitFunction = function(){
			$.post(root_url + "Users/Login/login:/", $("#loginPopup form").serialize(), function(data){
				if(data.indexOf('ok') == 0){
					$.get(root_url + "Menus", function(){
						//dumb call to refresh cookies
						$("#loginPopup").popup('close');
					});
				}else{
					$("#loginPopup").html("<div class='wrapper'>" + data + "</div>");
					$("#loginPopup").popup();
					$("#loginPopup a.submit").click(submitFunction);
				}
			});
			$("#loginPopup").html("<div class='loading'>--- " + STR_LOADING + " ---</div>");
			return false;
		};
		
		$.get(root_url + "Users/Login/login:/", function(data){
			$("#loginPopup").html("<div class='wrapper'>" + data + "</div>");
			$("#loginPopup").popup();
			$("#loginPopup form").submit(submitFunction);
		});
		$("#loginPopup").popup({closable:false, background:"black"});
	}
	
	function cookieWatch(){
		if($.cookie("session_expiration")){
			if(!sessionTimeout.lastRequest || sessionTimeout.lastRequest != $.cookie("last_request")){
				//5 to 1 second earlier expiration (due to 4 secs error margin)
				sessionTimeout.lastRequest = $.cookie("last_request");
				sessionTimeout.expirationTime = new Date().getTime() - sessionTimeout.serverOffset + (($.cookie("session_expiration") - $.cookie("last_request") - 5) * 1000);
			}
			if(sessionTimeout.expirationTime > new Date().getTime() && $("#loginPopup:visible").length == 1){
				$("#loginPopup").popup('close');
			}
		}
		
		if(sessionTimeout.expirationTime && sessionTimeout.expirationTime <= new Date().getTime() && $("#loginPopup:visible").length == 0){
			sessionExpired();
		}
		
		setTimeout(cookieWatch, 4000);//4 seconds error margin
	}
	
	
	function initJsControls(){
		if(history.replaceState){
			if(!history.state){
				history.replaceState(new Object(), "foo");
			}
		}
		
		if(window.storageLayout){
			initStorageLayout();
		}
		if(window.copyControl){
			initCopyControl();
		}
		if(window.aliquotVolumeCheck){
			initAliquotVolumeCheck();
		}
		if(window.actionControl){
			initActionControl(window.actionControl);
		}
		if(window.ccl){
			initCcl();
		}
		if(window.batchSetControls){
			initBatchSetControls();
		}
		if(window.treeView){
			initTreeView(document);
		}
		if(window.labBookFields){
			initLabBook(document);
		}
		if(window.labBookPopup){
			initLabBookPopup();
		}
		if(window.dropdownConfig){
			initDropdownConfig();
		}
		if(window.volumeIds){
			initAutoHideVolume();
		}
		if(window.permissionPreset){
			loadPresetFrame();
		}
		if(window.wizardTreeData){
			drawTree($.parseJSON(window.wizardTreeData));
		}
		if($(".ajax_search_results").length == 1){
			$(".ajax_search_results").parent().hide();
			if(history.replaceState){
				//doesn't work for IE < 10
				//TODO: prevent over clicking the submit btn
				beforeSubmitFct = function(){
					$("#footer").height(Math.max($("#footer").height(), $(".ajax_search_results").height()));//made to avoid page movement
					$(".ajax_search_results").html("<div class='loading'>--- " + STR_LOADING + " ---</div>");
					$(".ajax_search_results").parent().show();
					flyOverComponents();
				};
				successFct = function(data){
					try{
						data = $.parseJSON(data);
						$(".ajax_search_results").html(data.page);
						history.replaceState(data.page, "foo");//storing result in history
						//update the form action
						$("form").attr("action", $("form").attr("action").replace(/[0-9]+(\/)*$/, data.new_search_id + "$1"));
						handleSearchResultLinks();
						//stop submit button animation
						$("input.submit").siblings("a").find("span").removeClass('fetching');
						flyOverComponents();
					}catch(exception){
						$(".ajax_search_results").html(data)
						$("input.submit").siblings("a").find("span").removeClass('fetching');
					}
				};
				$("form").ajaxForm({
					url		: $("form").attr("action"),
					success	: successFct,
					beforeSubmit: beforeSubmitFct,
					error : function(){console.log("ERROR");}
				});
			}
		}
		
		if(history.replaceState){
			window.onpopstate = function(event) {
				//retrieving result from history
				if(event.state == null){
					//new / refresh
					initIndexZones(false);
					if($(".ajax_search_results_default")){
						$(".ajax_search_results").html($(".ajax_search_results_default").html());
						$(".ajax_search_results").parent().show();
						$(".ajax_search_results_default").remove();
					}
				}else{
					//back/forward
					$(".ajax_search_results_default").remove();
					$(".ajax_search_results").html(event.state);
					$(".ajax_search_results").parent().show();
					handleSearchResultLinks();
					initIndexZones(true);
				}
			};
			
			if(navigator.userAgent.indexOf("Firefox") != -1){
				$(".ajax_search_results").html($(".ajax_search_results_default").html());
				$(".ajax_search_results").parent().show();
				$(".ajax_search_results_default").remove();
				handleSearchResultLinks();
				initIndexZones(false);
			}
		}else{
			//unknown, always consider new
			initIndexZones(false);
		}
		
		if(window.realiquotInit){
			$("a.submit").prop("onclick", "").unbind('unclick').click(function(){
				if($("select").val().length > 0){
					$("form").submit();
				}
				return false;
			});
		}
		
		initAutocomplete(document);
		initAdvancedControls(document);
		initToolPopup(document);
		initActions();
		initAjaxClass(document);
		initAccuracy(document);
		initAddLine(document);//must be before datepicker
		
		//calendar controls
		$.datepicker.setDefaults($.datepicker.regional[locale]);
		initDatepicker(document);
		
		initCheckAll(document);
		initCheckboxes(document);
		
		$(document).ajaxError(function(event, xhr, settings, exception){
			if(xhr.status == 403){
				//access denied, most likely a session timeout
				document.location = "";
			}
		});
		
		if(useHighlighting){
			//field highlighting
			if($("table.structure.addgrid, table.structure.editgrid").length == 1){
				//gridview
				$('form').highlight('td');
			}else{
				$('form').highlight('tr');
			}
		}
		
		//focus on first field
		$("input:visible, select:visible, textarea:visible").first().focus();
		
		//fly over submit button, always in the screen
		flyOverComponents();
		$(window).scroll(flyOverComponents).resize(flyOverComponents);
		
		if(window.initPage){
			initPage();
		}
		$("a.databrowserMore").click(function(){
			$(this).parent().find("span:not(:visible), a").toggle();
			return false;
		});
		
		$(document).delegate("a.submit", 'click', clickSubmitButton
		).delegate("form", "submit", standardSubmit
		).delegate(".jsApplyPreset", "click", function(){
			applyPreset($(this).data("json"));
			return false;
		}).delegate("a.delete:not(.noPrompt)", "click", openDeleteConfirmPopup
		).delegate(".reveal.notFetched", "click", treeViewNodeClick
		).delegate(".sectionCtrl", "click", sectionCtrl
		).delegate("a.warningMoreInfo", "click", warningMoreInfoClick
		).delegate("td.checkbox input[type=checkbox]", "click", checkboxIndexFunction
		).delegate(".lineHighlight table tbody tr", "click", checkboxIndexLineFunction
		).delegate(".removeLineLink", "click", removeLine
		).delegate("div.selectItemZone span.button", "click", selectedItemZonePopup);
		
		$(window).bind("pageshow", function(event){
			//remove the fetching class. Otherwise hitting Firefox back button still shows the loading animation
			//don't bother using console.log, console is not ready yet
			$(document).find('a.submit span.fetching').removeClass('fetching');
			
			//on login page, displays a warning if the server is more than ~2 min late compared to the client
			if(window.loginPage != undefined){
				//adding date to the request URL to fool IE caching
				$.get(root_url + 'Users/login/1?t=' + (new Date().getTime()), function(data){
					data = $.parseJSON(data);
					if(data.logged_in == 1){
						document.location = root_url;
					}else{ 
						var foo = new Date;
						if(data.server_time - parseInt(foo.getTime() / 1000) < -120){
							$("#timeErr").show();
						}
					}
				});
			}
		});
		
		initFlyOverCells(document);

		if($.cookie("session_expiration")){
			sessionTimeout.serverOffset = new Date().getTime() - $.cookie('last_request') * 1000;
			if(!window.loginPage){
				cookieWatch();
			}
		}
		
		flyOverComponents();
	}
	
	function initFlyOverCells(scope){
		$(scope).find("table.structure").each(function(){
			if($(this).find("th.floatingCell:first").length == 0){
				return true;
			}
			$(this).find("th.floatingCell:last").each(function(){
				var i = 1;
				$(this).addClass("testScroll");
				$(this).prevAll().each(function(){
					$(this).addClass("testScroll");
					var colspan = $(this).attr("colspan");
					if(colspan == undefined){
					    ++ i
					}else{
					    i += colspan * 1;
					}
					console.log(this);
				});
				for(var j = 1; j <= i; ++ j){
					$(this).parents("table").eq(0).find("tbody td:nth-child(" + j + ")").addClass("testScroll");
				}
			});
			firstTh = $(this).find("table").find("th.floatingCell").parents("tr:first").find("th:first");
			firstTh.html('<span style="position: relative; z-index:2">' + firstTh.html() + '</span>');
			firstTh.append('<div class="floatingBckGrnd"><div class="right"><div></div></div><div class="left"></div></div>');
			lastTh = $(this).find("th.floatingCell:last");
			lastTd = $(this).find("tbody tr:last td.testScroll:last");
			console.log(lastTd[0]);
			computeSum = function(obj, cssArr){
				total = 0;
				for(var i in cssArr){
					total += parseFloat(obj.css(cssArr[i]));
				}
				return total;
			};
			psSize = function(obj, direction){
				arr = ["margin-%s", "padding-%s", "border-%s-width"];
				newArr = [];
				for(var i in arr){
					newArr.push(arr[i].replace("%s", direction));
				}
				return computeSum(obj, newArr);
			};
			width = lastTh.width() + lastTh.position().left + psSize(lastTh, "right") - firstTh.position().left + psSize(firstTh, "left") + 1;
			height = Math.ceil(lastTd.position().top + lastTd.outerHeight() - firstTh.position().top);
			$(this).find(".floatingBckGrnd").css({
				"top" : 0,
				"left" : 0,
				"width" : width + "px",
				"height" : height + "px"
			});
		});

		$(".testScroll").css("position", "relative");
	}
	
	function globalInit(scope){
		if(window.copyControl){
			initCopyControl();
		}
		initAddLine(scope);
		initDatepicker(scope);
		initAutocomplete(scope);
		initCheckAll(scope);
		initCheckboxes(scope);
		initAccuracy(scope);
		initAdvancedControls(scope);
		initFlyOverCells(scope);

		if(window.labBookFields){
			initLabBook(scope);
		}
		
	}

	function treeViewNodeClick(event){
		var element = event.currentTarget;
		$(element).removeClass("notFetched").unbind('click');
		var json = $(element).data("json");
		var expandButton = $(element);
		if(json.url != undefined && json.url.length > 0){
			$(element).addClass("fetching");
			var flat_url = json.url.replace(/\//g, "_");
			if(flat_url.length > 0){
				$.get(root_url + json.url + "?t=" + new Date().getTime(), function(data){
					$("body").append("<div id='" + flat_url + "' style='display: none'>" + data + "</div>");
					if($("#" + flat_url).find("ul").length == 1){
						var currentLi = $(expandButton).parents("li:first");
						$(currentLi).append("<ul>" + $("#" + flat_url).find("ul").html() + "</ul>");
						initAjaxClass($(currentLi).find("ul"));
						$(expandButton).click(function(){
							$(currentLi).find("ul").first().stop().toggle("blind");
						});
					}
					$(expandButton).removeClass("fetching");
					$("#" + flat_url).remove();
					
					if(window.initTree){
						initTree($(currentLi));
					}
				});
			}
		}
	}
	
	/**
	 * Called when a detail view is displayed in within the same page as the tree view
	 * @param new_at_li
	 * @param json
	 */
	function set_at_state_in_tree_root(new_at_li, json){
		$(".tree_root").find("div.treeArrow").hide();
		$(".tree_root").find("div.rightPart").removeClass("at");
		$li = $(new_at_li).parents("li:first");
		$($li).find("div.rightPart:first").addClass("at");
		$($li).find("div.treeArrow:first").show();
		$("#frame").html("<div class='loading'>---" + STR_LOADING + "---</div>");
		$.get($(this).prop("href") + "?t=" + new Date().getTime(), {}, function(data){
			$("#frame").html(data);
			initActions();
		});
	}
	
	/**
	 * Called for tree views listing radio buttons (such as treatments, to select a dx)
	 * to display the selected element.
	 * @param scope
	 */
	function initTreeView(scope){
		$("a.reveal.activate").each(function(){
			var matchingUl	= $(this).parents("li:first").children().filter("ul").first();
			$(this).click(function(){
				$(matchingUl).stop().toggle("blind");
			});
		});
		
		var element = $(scope).find(".tree_root input[type=radio]:checked");
		if(element.length == 1){
			var lis = $(element).parents("li");
			lis[0] = null;
			$(lis).find("a.reveal.activate:first").click();
		}
	}
	
	function openDeleteConfirmPopup(event){
		if($("#deleteConfirmPopup").length == 0){
			var yes_action = function(){
				document.location = $("#deleteConfirmPopup").data('link');
				return false;
			};
			var no_action = function(){
				$("#deleteConfirmPopup").popup('close');
				return false;
			}; 
			
			buildConfirmDialog('deleteConfirmPopup', STR_DELETE_CONFIRM, new Array({label : STR_YES, action: yes_action, icon: "detail"}, {label : STR_NO, action: no_action, icon: "delete noPrompt"}));
		}
		
		$("#deleteConfirmPopup").popup();
		$("#deleteConfirmPopup").data('link', $(event.currentTarget).prop("href"));
		return false;
	}

	function openSaveBrowsingStepsPopup(link){
		$.get(root_url + link, null, function(data){
			data = $.parseJSON(data);
			$("#default_popup").html("<div class='wrapper'><div class='frame'>" + data.page + "</div></div>").popup();
			$("#default_popup input[type=text]").first().focus();
			$("#default_popup form").attr("action", 'javascript:popupSubmit("' + $("#default_popup form").attr("action") + '");');
		});
	}
	
	function popupSubmit(url){
		$.post(url, $("#default_popup form").serialize(), function(data){
			data = $.parseJSON(data);
			if(data.type == 'form'){
				$("#default_popup").html("<div class='wrapper'><div class='frame'>" + data.page + "</div></div>").popup();
				$("#default_popup input[type=text]").first().focus();
				$("#default_popup form").attr("action", 'javascript:popupSubmit("' + $("#default_popup form").attr("action") + '");');
			}else if(data.type == 'message'){
				$("#message").remove();
				buildDialog("message", data.message, null, [{icon : 'detail', action : function(){ $("#message").popup('close'); $("#default_popup").popup('close'); return false; }, label : STR_OK}]);
				$("#message").popup();
			}
		});
	}
	
	/**
	 * Will see if the last_request time has changed in order to stop the rotating beam. Used by CSV download.
	 */
	function fetchingBeamCheck(){
		if(submitData.lastRequest != $.cookie('last_request')){
			$(document).find('a.submit span.fetching').removeClass('fetching');
		}else{
			submitData.callBack = setTimeout(fetchingBeamCheck, 1000);
		}
	}
	
	/*
	 * Toggles display of batch entry sections
	 */
	
	function sectionCtrl(event){
		element = event.target;
		if($(element).hasClass('delete')){
			//hide the content in the button data
			$(element).parents(".descriptive_heading:first").nextAll(".section:first").appendTo("body").hide();
			$(element).data("section", $("body .section:last"));
			$(element).parents(".descriptive_heading:first").css("text-decoration", "line-through");
			$(element).removeClass('delete').addClass('redo');
		}else{
			$(element).parents(".descriptive_heading:first").after($(element).data("section").show());
			$(element).parents(".descriptive_heading:first").css("text-decoration", "none");
			$(element).addClass('delete').removeClass('redo');
		}
		
		resizeFloatingBckGrnd(null);
		
		if($(".descriptive_heading a.sectionCtrl.delete").length == 0){
			$(".flyOverSubmit").hide();
		}else{
			$(".flyOverSubmit").show();
		}
		
		flyOverComponents();
		return false;
	}
	
	/**
	 * When a checkbox is checked/unchecked, toggles line coloring. Support of shiftkey to select multiple lines.
	 * @param event
	 * @param orgEvent
	 */
	function checkboxIndexFunction(event, orgEvent){
		var shiftKey = orgEvent ? false : event.originalEvent.shiftKey;
		if(shiftKey){
			marking = true;
			checked = $(event.currentTarget).prop("checked");
			markingFct = function(){
				if(marking){
					$(this).find("td.checkbox input[type=checkbox]").attr("checked", checked);
					if(checked){
						$(this).addClass("chkLine");
					}else{
						$(this).removeClass("chkLine");
					}
					if($(this).hasClass("checkboxIndexFunctionMark")){
						marking = !marking;
					}
				}
			}; 
			if($(event.currentTarget).parents("tr:first").nextAll(".checkboxIndexFunctionMark").length){
				$(event.currentTarget).parents("tr:first").nextAll().each(markingFct);
			}else if($(event.currentTarget).parents("tr:first").prevAll(".checkboxIndexFunctionMark").length){
				$(event.currentTarget).parents("tr:first").prevAll().each(markingFct);
			}
		}
		$(".checkboxIndexFunctionMark").removeClass("checkboxIndexFunctionMark");
		$(event.currentTarget).parents("tr:first").addClass("checkboxIndexFunctionMark");
		if(orgEvent ? !$(event.currentTarget).prop("checked") : $(event.currentTarget).prop("checked")){
			$(event.currentTarget).parents("tr:first").addClass("chkLine");
		}else{
			$(event.currentTarget).parents("tr:first").removeClass("chkLine");
		}
		event.stopPropagation();
	}
	
	/**
	 * Clicking on a line with a checkbox will check/uncheck it. No suport for shiftKey.
	 * @param event
	 */
	function checkboxIndexLineFunction(event){
		if($(event.currentTarget)[0].nodeName == "TR" && !event.originalEvent.shiftKey && event.target.nodeName != 'A'){
			//line clicked, toggle it's checkbox (don't support shift click, as it is for text selection
			$(event.currentTarget).find("td.checkbox:first input[type=checkbox]").trigger("click", [ event ]);
			event.stopPropagation();
		}
	}
	
	/**
	 * Hides the confirm msgs div, but keeps it in the display to avoid having page content moving. 
	 */
	function dataSavedFadeout(){
		$("ul.confirm").animate({opacity: 0}, 700);
	}
	
	function setCsvPopup(target){
		if($("#csvPopup").length == 0){
			buildDialog('csvPopup', 'CSV', "<div class='loading'>--- " + STR_LOADING + " ---</div>", null);
			$.get(root_url + 'Datamart/Csv/csv/popup:/', function(data){
				var visible = $("#csvPopup:visible").length == 1;
				$("#csvPopup:visible").popup('close');
				$("#csvPopup h4 + div").html(data);
				
				$("#csvPopup form").attr("action", root_url + target);
				$("#csvPopup a.submit").click(function(){
					$("#csvPopup form input[type=hidden]").remove();
					$("form:first input[type=checkbox]:checked").each(function(){
						$("#csvPopup form").append('<input type="hidden" name="' + this.name + '" value="' + this.value + '"/>');
					});
					$(".databrowser .selectableNode.selected").each(function(){
						$("#csvPopup form").append('<input type="hidden" name="data[0][singleLineNodes][]" value="' + $(this).parent("a").data("nodeId") + '"/>');
					});
				});
				
				//option to select some browsing tree nodes
				if($(".databrowser").length == 1){
					//keep the single line option + bind command
					$("#csvPopup table:last").append("<tr><td colspan=3><span style='padding-bottom: 5px; display: inline-block;'>" + STR_NODE_SELECTION + "</span></td></tr>");
					var lastLine = $("#csvPopup table:last tr:last").hide();
					$(".databrowser").clone(false).appendTo($("#csvPopup table:last td:last"));
					
					var browserTable = $("#csvPopup .databrowser"); 
					browserTable.find("a.icon16.link").hide();//hide table + merge links
					browserTable.find("a > span.icon16").css("opacity",  0.05);
					browserTable.find("a").click(function(){ return false; }).css("cursor", "default");
					
					//put all nodes in an object
					var browsingNodes = new Object();
					browserTable.find("a").each(function(){
						var id = $(this).attr("href").match(/\/browse\/([\d]+)\/$/);
						if(id != null && "1" in id){
							browsingNodes[id[1]] = this;
							$(this).data("nodeId", id[1]);
						}
					});
					
					var nodeToggle = function(event){
						var span = $(event.currentTarget).find("span:first"); 
						var nodeId = $(event.currentTarget).data("nodeId");
						span.toggleClass("selected");
						if(span.hasClass("selected")){
							//disable other similar structure id
							var structureId = null;
							if(nodeId in csvMergeData.flat_children){
								structureId = csvMergeData.flat_children[nodeId].BrowsingResult.browsing_structures_id;
							}else{
								structureId = csvMergeData.parents[nodeId].BrowsingResult.browsing_structures_id;
							}
							for(i in csvMergeData.parents){
								if(i != nodeId && csvMergeData.parents[i].BrowsingResult.browsing_structures_id == structureId){
									if($(browsingNodes[i]).find("span:first").hasClass("selected")){
										$(browsingNodes[i]).click();
									}
								}
							}
							
							for(i in csvMergeData.flat_children){
								if(i != nodeId && csvMergeData.flat_children[i].BrowsingResult.browsing_structures_id == structureId){
									if($(browsingNodes[i]).find("span:first").hasClass("selected")){
										$(browsingNodes[i]).click();
									}
								}
							}
						}else{
							if(nodeId in csvMergeData.parents){
								//disable all parents
								var parentId = csvMergeData.parents[nodeId].BrowsingResult.parent_id; 
								if(parentId in csvMergeData.parents && $(browsingNodes[parentId]).find("span:first").hasClass("selected")){
									$(browsingNodes[parentId]).click();
								}
							}else{
								//disable all children
								for(i in csvMergeData.flat_children){
									if(csvMergeData.flat_children[i].BrowsingResult.parent_id == nodeId && $(browsingNodes[i]).find("span:first").hasClass("selected")){
										$(browsingNodes[i]).click();
									}
								}
							}
						}
						
					};
					
					//for all "csv mergeable nodes", raise opacity
					csvMergeData = $.parseJSON(csvMergeData);
					for(i in csvMergeData.parents){
						var node = browsingNodes[i];
						$(node).find("span.icon16").css("opacity", "").addClass("selectableNode").parent("a").css("cursor", "").click(nodeToggle);
					}
					for(i in csvMergeData.flat_children){
						var node = browsingNodes[i];
						$(node).find("span.icon16").css("opacity", "").addClass("selectableNode").parent("a").css("cursor", "").click(nodeToggle);
					}
					
					//current not is opaque
					var node = browsingNodes[csvMergeData.current_id];
					$(node).find("span.icon16").css("opacity", 1);
					
					$("select[name=data\\[0\\]\\[redundancy\\]]").change(function(){
						if($(this).val() == "same"){
							lastLine.show();
						}else{
							lastLine.hide();
						}
						$("#csvPopup").popup('close');
						$("#csvPopup").popup();
					});
					
				}else{
					//remove single line option
					$("select[name=data\\[0\\]\\[redundancy\\]]").parents("tr:first").remove();
				}
				
				if(visible){
					$("#csvPopup").popup();
				}
			});
		}
		$("#csvPopup").popup();
		$("input.submit").siblings("a").find("span").removeClass('fetching');
	}
	
	function selectedItemZonePopup(event){
		var button = $(event.currentTarget);
		var popup = null;
		if(!(popup = button.data('popup'))){
			popup = $("#default_popup").clone().attr("id", null);
			popup.appendTo("body");
			button.data('popup', popup);
			popup.html("<div class='wrapper'><div class='frame'>");
			popup.frame = popup.find(".frame");
			popup.click(function(){
				popup.popup('center');
			});
		}
		popup.frame.html("<div class='loading'>---" + STR_LOADING + "---</div>");
		popup.popup();
		
		var fctLinksToAjax = function(scope){
			$(scope).find("a:not(.detail)").click(function(){
				if($(this).attr("href").indexOf("javascript:") == 0){
					return true;
				}
				popup.frame.html("<div class='loading'>---" + STR_LOADING + "---</div>");
				popup.popup('center');
				$.get($(this).attr("href"), function(data){
					if(data.indexOf("{") == 0){
						data = $.parseJSON(data);
						popup.frame.html(data.page);
					}else{
						popup.frame.html(data);					
					}
					popup.popup('center');
					fctLinksToAjax(popup.frame);
				});
				
				return false;
			});
			
			$(scope).find("a.detail").click(function(){
				//selection
				popup.popup('close');
				var targetDiv = button.parents(".selectItemZone:first").find(".selectedItem");
				targetDiv.html("<div class='loading'>---" + STR_LOADING + "---</div>");
				var targetHref = $(this).attr("href"); 
				$.get(targetHref + '/type:index/noActions:/noHeader:/', function(data){
					if(data.indexOf("{") == 0){
						data = $.parseJSON(data);
						targetDiv.html(data.page);
					}else{
						targetDiv.html(data);					
					}
					targetDiv.append('<input type="hidden" name="' + button.data('name') + '" value="' + targetHref + '"/>');
				});
				
				return false;
			});
		};
		
		$.get(root_url + button.data('url') + '/noActions:', function(data){
			popup.frame.html(data);
			initAdvancedControls(popup);
			initDatepicker(popup);
			popup.popup('center');
			
			popup.find("form").submit(function(){
				submitChecks(this);
				$.post(popup.find("form").attr("action") + '/forSelection:/', popup.find("form").serialize(), function(data){
					if(data.indexOf("{") == 0){
						data = $.parseJSON(data);
						popup.frame.html(data.page);
					}else{
						popup.frame.html(data);					
					}
					popup.popup('center');
					fctLinksToAjax(popup.frame);
				});
				return false;
			});
		});
		
		
		return false;
	}
	
	function submitChecks(scope){
		$(scope).find('a.submit span').last().addClass('fetching');
		submitData.lastRequest = $.cookie('last_request');
	}
	
	function initIndexZones(useCache){
		var fctLinksToAjax = function(scope){
			$(scope).find("a:not(.icon16)").click(function(){
				if($(this).attr("href").indexOf("javascript:") == 0){
					return true;
				}
				scope.html("<div class='loading'>---" + STR_LOADING + "---</div>");
				$.get($(this).attr("href"), function(data){
					var page = null;
					if(data.indexOf("{") == 0){
						data = $.parseJSON(data);
						page = data.page;
					}else{
						page = data;
					}
					scope.html(page);					
					fctLinksToAjax(scope);
					
					if(history.state){
						history.state.indexZone[scope.data("url")] = page;
						history.replaceState(history.state, "foo");
					}
				});
				
				return false;
			});
		};
		
		if(history.state && !history.state.indexZone){
			history.state.indexZone = new Object();
		}
		
		$(".indexZone").each(function(){
			var indexZone = $(this);
			var url = indexZone.data('url');
			if(useCache && history.state.indexZone[url]){
				indexZone.html(history.state.indexZone[url]);
				fctLinksToAjax(indexZone);
			}else{
				indexZone.html("<div class='loading'>---" + STR_LOADING + "---</div>");
				var successFct = function(data){
					var page = null;
					if(data.indexOf("{") == 0){
						data = $.parseJSON(data);
						page = data.page;
						indexZone.html(data.page);
					}else{
						page = data;
					}
					indexZone.html(page);
					fctLinksToAjax(indexZone);
					
					history.state.indexZone[url] = page;
					history.replaceState(history.state, "foo");
				};
				var errorFct = function(jqXHR, textStatus, errorThrown){
					indexZone.html(errorThrown);
				};
				
				$.ajax({
					type	: "GET",
					url		: root_url + url + "/noActions:/",
					cache	: false,
					success	: successFct,
					error	: errorFct
				});
			}
		});
	}
	
	/**
	 * Will popup a confirmation message if defined. If the message is opened
	 * or if no message is defined, will submit and replace the disk icon with
	 * a rotating beam. Submiting is blocked when the rotating icon is present.
	 * @returns {Boolean}
	 */
	function standardSubmit(){
		//submitting form
		var submitButton = $(this).find("a.submit");
		var form = $(this);
		if(!$(submitButton).find('span').hasClass('fetching')){
			if($(submitButton).data('confirmation-msg') && (!$(form).data('confirmation-popup') || $(form).data('confirmation-popup').find(":visible:first").length == 0)){
				if($(form).data('confirmation-popup')){
					$(form).data('confirmation-popup').popup();
				}else{
					//function buildConfirmDialog(id, question, buttons){
					var yes_action = function(){
						form.submit();
						form.data('confirmation-popup').popup('close');
						return false;
					};
					var no_action = function(){
						form.data('confirmation-popup').popup('close');
						return false;
					};
					buildConfirmDialog('tmp', $(submitButton).siblings("span.confirmationMsg").html(), [{label : STR_YES, action: yes_action, icon: "detail"}, {label : STR_NO, action: no_action, icon: "delete noPrompt"}]);
					var popup = $("#tmp").attr("id", null);
					$(form).data('confirmation-popup', popup);
					popup.popup();
				}
			}else{
				submitChecks(this);
				submitData.callBack = setTimeout(fetchingBeamCheck, 1000);//check every second (needed for CSV download)
				return true;
			}
		}
		return false;
	}
		
	function clickSubmitButton(){
		if($(this).attr("href").indexOf("javascript:") == 0){
			return true;
		}
		
		$(this).siblings("input.submit").click(); 
		return false;
	}
	
	function miscIdPopup(participant_id, ctrl_id){
		buildConfirmDialog('miscIdPopup', STR_MISC_IDENTIFIER_REUSE, new Array(
			{label : STR_NEW, action: function(){document.location = root_url + "ClinicalAnnotation/MiscIdentifiers/add/" + participant_id + "/" + ctrl_id + "/"; return false;}, icon: "add"}, 
			{label : STR_REUSE, action: function(){document.location = root_url + "ClinicalAnnotation/MiscIdentifiers/reuse/" + participant_id + "/" + ctrl_id + "/"; return false;}, icon: "redo"})
		);
		$("#miscIdPopup").popup();
	}
	
	function dataBrowserHelp(){
		var diagram_url =	root_url + 'app/webroot/img/dataBrowser/datamart_structures_relationships_' + STR_LANGUAGE + '.png';
		$("#default_popup").html('<form enctype="multipart/form-data"><div class="descriptive_heading"><h4>' + STR_DATAMART_STRUCTURE_RELATIONSHIPS + '</h4><p></p></div><div style="padding: 10px; background-color: #fff;"><img src="' + diagram_url + '"/></div></form>').popup();
	}
