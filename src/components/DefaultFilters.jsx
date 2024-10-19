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
import { endOfDay, format, startOfDay } from 'date-fns';
import { TIMEZONELESS_FORMAT } from '../constants';

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
								setSelectedCustomDateFrom(
									format(
										startOfDay( nextDate ),
										TIMEZONELESS_FORMAT
									)
								);
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
								setSelectedCustomDateTo(
									format(
										endOfDay( nextDate ),
										TIMEZONELESS_FORMAT
									)
								);
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
			<p>
				<div className="SimpleHistory__filters__filterLabel">
					{ __( 'Dates', 'simple-history' ) }
				</div>
				<div style={ { display: 'inline-block', width: '310px' } }>
					<SelectControl
						options={ dateOptions }
						value={ selectedDateOption }
						onChange={ ( value ) => setSelectedDateOption( value ) }
					/>
				</div>
			</p>

			{ selectedDateOption === 'customRange' ? (
				<CustomDateRange />
			) : null }

			<p>
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
			</p>
		</>
	);
}
