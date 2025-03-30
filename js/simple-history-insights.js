/* global Chart, simpleHistoryInsights */
jQuery( function () {
	( 'use strict' );

	// Set default Chart.js options
	Chart.defaults.font.family =
		'-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif';
	Chart.defaults.color = '#666';
	Chart.defaults.plugins.legend.position = 'bottom';

	// Top Users Chart
	function initTopUsersChart() {
		const ctx = document
			.getElementById( 'topUsersChart' )
			.getContext( '2d' );
		const data = simpleHistoryInsights.data.topUsers.map( ( user ) => ( {
			label: user.name,
			value: parseInt( user.count, 10 ),
		} ) );

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => item.label ),
				datasets: [
					{
						label: simpleHistoryInsights.strings.actions,
						data: data.map( ( item ) => item.value ),
						backgroundColor: 'rgba(54, 162, 235, 0.8)',
						borderColor: 'rgba(54, 162, 235, 1)',
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

	// Activity Overview Chart
	function initActivityChart() {
		const ctx = document
			.getElementById( 'activityChart' )
			.getContext( '2d' );
		const data = simpleHistoryInsights.data.activityOverview.map(
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
						label: simpleHistoryInsights.strings.events,
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

	// Most Common Actions Chart
	function initActionsChart() {
		const ctx = document
			.getElementById( 'actionsChart' )
			.getContext( '2d' );
		const data = simpleHistoryInsights.data.topActions.map(
			( action ) => ( {
				label: action.logger,
				value: parseInt( action.count, 10 ),
			} )
		);

		new Chart( ctx, {
			type: 'doughnut',
			data: {
				labels: data.map( ( item ) => item.label ),
				datasets: [
					{
						data: data.map( ( item ) => item.value ),
						backgroundColor: [
							'rgba(255, 99, 132, 0.8)',
							'rgba(54, 162, 235, 0.8)',
							'rgba(255, 206, 86, 0.8)',
							'rgba(75, 192, 192, 0.8)',
							'rgba(153, 102, 255, 0.8)',
							'rgba(255, 159, 64, 0.8)',
							'rgba(255, 99, 132, 0.8)',
							'rgba(54, 162, 235, 0.8)',
							'rgba(255, 206, 86, 0.8)',
							'rgba(75, 192, 192, 0.8)',
						],
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
			},
		} );
	}

	// Peak Activity Times Chart
	function initPeakTimesChart() {
		const ctx = document
			.getElementById( 'peakTimesChart' )
			.getContext( '2d' );
		const data = simpleHistoryInsights.data.peakTimes.map( ( time ) => ( {
			hour: time.hour,
			count: parseInt( time.count, 10 ),
		} ) );

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => `${ item.hour }:00` ),
				datasets: [
					{
						label: simpleHistoryInsights.strings.events,
						data: data.map( ( item ) => item.count ),
						backgroundColor: 'rgba(153, 102, 255, 0.8)',
						borderColor: 'rgba(153, 102, 255, 1)',
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
		const data = simpleHistoryInsights.data.peakDays.map( ( day ) => ( {
			day: dayNames[ parseInt( day.day, 10 ) ],
			count: parseInt( day.count, 10 ),
		} ) );

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: data.map( ( item ) => item.day ),
				datasets: [
					{
						label: simpleHistoryInsights.strings.events,
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

	// Initialize all charts
	if ( document.getElementById( 'topUsersChart' ) ) {
		initTopUsersChart();
	}
	if ( document.getElementById( 'activityChart' ) ) {
		initActivityChart();
	}
	if ( document.getElementById( 'actionsChart' ) ) {
		initActionsChart();
	}
	if ( document.getElementById( 'peakTimesChart' ) ) {
		initPeakTimesChart();
	}
	if ( document.getElementById( 'peakDaysChart' ) ) {
		initPeakDaysChart();
	}
} );
