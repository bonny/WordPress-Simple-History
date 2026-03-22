/**
 * Compact insights bar displayed above filters on the main event log page.
 * Shows event counts, activity sparkline, and top users in a three-column layout.
 *
 * Experimental feature — only rendered when experimental features are enabled.
 */

import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { UserCard } from './UserCard';

/**
 * Format a date string (YYYY-MM-DD) as short month + day (e.g. "Feb 20").
 *
 * @param {string} dateStr Date in YYYY-MM-DD format.
 * @return {string} Formatted date.
 */
function formatShortDate( dateStr ) {
	if ( ! dateStr ) {
		return '';
	}

	const date = new Date( dateStr + 'T00:00:00' );
	return date.toLocaleDateString( undefined, {
		month: 'short',
		day: 'numeric',
	} );
}

/**
 * Format a number with locale-appropriate separators.
 *
 * @param {number} num
 * @return {string} Formatted number.
 */
function formatNumber( num ) {
	return Number( num ).toLocaleString();
}

/**
 * Bar sparkline — 30 vertical bars showing daily activity.
 * Hover shows date + count, click filters the log to that day.
 *
 * @param {Object} props
 * @param {Array}  props.data Array of {date: string, count: string|number}.
 */
function BarSparkline( { data } ) {
	if ( ! data || data.length === 0 ) {
		return null;
	}

	const max = Math.max( ...data.map( ( d ) => Number( d.count ) ), 1 );
	const today = new Date().toISOString().slice( 0, 10 );

	const handleBarClick = ( date ) => {
		window.dispatchEvent(
			new CustomEvent( 'SimpleHistory:chartDateClick', {
				detail: { date },
			} )
		);
	};

	return (
		<div
			className="sh-InsightsBar-bars"
			role="img"
			aria-label={ __( 'Daily activity over last 30 days', 'simple-history' ) }
		>
			{ data.map( ( d ) => {
				const count = Number( d.count );
				const heightPercent = ( count / max ) * 100;
				const isToday = d.date === today;

				return (
					<button
						key={ d.date }
						type="button"
						className={
							'sh-InsightsBar-bar' +
							( isToday ? ' sh-InsightsBar-bar--today' : '' )
						}
						style={ { height: `${ Math.max( heightPercent, 2 ) }%` } }
						onClick={ () => handleBarClick( d.date ) }
						title={ `${ formatShortDate( d.date ) }: ${ formatNumber( count ) } ${ __( 'events', 'simple-history' ) }` }
						aria-label={ `${ formatShortDate( d.date ) }: ${ formatNumber( count ) } ${ __( 'events', 'simple-history' ) }` }
					/>
				);
			} ) }
		</div>
	);
}

/**
 * Wraps a user name+avatar in a UserCard popover trigger.
 * Constructs a minimal event-like object that UserCard expects.
 *
 * @param {Object} props
 * @param {Object} props.user User object from the insights API.
 * @param {Object} props.children React children.
 */
function InsightsUserCard( { user, children } ) {
	const fakeEvent = useMemo(
		() => ( {
			initiator: 'wp_user',
			initiator_data: {
				user_id: Number( user.id ),
				user_display_name: user.display_name,
				user_email: user.user_email,
				user_avatar_url: user.avatar,
				user_login: user.display_name,
			},
		} ),
		[ user ]
	);

	return <UserCard event={ fakeEvent }>{ children }</UserCard>;
}

export default function EventsInsightsBar() {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		apiFetch( { path: '/simple-history/v1/stats/insights' } )
			.then( ( response ) => {
				setData( response );
				setIsLoading( false );
			} )
			.catch( () => {
				setIsLoading( false );
			} );
	}, [] );

	if ( isLoading ) {
		return (
			<div className="sh-InsightsBar sh-InsightsBar--loading">
				<div className="sh-InsightsBar-skeleton" />
			</div>
		);
	}

	if ( ! data ) {
		return null;
	}

	const {
		event_counts: counts,
		activity_by_date: chartData,
		top_users: topUsers,
		database: db,
		stats_page_url: statsPageUrl,
	} = data;

	// Get yesterday's count from chart data (second to last entry).
	const yesterdayData = chartData?.[ chartData.length - 2 ];
	const yesterdayCount = yesterdayData ? Number( yesterdayData.count ) : 0;

	return (
		<div className="sh-InsightsBar">
			{ /* Main row: counts + bars + users — all inline */ }
			<div className="sh-InsightsBar-row">
				<span className="sh-InsightsBar-stat">
					{ __( 'Events:', 'simple-history' ) }
				</span>
				<span className="sh-InsightsBar-stat">
					<strong>{ formatNumber( counts.today ) }</strong>{ ' ' }
					{ __( 'today', 'simple-history' ) }
				</span>
				<span className="sh-InsightsBar-sep">|</span>
				<span className="sh-InsightsBar-stat">
					<strong>{ formatNumber( yesterdayCount ) }</strong>{ ' ' }
					{ __( 'yesterday', 'simple-history' ) }
				</span>
				<span className="sh-InsightsBar-sep">|</span>
				<span className="sh-InsightsBar-stat">
					<strong>{ formatNumber( counts.week ) }</strong>{ ' ' }
					{ __( '7 days', 'simple-history' ) }
				</span>
				<span className="sh-InsightsBar-sep">|</span>
				<span className="sh-InsightsBar-stat">
					<strong>{ formatNumber( counts.month ) }</strong>{ ' ' }
					{ __( '30 days', 'simple-history' ) }
				</span>

				<BarSparkline data={ chartData } />
			</div>

			{ /* Users row */ }
			{ topUsers && topUsers.length > 0 && (
				<div className="sh-InsightsBar-usersRow">
					<span className="sh-InsightsBar-usersLabel">
						{ __( 'Most active', 'simple-history' ) }
					</span>
					{ topUsers.map( ( user, index ) => (
						<span
							key={ user.id }
							className="sh-InsightsBar-userInline"
						>
							<InsightsUserCard user={ user }>
								<img
									src={ user.avatar }
									alt=""
									className="sh-InsightsBar-userAvatar"
									width="16"
									height="16"
								/>
								{ user.display_name }
							</InsightsUserCard>
							<span className="sh-InsightsBar-userCount">
								&nbsp;({ formatNumber( user.count ) })
							</span>
							{ index < topUsers.length - 1 ? ',\u00A0 ' : '' }
						</span>
					) ) }
				</div>
			) }

			{ /* Footer */ }
			<div className="sh-InsightsBar-footer">
				<span className="sh-InsightsBar-footerInfo">
					{ formatNumber( db.events_in_db ) }{ ' ' }
					{ __( 'in database', 'simple-history' ) }
					{ ' · ' }
					{ __( 'Calculated from all events. Updates every 5 minutes.', 'simple-history' ) }
				</span>
				<a
					href={ statsPageUrl || 'admin.php?page=simple_history_admin_menu_page' }
					className="sh-InsightsBar-footerLink"
				>
					{ __( 'See all History Insights', 'simple-history' ) } →
				</a>
			</div>
		</div>
	);
}
