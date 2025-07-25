/**
 * Gutenberg integration for Simple History
 *
 * Registers the post history sidebar panel for the block editor
 */

import { registerPlugin } from '@wordpress/plugins';
import { PostHistorySidebar } from './components/gutenberg/PostHistorySidebar';

// Register the post history sidebar plugin
registerPlugin( 'simple-history-post-sidebar', {
	render: PostHistorySidebar,
} );
