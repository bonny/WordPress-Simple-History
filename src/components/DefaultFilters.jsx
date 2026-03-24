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
import { useEffect, useRef } from '@wordpress/element';
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
		children,
	} = props;

	const searchInputRef = useRef( null );
	const previousFocusRef = useRef( null );
	const focusedViaShortcutRef = useRef( false );

	// "/" focuses the search input, Escape restores focus to the previous element.
	useEffect( () => {
		function handleKeyDown( event ) {
			if ( event.key === 'Escape' ) {
				if ( document.activeElement === searchInputRef.current ) {
					if ( focusedViaShortcutRef.current && previousFocusRef.current ) {
						previousFocusRef.current.focus();
					} else {
						searchInputRef.current.blur();
					}
					previousFocusRef.current = null;
					focusedViaShortcutRef.current = false;
				}
				return;
			}

			if ( event.key !== '/' ) {
				return;
			}

			const tag = event.target.tagName;
			if (
				tag === 'INPUT' ||
				tag === 'TEXTAREA' ||
				tag === 'SELECT' ||
				event.target.isContentEditable
			) {
				return;
			}

			event.preventDefault();
			previousFocusRef.current = document.activeElement;
			focusedViaShortcutRef.current = true;
			searchInputRef.current?.focus();
		}

		// Once the user leaves the search input (tab, click elsewhere),
		// the shortcut context is over — clear the return point.
		function handleFocusOut( event ) {
			if ( event.target === searchInputRef.current ) {
				previousFocusRef.current = null;
				focusedViaShortcutRef.current = false;
			}
		}

		document.addEventListener( 'keydown', handleKeyDown );
		document.addEventListener( 'focusout', handleFocusOut );
		return () => {
			document.removeEventListener( 'keydown', handleKeyDown );
			document.removeEventListener( 'focusout', handleFocusOut );
		};
	}, [] );

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
					ref={ searchInputRef }
					type="search"
					value={ searchText }
					onChange={ ( value ) => setSearchText( value || '' ) }
					placeholder={ __( 'Search events', 'simple-history' ) }
					aria-label={ __( 'Search events', 'simple-history' ) }
					prefix={
						<InputControlPrefixWrapper>
							<Icon
								icon={ search }
								size={ 20 }
								style={ { color: '#646970' } }
							/>
						</InputControlPrefixWrapper>
					}
					suffix={
						<kbd className="SimpleHistory-filters__searchShortcut">
							/
						</kbd>
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

				<Button
					variant="secondary"
					onClick={ onReload }
					__next40pxDefaultSize
				>
					{ __( 'Search events', 'simple-history' ) }
				</Button>

				{ children }
			</div>

			{ selectedDateOption === 'customRange' ? (
				<CustomDateRange />
			) : null }
		</>
	);
}
