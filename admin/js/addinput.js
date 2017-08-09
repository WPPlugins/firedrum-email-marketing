function fdAddInput(elm, type) {
	if (elm.getElementsByTagName('input').length > 0)
		return;

	var value = elm.innerHTML;
	elm.innerHTML = '';

	var input = document.createElement('input');
	input.setAttribute('type', 'text');
	input.setAttribute('value', value);
	input.setAttribute('name', 'new_' + type);
	input.setAttribute('id', 'new_' + type);

	elm.appendChild(input);

	input.focus();
}

(function($) {
	function fdToggleCustomStyle() {
		if (document.getElementById("fd_form_styling").checked == true) {
			document.getElementById("fd_form_background_color")
					.removeAttribute("disabled");
			document.getElementById("fd_form_text_color").removeAttribute(
					"disabled");
			document.getElementById("fd_form_text_size").removeAttribute(
					"disabled");
			document.getElementById("fd_form_text_font").removeAttribute(
					"disabled");
		} else {
			document.getElementById("fd_form_background_color").setAttribute(
					"disabled", "disabled");
			document.getElementById("fd_form_text_color").setAttribute(
					"disabled", "disabled");
			document.getElementById("fd_form_text_size").setAttribute(
					"disabled", "disabled");
			document.getElementById("fd_form_text_font").setAttribute(
					"disabled", "disabled");
		}
	}

	function fdToggleBorder() {
		if (document.getElementById("fd_form_border").checked == true) {
			document.getElementById("fd_form_border_color").removeAttribute(
					"disabled");
		} else {
			document.getElementById("fd_form_border_color").setAttribute(
					"disabled", "disabled");
		}
	}

	$(document).ready(function() {
		fdToggleCustomStyle();
		fdToggleBorder();
	});
})( jQuery );