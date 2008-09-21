/****************************************************
 * Javascript functioons for the pp_forum extension
 * @see http://typo3.org
 *
 * @author: popy
 *
 */

var tx_ppforum = {
	resizerOptions: {
		cornerStyle: {
			height: '13px',
			width: '13px',
			backgroundImage: '',
			position: 'absolute',
			bottom: '0',
			right: '0',
			cursor: 'nw-resize'
		}
	},

	/**
	 * Initialize function (onload event handler)
	 *
	 * @param event event = load event
	 * @access public
	 * @return void
	 */
	initialize: function(event){
		document.getElementsByClassName('tx-ppforum-pi1').each(function(forum){
			forum = $(forum);
			$A(forum.getElementsByTagName('form')).each(function(item){
				tx_ppforum.initializeForm(item);
				tx_ppforum.disableAutoComplete(item);
			});
			$A(forum.getElementsByTagName('textarea')).each(function(item){
				new Belink.Resizable(item, tx_ppforum.resizerOptions);
			});
		});		
	},

	initializeForm: function(form){
		form = $(form);
		var regexp = /^tx_ppforum_pi1\[[^\]]*\]\[([^\]]*)\]$/;

		if(!form.elementsShortNames){
			form.elementsShortNames = {};

			$A(form.elements).each(function(field){
				if(match = regexp.exec(field.name)){
					form.elementsShortNames[match[1]] = $(field);
				}
			});
		}
	},

	/**
	 * "Open" a tool : it means the <div> tag containing a tool (identified by a class) will be displayed and the others hidden
	 *
	 * @param node caller = the button node
	 * @param string toolClass = the class wich identify the div
	 * @access public
	 * @return bool (always false) 
	 */
	showhideTool: function(caller,toolClass){
		var temp = $(caller).up().next('div.hiddentools');

		temp.immediateDescendants().each(function(item) {
			if (item.hasClassName(toolClass)) {
				item.toggle();
			} else{
				item.hide();
			}
		});

		return false;
	},

	switchParserToolbar: function(ref, toolbarClass){
		var temp = $(ref.parentNode).next('div').down('div');

		while(temp){
			if (toolbarClass && temp.hasClassName(toolbarClass)){
				temp.show();
			} else {
				temp.hide();
			}

			temp = temp.next('div');
		}
	},

	getParentForm: function(obj){
		if(obj.nodeName != 'FORM') {
			return $(obj).up('form');
		} else{
			return $(obj);
		}
	},


	getFieldInForm: function(form, fieldname){
		form = tx_ppforum.getParentForm(form);

		if(form.elementsShortNames && form.elementsShortNames[fieldname]){
			return form.elementsShortNames[fieldname];
		} else{
			return false;
		}
	},

	wrapSelected: function(starttag,endtag,obj){
		var textarea = tx_ppforum.getFieldInForm(obj, 'message');

		var start = textarea.selectionStart;

		if(start == null){
			//IE, still IE...
			textarea.selRange.text = starttag+textarea.selRange.text+endtag;
		} else{
			var stop  = textarea.selectionEnd;
			var scTop = textarea.scrollTop;
			text = textarea.value;

			text = text.substring(0,stop)+endtag+text.substring(stop);
			text = text.substring(0,start)+starttag+text.substring(start);

			textarea.value = text;

			if (textarea.setSelectionRange) {
				textarea.setSelectionRange(start + starttag.length, stop + starttag.length);
			}
			textarea.scrollTop = scTop;
		}

		textarea.focus();

		return false;
	},

	/**
	 * Disable autocomplete functionnality on the given form's fields by adding an "autocomplete" attribute
	 *
	 * @param formNode formObj   = The form object
	 * @param array execeptList = field names (fields to skip)
	 * @access public
	 * @return string 
	 */
	disableAutoComplete: function(formObj, execeptList){
		for(i = 0; i < formObj.elements.length; i++) {
			if(!execeptList || execeptList.indexOf(formObj[i].name) == -1){
				formObj[i].setAttribute('autocomplete', 'off');
			}
		}
	}

};

Event.observe(window, 'load', tx_ppforum.initialize);

