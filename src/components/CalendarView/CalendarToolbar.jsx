import { Button, ButtonGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const VIEWS = [
	{ key: 'month', label: __( 'Month', 'simple-history' ) },
	{ key: 'week', label: __( 'Week', 'simple-history' ) },
	{ key: 'day', label: __( 'Day', 'simple-history' ) },
];

// react-big-calendar injects: date, view, label, onNavigate, onView
export function CalendarToolbar( { view, label, onNavigate, onView } ) {
	return (
		<div className="sh-calendar-toolbar">
			<div className="sh-calendar-toolbar__nav">
				<Button
					variant="secondary"
					onClick={ () => onNavigate( 'PREV' ) }
					aria-label={ __( 'Previous', 'simple-history' ) }
				>
					‹
				</Button>
				<Button
					variant="secondary"
					onClick={ () => onNavigate( 'TODAY' ) }
				>
					{ __( 'Today', 'simple-history' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => onNavigate( 'NEXT' ) }
					aria-label={ __( 'Next', 'simple-history' ) }
				>
					›
				</Button>
				<span className="sh-calendar-toolbar__label">{ label }</span>
			</div>
			<ButtonGroup>
				{ VIEWS.map( ( { key, label: viewLabel } ) => (
					<Button
						key={ key }
						variant={ view === key ? 'primary' : 'secondary' }
						onClick={ () => onView( key ) }
					>
						{ viewLabel }
					</Button>
				) ) }
			</ButtonGroup>
		</div>
	);
}
