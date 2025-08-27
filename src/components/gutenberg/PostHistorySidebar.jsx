import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { update } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';
import { EventsCompactList } from '../EventsCompactList';
import './PostHistorySidebar.scss';

// Custom hook for fetching post events on demand
const usePostEvents = ( postId ) => {
	const [ events, setEvents ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const loadEvents = async () => {
		if ( ! postId || postId === 0 ) {
			return;
		}

		setIsLoading( true );
		setError( null );

		try {
			const response = await apiFetch( {
				path: addQueryArgs( '/simple-history/v1/events', {
					per_page: 100,
					loggers: 'SimplePostLogger',
					context_filters: {
						post_id: postId.toString(),
					},
					ungrouped: true,
					_fields: [
						'id',
						'message',
						'initiator',
						'link',
						'date',
						'date_gmt',
						'date_local',
						'initiator_data.user_login',
						'initiator_data.user_profile_url',
						'initiator_data.user_email',
						'initiator_data.display_name',
						'initiator_data.user_display_name',
						'context.post_revision_id',
						//'context.post_id',
					],
				} ),
				parse: false,
			} );

			const eventsJson = await response.json();
			setEvents( eventsJson );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setIsLoading( false );
		}
	};

	return { events, isLoading, error, loadEvents };
};

// Main panel component
export const PostHistorySidebar = () => {
	// Get current post ID from WordPress data layer
	const postId = useSelect( ( select ) => {
		const { getCurrentPostId } = select( 'core/editor' );
		return getCurrentPostId();
	}, [] );

	// Use the manual loading hook
	const { events, isLoading, error, loadEvents } = usePostEvents( postId );

	const renderContent = () => {
		return (
			<div className="sh-GutenbergPanel-content">
				<Button
					variant="tertiary"
					onClick={ loadEvents }
					disabled={ isLoading || postId === 0 }
					className="sh-GutenbergPanel-loadBtn"
					icon={ isLoading ? <Spinner /> : update }
				>
					{ isLoading
						? __( 'Loading…', 'simple-history' )
						: __( 'Load edit history', 'simple-history' ) }
				</Button>

				{ error && (
					<Notice
						status="error"
						isDismissible={ false }
						className="sh-GutenbergPanel-notice"
					>
						{ __(
							'Unable to load history events.',
							'simple-history'
						) }
					</Notice>
				) }

				<EventsCompactList
					events={ events }
					isLoading={ isLoading }
					variant="sidebar"
					maxEvents={ 100 }
				/>

				{ postId === 0 && (
					<Notice
						status="info"
						isDismissible={ false }
						className="sh-GutenbergPanel-notice"
					>
						{ __(
							'Save this post to see its history.',
							'simple-history'
						) }
					</Notice>
				) }
			</div>
		);
	};

	return (
		<PluginDocumentSettingPanel
			name="simple-history-panel"
			title={ __( 'Post History', 'simple-history' ) }
		>
			<div className="sh-GutenbergPanel">{ renderContent() }</div>
		</PluginDocumentSettingPanel>
	);
};
