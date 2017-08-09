(function($) {
	$(document).ready(function($) {
		// Attach our form submitter action
		$( '.fd_signup_form' ).on( 'submit', fd_beforeForm );

		if ( typeof firedrum.reCaptchaSiteKey !== 'undefined' ) {
			var signupForms = [];

			$( '.fd_signup_form' ).each(function(index, form) {
				var $form = $( this );
				var reCaptchaInfo = {
					reCaptchaID: null,
					reCaptchaContainer: document.createElement( "div" ),
					form: $form
				};
				form.appendChild( reCaptchaInfo.reCaptchaContainer );
				window['fdReCaptchaCallback_'+index] = function(token) {
					fd_ajaxSubmit( $form );
					grecaptcha.reset( $form.data( 'recaptcha-id' ) );
					$( 'iframe[title="recaptcha widget"]', signupForms[index].reCaptchaContainer ).on( 'load', function() {
						var challengeParent = $( 'iframe[title="recaptcha challenge"]:first-child' ).parent();
						challengeParent.prepend( '<div class="fd-instructions"><table><tbody><tr><td class="invisible-text"><span>protected by <strong>reCAPTCHA</strong></span><div class="anchor-pt"><a href="https://www.google.com/intl/en/policies/privacy/" target="_blank">Privacy</a><span aria-hidden="true" role="presentation"> - </span><a href="https://www.google.com/intl/en/policies/terms/" target="_blank">Terms</a></div></td><td class="normal-footer"><div class="logo-large" role="presentation"><div class="logo-img logo-img-large"></div></div></td></tr></tbody></table></div>' );
						challengeParent.parent().attr( 'class', 'fd-captcha-challenge' );
					} );
				}
				signupForms.push( reCaptchaInfo );
			} );

			if ( signupForms.length > 0 ) {
				window.fdReCaptchaOnLoad = function(index, form) {
					for ( var i = 0; i < signupForms.length; i++ ) {
						signupForms[i].form.data( 'recaptcha-id', grecaptcha.render( signupForms[i].reCaptchaContainer, {
							sitekey: firedrum.reCaptchaSiteKey,
							size: 'invisible',
							callback: 'fdReCaptchaCallback_' + i
						} ) );
						$( 'iframe[title="recaptcha widget"]', signupForms[i].reCaptchaContainer ).on( 'load', function() {
							var challengeParent = $( 'iframe[title="recaptcha challenge"]:first-child' ).parent();
							challengeParent.prepend( '<div class="fd-instructions"><table><tbody><tr><td class="invisible-text"><span>protected by <strong>reCAPTCHA</strong></span><div class="anchor-pt"><a href="https://www.google.com/intl/en/policies/privacy/" target="_blank">Privacy</a><span aria-hidden="true" role="presentation"> - </span><a href="https://www.google.com/intl/en/policies/terms/" target="_blank">Terms</a></div></td><td class="normal-footer"><div class="logo-large" role="presentation"><div class="logo-img logo-img-large"></div></div></td></tr></tbody></table></div>' );
							challengeParent.parent().attr( 'class', 'fd-captcha-challenge' );
						} );
					}
				};

				var reCaptcha = document.createElement( 'script' );
				reCaptcha.async = 'async';
				reCaptcha.defer = 'defer';
				reCaptcha.src = 'https://www.google.com/recaptcha/api.js?onload=fdReCaptchaOnLoad&render=explicit';
				document.head.appendChild( reCaptcha );
			}
		}
	});

	function fd_beforeForm(evt) {
		// Disable the submit button
		$('.fd_signup_submit', this).attr("disabled", "disabled");

		if ( typeof firedrum.reCaptchaSiteKey !== 'undefined' ) {
			grecaptcha.execute( $( this ).data( 'recaptcha-id' ) );
		} else {
			fd_ajaxSubmit( $( this ) );
		}

		evt.preventDefault();
		return false;
	}
	
	function fd_ajaxSubmit( $form ) {
		$form.ajaxSubmit( {
			url : firedrum.ajax_url,
			type : 'POST',
			dataType : 'text',
			success : fd_success
		} );
	}

	function fd_success(data, status, xhr, $form) {
		data = JSON.parse(data);
		if ( typeof $form === 'undefined' ) {
			// Using jQuery < 1.4
			$form = xhr;
		}
		// Re-enable the submit button
		$('.fd_signup_submit', $form).removeAttr("disabled");

		// Put the response in the message div
		$('.fd_message', $form).html( data.message );

		// See if we're successful, if so, wipe the fields
		if ( data.success ) {
			$form[0].reset();
		}

		$.scrollTo($form.parent(), {
			offset : {
				top : -28
			}
		});
	}
})(jQuery);