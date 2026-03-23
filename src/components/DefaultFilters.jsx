import {
	BaseControl,
	Button,
	DatePicker,
	Flex,
	FlexItem,
	SelectControl,
	__experimentalInputControl as InputControl,
	__experimentalInputControlPrefixWrapper as InputControlPrefixWrapper,
} from '@wordpress/components';
import { getSettings as getDateSettings } from '@wordpress/date';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon, search } from '@wordpress/icons';

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
		onReload,
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
			<div className="SimpleHistory-filters__defaultRow">
				<InputControl
					type="search"
					value={ searchText }
					onChange={ ( value ) => setSearchText( value || '' ) }
					placeholder={ __(
						'Search events',
						'simple-history'
					) }
					aria-label={ __(
						'Search events',
						'simple-history'
					) }
					prefix={
						<InputControlPrefixWrapper>
							<Icon
								icon={ search }
								size={ 20 }
								style={ { color: '#646970' } }
							/>
						</InputControlPrefixWrapper>
					}
					__next40pxDefaultSize
					className="SimpleHistory-filters__searchControl"
				/>

				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					options={ dateOptions }
					value={ selectedDateOption }
					onChange={ ( value ) => setSelectedDateOption( value ) }
					className="SimpleHistory-filters__dateSelect"
				/>

				<Button variant="secondary" onClick={ onReload } __next40pxDefaultSize>
					{ __( 'Search events', 'simple-history' ) }
				</Button>
			</div>

			{ selectedDateOption === 'customRange' ? (
				<CustomDateRange />
			) : null }
		</>
	);
}
