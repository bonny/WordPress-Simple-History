import {
	startOfMonth,
	endOfMonth,
	startOfWeek,
	endOfWeek,
	startOfDay,
	endOfDay,
	format,
	parseISO,
} from 'date-fns';

export const LOG_LEVEL_COLORS = {
	emergency: '#dc2626',
	alert: '#dc2626',
	critical: '#dc2626',
	error: '#ef4444',
	warning: '#f97316',
	notice: '#3b82f6',
	info: '#6b7280',
	debug: '#9ca3af',
};

export function getPeriodRange( date, calView ) {
	if ( calView === 'month' ) {
		return { start: startOfMonth( date ), end: endOfMonth( date ) };
	}

	if ( calView === 'week' ) {
		return { start: startOfWeek( date ), end: endOfWeek( date ) };
	}

	return { start: startOfDay( date ), end: endOfDay( date ) };
}

export function groupEventsByDay( events ) {
	return events.reduce( ( acc, event ) => {
		const day = format( parseISO( event.date_local ), 'yyyy-MM-dd' );

		if ( ! acc[ day ] ) {
			acc[ day ] = [];
		}

		acc[ day ].push( event );

		return acc;
	}, {} );
}

export function getSeverityCounts( events ) {
	const counts = { error: 0, warning: 0, info: 0 };

	events.forEach( ( event ) => {
		const level = event.loglevel;

		if ( [ 'emergency', 'alert', 'critical', 'error' ].includes( level ) ) {
			counts.error++;
		} else if ( [ 'warning', 'notice' ].includes( level ) ) {
			counts.warning++;
		} else {
			counts.info++;
		}
	} );

	return counts;
}
