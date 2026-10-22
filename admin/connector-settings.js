( function ( window, document ) {
	'use strict';

	var config = window.zctzXAiConnector || null;

	if ( ! config ) {
		return;
	}

	var panelId = 'zactonz-ai-connector-for-xai-grok-connector-panel';
	var strings = config.strings || {};
	var block = 'zctz-xai-connector';

	function text( key, fallback ) {
		return strings[ key ] || fallback || '';
	}

	function element( tag, className, content ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}
		if ( content ) {
			node.textContent = content;
		}

		return node;
	}

	function findProviderCard() {
		var labels = document.querySelectorAll( 'h1, h2, h3, h4, strong' );
		var index;
		var node;

		for ( index = 0; index < labels.length; index++ ) {
			if ( config.providerName !== labels[ index ].textContent.trim() ) {
				continue;
			}

			if ( labels[ index ].closest( '.components-card' ) ) {
				return labels[ index ].closest( '.components-card' );
			}

			node = labels[ index ].parentElement;
			while ( node && node !== document.body ) {
				if ( node.textContent.indexOf( config.providerName ) !== -1 && node.querySelector( 'button, a' ) ) {
					return node;
				}
				node = node.parentElement;
			}
		}

		return null;
	}

	function labelledInput( label, input, description ) {
		var wrapper = element( 'div', block + '__field' );

		wrapper.appendChild( element( 'span', block + '__label', label ) );
		wrapper.appendChild( input );

		if ( description ) {
			wrapper.appendChild( element( 'span', block + '__help', description ) );
		}

		return wrapper;
	}

	function textInput( type, placeholder ) {
		var input = document.createElement( 'input' );

		input.type = type;
		input.className = block + '__input';
		input.autocomplete = 'off';
		input.spellcheck = false;

		if ( placeholder ) {
			input.placeholder = placeholder;
		}

		return input;
	}

	function selectInput( options, value ) {
		var select = document.createElement( 'select' );

		select.className = block + '__input';

		Object.keys( options ).forEach( function ( key ) {
			var option = document.createElement( 'option' );
			option.value = key;
			option.textContent = options[ key ];
			if ( key === value ) {
				option.selected = true;
			}
			select.appendChild( option );
		} );

		return select;
	}

	function buildPanel() {
		var panel = element( 'div', block + '__panel' );
		var inputs = {};

		panel.id = panelId;
		panel.appendChild( element( 'h3', block + '__title', text( 'heading' ) ) );

		var apiKey = textInput( 'password', text( 'savedValue' ) );
		inputs.api_key = apiKey;
		panel.appendChild( labelledInput( text( 'apiKey' ), apiKey, '' ) );

		( config.fields || [] ).forEach( function ( field ) {
			var input;

			if ( 'select' === field.type ) {
				input = selectInput( field.options || {}, field.value );
			} else {
				input = textInput( 'password' === field.type ? 'password' : 'text', field.placeholder || ( 'password' === field.type && field.hasValue ? text( 'savedValue' ) : '' ) );
				input.value = 'password' === field.type ? '' : ( field.value || '' );
			}

			inputs[ field.key ] = input;
			panel.appendChild( labelledInput( field.label, input, field.description ) );
		} );

		var actions = element( 'div', block + '__actions' );
		var save = element( 'button', 'button button-primary', text( 'save' ) );
		var status = element( 'span', block + '__status' );
		var more = document.createElement( 'a' );

		save.type = 'button';
		more.href = config.settingsUrl;
		more.className = block + '__link';
		more.textContent = text( 'moreSettings' );

		actions.appendChild( save );
		actions.appendChild( more );
		actions.appendChild( status );
		panel.appendChild( actions );

		save.addEventListener( 'click', function () {
			var body = new window.URLSearchParams();

			body.append( 'action', config.action );
			body.append( '_wpnonce', config.nonce );

			Object.keys( inputs ).forEach( function ( key ) {
				body.append( key, inputs[ key ].value );
			} );

			save.disabled = true;
			status.textContent = text( 'saving' );

			window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( payload ) {
				if ( ! payload || true !== payload.success ) {
					throw new Error( text( 'unexpected' ) );
				}

				status.textContent = payload.data.message || text( 'saved' );
				status.className = block + '__status ' + block + '__status--' + ( payload.data.connected ? 'ok' : 'error' );
			} ).catch( function ( error ) {
				status.textContent = error.message;
				status.className = block + '__status ' + block + '__status--error';
			} ).then( function () {
				save.disabled = false;
			} );
		} );

		return panel;
	}

	function mountPanel() {
		if ( document.getElementById( panelId ) ) {
			return true;
		}

		var card = findProviderCard();

		if ( ! card || ! card.parentNode ) {
			return false;
		}

		card.parentNode.insertBefore( buildPanel(), card.nextSibling );

		return true;
	}

	if ( ! mountPanel() ) {
		var observer = new window.MutationObserver( function () {
			if ( mountPanel() ) {
				observer.disconnect();
			}
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}
} )( window, document );
