import { TextControl } from '@wordpress/components';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';

export const TextInput = compose(
	withDispatch( function( dispatch, props ) {
		return {
			setMetaValue: function( value ) {
				dispatch( 'core/editor' ).editPost( { meta: { [props.metaKey]: value } } );
			}
		}
	} ),
	withSelect( function( select, props ) {
		return {
			metaValue: select( 'core/editor' ).getEditedPostAttribute( 'meta' )[ props.metaKey ],
		}
	} )
)( function( props ) {

	return (
		<TextControl
			type="number"
			label={ props.label }
			value={ props.metaValue }
			onChange={ ( content ) => { props.setMetaValue( content ) } }
		/>
	);
} );

