/**
 * JavaScript for Simple History Insights Sidebar Chart.
 *
 * Initializes Chart.js for the sidebar widget that displays
 * daily activity over the last 30 days.
 *
 * @param {jQuery} $ - jQuery instance.
 */
( function ( $ ) {
	$( function () {
		const ctx = $( '.SimpleHistory_SidebarChart_ChartCanvas' );

		if ( ! ctx.length ) {
			return;
		}

		const chartLabels = JSON.parse(
			$( '.SimpleHistory_SidebarChart_ChartLabels' ).val()
		);
		const chartLabelsToDates = JSON.parse(
			$( '.SimpleHistory_SidebarChart_ChartLabelsToDates' ).val()
		);
		const chartDatasetData = JSON.parse(
			$( '.SimpleHistory_SidebarChart_ChartDatasetData' ).val()
		);

		const color = getComputedStyle(
			document.documentElement
		).getPropertyValue( '--sh-color-blue' );

		// Create chart.
		const myChart = new window.Chart( ctx, {
			type: 'line',
			data: {
				labels: chartLabels,
				datasets: [
					{
						label: '',
						data: chartDatasetData,
						borderColor: color,
						backgroundColor: color,
						borderWidth: 2,
						pointRadius: 0,
					},
				],
			},
			options: {
				interaction: {
					intersect: false,
					mode: 'index',
				},
				scales: {
					y: {
						ticks: {
							beginAtZero: true,
						},
					},
					x: {
						display: false,
					},
				},
				plugins: {
					legend: {
						display: false,
					},
					// https://www.chartjs.org/docs/4.4.0/configuration/tooltip.html
					tooltip: {
						displayColors: false,
						callbacks: {
							label( context ) {
								const eventsCount = context.parsed.y;
								const label = `${ eventsCount } events`;
								return label;
							},
						},
					},
				},
				onClick: clickChart,
			},
		} );

		/**
		 * When chart is clicked determine what value/day was clicked
		 * and dispatch a custom event to the React app to handle the date filter.
		 *
		 * @param {Object} e - The click event object.
		 */
		function clickChart( e ) {
			// Get value of selected bar.
			// Use 'index' mode with intersect: false to match the tooltip behavior,
			// so clicking anywhere in the vertical area of a day will select that day.
			let label;
			const points = myChart.getElementsAtEventForMode(
				e,
				'index',
				{ intersect: false },
				true
			);
			if ( points.length ) {
				const firstPoint = points[ 0 ];
				// Label e.g. "Jun 25".
				label = myChart.data.labels[ firstPoint.index ];
			}

			// now we have the label which is like "July 23" or "23 juli" depending on language
			// look for that label value in chartLabelsToDates and there we get the date in format Y-m-d
			let labelDate;
			for ( const idx in chartLabelsToDates ) {
				if ( label === chartLabelsToDates[ idx ].label ) {
					labelDate = chartLabelsToDates[ idx ];
				}
			}

			if ( ! labelDate ) {
				return;
			}

			// Dispatch custom event for React app to handle the date filter.
			// The React app will listen for this event and update the date filter state.
			const event = new CustomEvent( 'SimpleHistory:chartDateClick', {
				detail: {
					// Date in Y-m-d format, e.g., "2024-10-05".
					date: labelDate.date,
				},
			} );

			window.dispatchEvent( event );
		}
	} );
} )( jQuery );
