/* global Chart, simpleHistoryStats */
jQuery( function () {
	( 'use strict' );

	// Set default Chart.js options
	Chart.defaults.font.family =
		'-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif';
	Chart.defaults.color = '#666';
	Chart.defaults.plugins.legend.position = 'bottom';

	// Activity Overview Chart
	function initActivityChart() {
		const ctx = document
			.getElementById( 'activityChart' )
			.getContext( '2d' );
		const data = simpleHistoryStats.data.activityOverview.map(
			( item ) => ( {
				date: new Date( item.date ).toLocaleDateString(),
				count: parseInt( item.count, 10 ),
			} )
		);

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => item.date ),
				datasets: [
					{
						label: simpleHistoryStats.strings.events,
						data: data.map( ( item ) => item.count ),
						backgroundColor: 'rgba(75, 192, 192, 0.8)',
						borderColor: 'rgba(75, 192, 192, 1)',
						borderWidth: 1,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
						},
					},
				},
			},
		} );
	}

	// Peak Activity Times Chart
	function initPeakTimesChart() {
		const ctx = document
			.getElementById( 'peakTimesChart' )
			.getContext( '2d' );
		const data = simpleHistoryStats.data.peakTimes.map( ( time ) => ( {
			hour: time.hour,
			count: parseInt( time.count, 10 ),
		} ) );

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => `${ item.hour }:00` ),
				datasets: [
					{
						label: simpleHistoryStats.strings.events,
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

	// Peak Activity Days Chart
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

		const data = simpleHistoryStats.data.peakDays.map( ( day ) => ( {
			day: dayNames[ parseInt( day.day, 10 ) ],
			count: parseInt( day.count, 10 ),
		} ) );

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => item.day ),
				datasets: [
					{
						label: simpleHistoryStats.strings.events,
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

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => item.date ),
				datasets: [
					{
						label: simpleHistoryStats.strings.events,
						data: data.map( ( item ) => item.count ),
						barPercentage: 0.99,
						categoryPercentage: 1,
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
	if ( document.getElementById( 'activityChart' ) ) {
		initActivityChart();
	}

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
