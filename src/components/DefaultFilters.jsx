import {
	BaseControl,
	DatePicker,
	Flex,
	FlexItem,
	SelectControl,
} from '@wordpress/components';
import { getSettings as getDateSettings } from '@wordpress/date';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export function DefaultFilters( props ) {
	const {
		dateOptions,
		selectedDateOption,
		setSelectedDateOption,
		searchText,
		setSearchText,
		selectedCustomDateFrom,
		setSelectedCustomDateFrom,
		selectedCustomDateTo,
		setSelectedCustomDateTo,
	} = props;

	// Future dates are invalid.
	const isInvalidDate = ( date ) => {
		if ( date > new Date() ) {
			return true;
		}

		return false;
	};

	/**
	 * When dates are changed, make sure that to date is not before from date.
	 */
	useEffect( () => {
		if ( selectedCustomDateFrom && selectedCustomDateTo ) {
			if ( selectedCustomDateFrom > selectedCustomDateTo ) {
				setSelectedCustomDateTo( selectedCustomDateFrom );
			}
		}
	}, [
		selectedCustomDateFrom,
		selectedCustomDateTo,
		setSelectedCustomDateTo,
	] );

	function CustomDateRange() {
		const firstDayOfWeek = getDateSettings().l10n.startOfWeek;

		return (
			<Flex justify="start" gap="15">
				<FlexItem style={ { width: '95px' } }>
					{ /* Empty for space */ }
				</FlexItem>

				<FlexItem>
					{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
					<BaseControl label={ __( 'From date', 'simple-history' ) }>
						<DatePicker
							id="simple-history-datepicker-from"
							startOfWeek={ firstDayOfWeek }
							onChange={ ( nextDate ) => {
								// Create date at UTC midnight for the selected date
								const date = new Date( nextDate );
								const utcDate = new Date(
									Date.UTC(
										date.getFullYear(),
										date.getMonth(),
										date.getDate()
									)
								);
								setSelectedCustomDateFrom( utcDate );
							} }
							currentDate={ selectedCustomDateFrom }
							isInvalidDate={ isInvalidDate }
						/>
					</BaseControl>
				</FlexItem>

				<FlexItem>
					{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
					<BaseControl label={ __( 'To date', 'simple-history' ) }>
						<DatePicker
							startOfWeek={ firstDayOfWeek }
							onChange={ ( nextDate ) => {
								// Create date at UTC midnight for the selected date
								const date = new Date( nextDate );
								const utcDate = new Date(
									Date.UTC(
										date.getFullYear(),
										date.getMonth(),
										date.getDate()
									)
								);
								setSelectedCustomDateTo( utcDate );
							} }
							currentDate={ selectedCustomDateTo }
							isInvalidDate={ isInvalidDate }
						/>
					</BaseControl>
				</FlexItem>
			</Flex>
		);
	}

	return (
		<>
			<div style={ { margin: '1em 0' } }>
				<div className="SimpleHistory__filters__filterLabel">
					{ __( 'Dates', 'simple-history' ) }
				</div>
				<div style={ { display: 'inline-block', width: '310px' } }>
					<SelectControl
						__nextHasNoMarginBottom
						options={ dateOptions }
						value={ selectedDateOption }
						onChange={ ( value ) => setSelectedDateOption( value ) }
					/>
				</div>
			</div>

			{ selectedDateOption === 'customRange' ? (
				<CustomDateRange />
			) : null }

			<div style={ { margin: '1em 0' } }>
				<div className="SimpleHistory__filters__filterLabel">
					{ __( 'Containing words', 'simple-history' ) }
				</div>
				<input
					type="search"
					className="SimpleHistoryFilterDropin-searchInput"
					value={ searchText }
					onChange={ ( event ) =>
						setSearchText( event.target.value )
					}
				/>
			</div>
		</>
	);
}
