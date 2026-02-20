/**
 * Registers a "Simple History for this post" command in the WordPress command palette.
 *
 * This command is available in the block editor (post/page editing screens)
 * and navigates to the Simple History event log filtered by the current post ID.
 *
 * @since 5.24.0
 */
import { useCommand } from '@wordpress/commands';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { backup } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Component that registers the command palette command.
 * Returns null since it doesn't render anything.
 */
function SimpleHistoryCommandPalette() {
	const { postId, postTitle, postType } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			postId: editor.getCurrentPostId(),
			postTitle: editor.getEditedPostAttribute( 'title' ),
			postType: editor.getCurrentPostType(),
		};
	}, [] );

	// Build the URL to Simple History filtered by context _post_id.
	// The context filter format is a newline-separated "key:value" string.
	const historyUrl = window.simpleHistoryCommandPalette?.historyPageUrl ?? '';

	// Strip double quotes from post title to avoid breaking cmdk's
	// querySelector which uses the label as a CSS attribute selector value.
	const safeTitle = (
		postTitle ||
		postType ||
		__( 'this post', 'simple-history' )
	).replace( /"/g, '' );

	useCommand( {
		name: 'simple-history/view-post-history',
		label: sprintf(
			// translators: %s: post title or post type label.
			__( 'Simple History for "%s"', 'simple-history' ),
			safeTitle
		),
		icon: backup,
		callback: ( { close } ) => {
			if ( historyUrl && postId ) {
				const contextFilter = encodeURIComponent(
					`_post_id:${ postId }`
				);
				document.location.href = `${ historyUrl }&context=${ contextFilter }`;
			}
			close();
		},
	} );

	return null;
}

registerPlugin( 'simple-history-command-palette', {
	render: SimpleHistoryCommandPalette,
} );
