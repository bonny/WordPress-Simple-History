import { __, _x } from '@wordpress/i18n';
import { endOfDay, format, startOfDay } from 'date-fns';

export const DEFAULT_DATE_OPTIONS = [
	{
		label: __( 'Custom date range…', 'simple-history' ),
		value: 'customRange',
	},
	{
		label: __( 'Last day', 'simple-history' ),
		value: 'lastdays:1',
	},
	{
		label: __( 'Last 7 days', 'simple-history' ),
		value: 'lastdays:7',
	},
	{
		label: __( 'Last 14 days', 'simple-history' ),
		value: 'lastdays:14',
	},
	{
		label: __( 'Last 30 days', 'simple-history' ),
		value: 'lastdays:30',
	},
	{
		label: __( 'Last 60 days', 'simple-history' ),
		value: 'lastdays:60',
	},
];

export const OPTIONS_LOADING = [
	{
		label: __( 'Loading…', 'simple-history' ),
		value: '',
	},
];
export const LOGLEVELS_OPTIONS = [
	{
		label: _x( 'Info', 'Log level in gui', 'simple-history' ),
		value: 'info',
	},
	{
		label: _x( 'Warning', 'Log level in gui', 'simple-history' ),
		value: 'warning',
	},
	{
		label: _x( 'Error', 'Log level in gui', 'simple-history' ),
		value: 'error',
	},
	{
		label: _x( 'Critical', 'Log level in gui', 'simple-history' ),
		value: 'critical',
	},
	{
		label: _x( 'Alert', 'Log level in gui', 'simple-history' ),
		value: 'alert',
	},
	{
		label: _x( 'Emergency', 'Log level in gui', 'simple-history' ),
		value: 'emergency',
	},
	{
		label: _x( 'Debug', 'Log level in gui', 'simple-history' ),
		value: 'debug',
	},
];

// Date in format 2024-07-11T13:20:32, as used by WP DatePicker.
export const TIMEZONELESS_FORMAT = "yyyy-MM-dd'T'HH:mm:ss";

export const SUBITEM_PREFIX = ' - ';

export const SEARCH_FILTER_DEFAULT_START_DATE = format(
	startOfDay( new Date() ),
	TIMEZONELESS_FORMAT
);
export const SEARCH_FILTER_DEFAULT_END_DATE = format(
	endOfDay( new Date() ),
	TIMEZONELESS_FORMAT
);
