/**
 * WP Social Publisher — Gutenberg sidebar.
 * ES5-compatible IIFE. Uses wp.* globals — no build step required.
 */
(function () {
	'use strict';

	var registerPlugin        = wp.plugins.registerPlugin;
	var PluginSidebar         = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var PanelBody             = wp.components.PanelBody;
	var CheckboxControl       = wp.components.CheckboxControl;
	var TextareaControl       = wp.components.TextareaControl;
	var Notice                = wp.components.Notice;
	var useSelect             = wp.data.useSelect;
	var useDispatch           = wp.data.useDispatch;
	var useState              = wp.element.useState;
	var Fragment              = wp.element.Fragment;
	var createElement         = wp.element.createElement;
	var __                    = wp.i18n.__;

	var PLATFORMS = [
		{ slug: 'facebook',  label: 'Facebook',    color: '#1877F2', limit: 63206 },
		{ slug: 'instagram', label: 'Instagram',   color: '#E1306C', limit: 2200  },
		{ slug: 'linkedin',  label: 'LinkedIn',    color: '#0A66C2', limit: 3000  },
		{ slug: 'twitter',   label: 'X (Twitter)', color: '#000000', limit: 280   }
	];

	function WspSidebar() {
		var editPost     = useDispatch( 'core/editor' ).editPost;
		var meta         = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		} );
		var alreadySent  = ( typeof wspData !== 'undefined' && wspData.alreadySent ) ? wspData.alreadySent : [];

		var channels = Array.isArray( meta._wsp_channels ) ? meta._wsp_channels : [];
		var captions = ( meta._wsp_captions && typeof meta._wsp_captions === 'object' ) ? meta._wsp_captions : {};

		function toggleChannel( slug, checked ) {
			var next;
			if ( checked ) {
				next = channels.indexOf( slug ) === -1 ? channels.concat( [ slug ] ) : channels;
			} else {
				next = channels.filter( function ( c ) { return c !== slug; } );
			}
			editPost( { meta: Object.assign( {}, meta, { _wsp_channels: next } ) } );
		}

		function setCaption( slug, value ) {
			var nextCaptions = Object.assign( {}, captions );
			nextCaptions[ slug ] = value;
			editPost( { meta: Object.assign( {}, meta, { _wsp_captions: nextCaptions } ) } );
		}

		var rows = PLATFORMS.map( function ( platform ) {
			var isChecked   = channels.indexOf( platform.slug ) !== -1;
			var captionVal  = captions[ platform.slug ] || '';
			var charCount   = captionVal.length;
			var overLimit   = charCount > platform.limit;
			var wasSent     = alreadySent.indexOf( platform.slug ) !== -1;

			return createElement(
				'div',
				{ key: platform.slug, className: 'smp-platform-row' },
				createElement( CheckboxControl, {
					label: createElement(
						'span',
						null,
						createElement( 'span', {
							className: 'smp-platform-dot',
							style: { background: platform.color }
						} ),
						platform.label
					),
					checked: isChecked,
					onChange: function ( val ) { toggleChannel( platform.slug, val ); }
				} ),
				wasSent && createElement( Notice, {
					status: 'info',
					isDismissible: false,
					className: 'smp-already-sent-notice'
				}, __( 'Already published to ' + platform.label, 'wp-social-publisher' ) ),
				isChecked && createElement(
					'div',
					{ className: 'smp-caption-wrap' },
					createElement( TextareaControl, {
						placeholder: __( 'Custom caption (optional)', 'wp-social-publisher' ),
						value: captionVal,
						onChange: function ( val ) { setCaption( platform.slug, val ); },
						rows: 3
					} ),
					createElement(
						'span',
						{ className: 'smp-char-count' + ( overLimit ? ' smp-over-limit' : '' ) },
						charCount + ' / ' + platform.limit
					)
				)
			);
		} );

		return createElement(
			Fragment,
			null,
			createElement( PluginSidebarMoreMenuItem, { target: 'wsp-social-sidebar' },
				__( 'Social Media', 'wp-social-publisher' )
			),
			createElement(
				PluginSidebar,
				{ name: 'wsp-social-sidebar', title: __( 'Social Media', 'wp-social-publisher' ), icon: 'share' },
				createElement( PanelBody, { title: __( 'Publish to Social Media', 'wp-social-publisher' ), initialOpen: true },
					rows
				)
			)
		);
	}

	registerPlugin( 'wsp-social-publisher', { render: WspSidebar } );
}());
