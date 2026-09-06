/* global Chart, simpleHistoryStats */
/**
 * JavaScript for History Insights page.
 * This is the core stats page for the plugin,
 * that only target the "demo" page, with blurred stats.
 */
jQuery( function () {
	'use strict';

	// Helper function to generate random integers.
	function getRandomInt( min, max ) {
		// Square the random number to bias towards lower values
		const skewedRandom = Math.random() * Math.random();
		return Math.floor( skewedRandom * ( max - min + 1 ) ) + min;
	}

	// Peak Activity Times Chart.
	function initPeakTimesChart() {
		const ctx = document
			.getElementById( 'peakTimesChart' )
			.getContext( '2d' );

		// Generate 24 hours of random data.
		const generateTimeData = () =>
			Array.from( { length: 24 }, ( _, hour ) => ( {
				hour,
				count: getRandomInt( 10, 100 ),
			} ) );

		const data = generateTimeData();

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => `${ item.hour }:00` ),
				datasets: [
					{
						label: 'Events',
						data: data.map( ( item ) => item.count ),
						backgroundColor: 'rgba(153, 102, 255, 0.8)',
						borderColor: 'rgba(153, 102, 255, 1)',
						borderWidth: 1,
					},
				],
			},
			options: {
				plugins: {
					legend: {
						display: false,
					},
					tooltip: {
						enabled: false,
					},
				},
				responsive: true,
				maintainAspectRatio: false,

				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
						},
					},
					x: {
						ticks: {
							font: {
								size: 10,
							},
						},
					},
				},
			},
		} );
	}

	// Peak Activity Days Chart.
	function initPeakDaysChart() {
		const ctx = document
			.getElementById( 'peakDaysChart' )
			.getContext( '2d' );

		const dayNames = [
			'Sunday',
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday',
		];

		// Generate random data for each day.
		const generateDayData = () =>
			dayNames.map( ( day ) => ( {
				day,
				count: getRandomInt( 50, 200 ),
			} ) );

		const data = generateDayData();

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => item.day ),
				datasets: [
					{
						label: 'Events',
						data: data.map( ( item ) => item.count ),
						backgroundColor: 'rgba(255, 159, 64, 0.8)',
						borderColor: 'rgba(255, 159, 64, 1)',
						borderWidth: 1,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false,
					},
					tooltip: {
						enabled: false,
					},
				},

				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
						},
					},
					x: {
						ticks: {
							font: {
								size: 10,
							},
						},
					},
				},
			},
		} );
	}

	// Events Overview Chart,
	// a sparkline-ish chart of number of events per day.
	function initEventsOverviewChart() {
		const ctx = document
			.getElementById( 'eventsOverviewChart' )
			.getContext( '2d' );

		const data = simpleHistoryStats.data.activityOverview.map(
			( item ) => ( {
				date: wp.date.dateI18n(
					wp.date.__experimentalGetSettings().formats.date,
					item.date
				),
				count: parseInt( item.count, 10 ),
			} )
		);

		const baseColor = getComputedStyle(
			document.documentElement
		).getPropertyValue( '--sh-color-blue' );

		// Highlight the most recent day with the WP admin accent so the
		// "current" bar is visually anchored. Falls back to blue if the
		// CSS variable is not present.
		const accentColor =
			getComputedStyle( document.documentElement )
				.getPropertyValue( '--wp-admin-theme-color' )
				.trim() || baseColor;

		const lastIndex = data.length - 1;
		const barColors = data.map( ( _, index ) =>
			index === lastIndex ? accentColor : baseColor
		);

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => item.date ),
				datasets: [
					{
						label: simpleHistoryStats.strings.events,
						data: data.map( ( item ) => item.count ),
						backgroundColor: barColors,
						borderColor: barColors,
						borderWidth: 0,
						borderRadius: 2,
						categoryPercentage: 0.9,
						barPercentage: 0.85,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false,
					},
					tooltip: {
						enabled: true,
						mode: 'index',
						intersect: false,
						callbacks: {
							title( tooltipItems ) {
								return tooltipItems[ 0 ].label;
							},
							label( context ) {
								return (
									simpleHistoryStats.strings.events +
									': ' +
									context.parsed.y
								);
							},
						},
					},
				},
				scales: {
					x: {
						grid: {
							display: false,
						},
						ticks: {
							display: false,
						},
					},
					y: {
						beginAtZero: true,
						grid: {
							display: false,
						},
						ticks: {
							display: true,
						},
					},
				},
			},
		} );
	}

	// Initialize charts.
	if ( document.getElementById( 'eventsOverviewChart' ) ) {
		initEventsOverviewChart();
	}

	if ( document.getElementById( 'peakTimesChart' ) ) {
		initPeakTimesChart();
	}

	if ( document.getElementById( 'peakDaysChart' ) ) {
		initPeakDaysChart();
	}
} );
