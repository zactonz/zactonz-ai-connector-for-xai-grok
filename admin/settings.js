( function ( window, document ) {
	'use strict';

	var config = window.zctzXAiSettings || null;

	if ( ! config ) {
		return;
	}

	var strings = config.strings || {};
	var models = [];

	function text( key, fallback ) {
		return strings[ key ] || fallback || '';
	}

	function request( url ) {
		return window.fetch( url, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( payload ) {
			if ( ! payload || true !== payload.success ) {
				throw new Error( payload && payload.data ? String( payload.data ) : text( 'modelsFailed' ) );
			}

			return payload.data;
		} );
	}

	function featureLabels( features ) {
		var labels = {
			text: text( 'featureText', 'Text' ),
			vision: text( 'featureVision', 'Vision' ),
			tools: text( 'featureTools', 'Tools' ),
			structured: text( 'featureStructured', 'JSON' ),
			reasoning: text( 'featureReasoning', 'Reasoning' ),
			embedding: text( 'featureEmbedding', 'Embeddings' ),
			image: text( 'featureImage', 'Images' )
		};

		return Object.keys( labels ).filter( function ( key ) {
			return features && features[ key ];
		} ).map( function ( key ) {
			return { key: key, label: labels[ key ] };
		} );
	}

	function formatContext( length ) {
		if ( ! length ) {
			return '';
		}

		if ( length >= 1000 ) {
			return Math.round( length / 1000 ) + 'K ' + text( 'context', 'context' );
		}

		return length + ' ' + text( 'context', 'context' );
	}

	function renderModelList( container ) {
		var list = document.createElement( 'ul' );
		list.className = 'zctz-xai-models-list';

		models.forEach( function ( model ) {
			var item = document.createElement( 'li' );
			item.className = 'zctz-xai-model-item';

			var name = document.createElement( 'code' );
			name.textContent = model.id;
			item.appendChild( name );

			var context = formatContext( model.contextLength );
			if ( context ) {
				var meta = document.createElement( 'span' );
				meta.className = 'zctz-xai-model-meta';
				meta.textContent = context;
				item.appendChild( meta );
			}

			var pills = document.createElement( 'span' );
			pills.className = 'zctz-xai-capabilities';
			featureLabels( model.features ).forEach( function ( feature ) {
				var pill = document.createElement( 'span' );
				pill.className = 'zctz-xai-pill zctz-xai-pill--' + feature.key;
				pill.textContent = feature.label;
				pills.appendChild( pill );
			} );
			item.appendChild( pills );

			list.appendChild( item );
		} );

		container.innerHTML = '';
		container.appendChild( list );
	}

	function matchesCapability( model, capability ) {
		var features = model.features || {};

		if ( 'tools' === capability ) {
			return !! features.tools;
		}
		if ( 'vision' === capability ) {
			return !! features.vision;
		}
		if ( 'image' === capability ) {
			return !! features.image;
		}
		if ( 'embedding' === capability ) {
			return !! features.embedding;
		}

		return !! features.text;
	}

	function populateSelects() {
		var selects = document.querySelectorAll( '.zctz-xai-model-select' );

		Array.prototype.forEach.call( selects, function ( select ) {
			var capability = select.getAttribute( 'data-capability' ) || 'text';
			var selected = select.getAttribute( 'data-selected' ) || '';
			var matching = models.filter( function ( model ) {
				return matchesCapability( model, capability );
			} );

			select.innerHTML = '';

			var automatic = document.createElement( 'option' );
			automatic.value = '';
			automatic.textContent = text( 'automatic', 'Automatic' );
			select.appendChild( automatic );

			matching.forEach( function ( model ) {
				var option = document.createElement( 'option' );
				option.value = model.id;
				option.textContent = model.id;
				if ( model.id === selected ) {
					option.selected = true;
				}
				select.appendChild( option );
			} );

			if ( selected && ! matching.some( function ( model ) {
				return model.id === selected;
			} ) ) {
				var stale = document.createElement( 'option' );
				stale.value = selected;
				stale.textContent = selected;
				stale.selected = true;
				select.appendChild( stale );
			}
		} );
	}

	function updateReasoningHint() {
		var hint = document.getElementById( 'zctz_xai_settings-reasoning-support' );
		var select = document.getElementById( 'zctz_xai_settings-model-text' );

		if ( ! hint || ! select ) {
			return;
		}

		if ( ! select.value ) {
			hint.textContent = text( 'reasoningPick' );
			return;
		}

		var model = models.filter( function ( candidate ) {
			return candidate.id === select.value;
		} )[ 0 ];

		if ( ! model ) {
			hint.textContent = '';
			return;
		}

		hint.textContent = model.features && model.features.reasoning
			? text( 'reasoningYes' )
			: text( 'reasoningNo' );
	}

	function loadModels() {
		var container = document.getElementById( 'zctz-xai-models-container' );
		var status = document.getElementById( 'zctz-xai-model-status' );

		if ( ! container ) {
			return;
		}

		if ( status ) {
			status.textContent = text( 'loadingModels' );
		}

		request( config.modelsUrl ).then( function ( payload ) {
			models = Array.isArray( payload ) ? payload : [];

			if ( ! models.length ) {
				container.textContent = text( 'noModels' );
				return;
			}

			renderModelList( container );
			populateSelects();
			updateReasoningHint();
		} ).catch( function ( error ) {
			container.textContent = text( 'modelsFailed' ) + ' ' + error.message;
		} );
	}

	function renderDiagnostics( report ) {
		var results = document.getElementById( 'zctz-xai-diagnostics-results' );

		if ( ! results ) {
			return;
		}

		var rows = [
			[ text( 'endpoint' ), report.endpoint ],
			[ text( 'latency' ), report.latencyMs + ' ms' ],
			[ text( 'modelCount' ), String( report.modelCount ) ],
			[ text( 'aiClient' ), report.aiClientVersion || '-' ]
		];

		var missing = Object.keys( report.missingDefaults || {} );
		if ( missing.length ) {
			rows.push( [ text( 'missingDefaults' ), missing.map( function ( key ) {
				return key + ': ' + report.missingDefaults[ key ];
			} ).join( ', ' ) ] );
		}

		var table = document.createElement( 'table' );
		rows.forEach( function ( row ) {
			var tr = document.createElement( 'tr' );
			var th = document.createElement( 'th' );
			var td = document.createElement( 'td' );
			th.textContent = row[ 0 ];
			td.textContent = row[ 1 ];
			tr.appendChild( th );
			tr.appendChild( td );
			table.appendChild( tr );
		} );

		var heading = document.createElement( 'p' );
		heading.className = 'zctz-xai-diagnostics-heading';
		heading.textContent = report.connected ? text( 'connected' ) : text( 'notConnected' );

		results.className = 'notice ' + ( report.connected ? 'notice-success' : 'notice-error' );
		results.innerHTML = '';
		results.appendChild( heading );

		if ( report.error ) {
			var error = document.createElement( 'p' );
			error.textContent = report.error;
			results.appendChild( error );
		}

		results.appendChild( table );
	}

	function bindDiagnostics() {
		var button = document.getElementById( 'zctz-xai-run-diagnostics' );
		var spinner = document.getElementById( 'zctz-xai-diagnostics-spinner' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			if ( spinner ) {
				spinner.classList.add( 'is-active' );
			}

			request( config.diagnosticsUrl ).then( function ( report ) {
				renderDiagnostics( report );
			} ).catch( function ( error ) {
				var results = document.getElementById( 'zctz-xai-diagnostics-results' );
				if ( results ) {
					results.className = 'notice notice-error';
					results.textContent = error.message;
				}
			} ).then( function () {
				button.disabled = false;
				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
			} );
		} );
	}

	function bindReasoning() {
		var select = document.getElementById( 'zctz_xai_settings-model-text' );

		if ( select ) {
			select.addEventListener( 'change', updateReasoningHint );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		loadModels();
		bindDiagnostics();
		bindReasoning();
	} );
} )( window, document );
