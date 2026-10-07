import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	DATE_OPTION_GROUPS_LOADING,
	DEFAULT_DATE_OPTION_GROUPS,
} from '../constants';

/**
 * Fetch /search-options once and spread the answer across the page's state.
 *
 * This is the page's bootstrap, not the filter panel's. The endpoint carries
 * the filter dropdowns' contents, but it also carries the pager size, which
 * add-ons are active, whether reactions and experimental features are on,
 * three admin page URLs, who the current user is and what they may do — none
 * of which belongs to a filter, and all of which the rest of the page needs
 * whether or not a filter is ever shown.
 *
 * It used to live inside EventsSearchFilters, which meant the panel could not
 * be left unrendered without taking the page's data down with it. Hoisting it
 * here is what lets the table view — which has its own query bar and chips —
 * skip core's filter panel entirely.
 *
 * Every setter is called at most once, on the single response. They are listed
 * individually rather than taken as one object so the effect's dependencies
 * stay honest: a caller passing a fresh object literal each render would
 * otherwise refetch on every render.
 *
 * @param {Object}   props                                  Setters owned by EventsGui, plus the two values read below.
 * @param {string}   props.selectedDateOption               Date option already chosen, if any.
 * @param {Object}   props.defaultDateOptionRef             Ref the API's recommended date option is written to.
 * @param {Function} props.setSelectedDateOption            Sets the chosen date option.
 * @param {Function} props.setSearchOptionsLoaded           Flips once the request settles, either way.
 * @param {Function} props.setPagerSize                     Sets the pager size.
 * @param {Function} props.setMapsApiKey                    Sets the maps API key.
 * @param {Function} props.setHasExtendedSettingsAddOn      Sets whether the extended settings add-on is active.
 * @param {Function} props.setHasPremiumAddOn               Sets whether the premium add-on is active.
 * @param {Function} props.setHasFailedLoginLimit           Sets whether failed logins are being limited.
 * @param {Function} props.setFailedLoginLimitThreshold     Sets the failed login threshold.
 * @param {Function} props.setFailedLoginSuppressedCount    Sets how many failed logins were suppressed.
 * @param {Function} props.setIsReactionsEnabled            Sets whether event reactions are on.
 * @param {Function} props.setIsExperimentalFeaturesEnabled Sets whether experimental features are on.
 * @param {Function} props.setEventsAdminPageURL            Sets the events admin page URL.
 * @param {Function} props.setEventsSettingsPageURL         Sets the settings page URL.
 * @param {Function} props.setAlertsPageURL                 Sets the alerts page URL.
 * @param {Function} props.setCurrentUserId                 Sets the current user id.
 * @param {Function} props.setUserCanManageOptions          Sets whether the user may manage options.
 * @return {{searchOptions: (Object|null), dateOptionGroups: Array}} The response, and the date dropdown's groups.
 */
export function useSearchOptions( {
	selectedDateOption,
	defaultDateOptionRef,
	setSelectedDateOption,
	setSearchOptionsLoaded,
	setPagerSize,
	setMapsApiKey,
	setHasExtendedSettingsAddOn,
	setHasPremiumAddOn,
	setHasFailedLoginLimit,
	setFailedLoginLimitThreshold,
	setFailedLoginSuppressedCount,
	setIsReactionsEnabled,
	setIsExperimentalFeaturesEnabled,
	setEventsAdminPageURL,
	setEventsSettingsPageURL,
	setAlertsPageURL,
	setCurrentUserId,
	setUserCanManageOptions,
} ) {
	const [ dateOptionGroups, setDateOptionGroups ] = useState(
		DATE_OPTION_GROUPS_LOADING
	);
	const [ searchOptions, setSearchOptions ] = useState( null );

	// The search options effect only needs to know whether a date option was
	// already picked (from the URL) when the response arrives. Read it through
	// a ref so the effect does not depend on the value it sets itself; with the
	// value in the deps it re-ran after setting the default, fetching the
	// search options twice and, via a new pagerSize object, the events twice.
	const selectedDateOptionRef = useRef( selectedDateOption );
	selectedDateOptionRef.current = selectedDateOption;

	// Load search options when component mounts.
	useEffect( () => {
		const fetchSearchOptions = async () => {
			try {
				const searchOptionsResponse = await apiFetch( {
					path: addQueryArgs(
						'/simple-history/v1/search-options',
						{}
					),
				} );

				setSearchOptions( searchOptionsResponse );

				// "All dates" is rendered as an ungrouped option at the
				// very top — it's the conventional "reset/clear" slot
				// in a select, immediately discoverable, and avoids the
				// orphaned-option problem of placing it after optgroups.
				const allDatesGroup = {
					label: '',
					options: [
						{
							label: __( 'All dates', 'simple-history' ),
							value: 'allDates',
						},
					],
				};

				const monthsOptions =
					searchOptionsResponse.dates.result_months.map(
						( row ) => ( {
							label: dateI18n( 'F Y', row.yearMonth ),
							value: `month:${ row.yearMonth }`,
						} )
					);

				const monthsGroup = {
					label: __( 'By month', 'simple-history' ),
					options: monthsOptions,
				};

				setDateOptionGroups( [
					allDatesGroup,
					...DEFAULT_DATE_OPTION_GROUPS,
					monthsGroup,
				] );

				// Store the default date option for use when clearing filters.
				const apiDefaultDateOption = `lastdays:${ searchOptionsResponse.dates.daysToShow }`;
				defaultDateOptionRef.current = apiDefaultDateOption;

				// Set selected date option to "recommended" option from API.
				// Only set if not already set, because it can be set in the URL.
				if ( ! selectedDateOptionRef.current ) {
					setSelectedDateOption( apiDefaultDateOption );
				}

				setPagerSize( searchOptionsResponse.pager_size );
				setMapsApiKey( searchOptionsResponse.maps_api_key );

				setHasExtendedSettingsAddOn(
					searchOptionsResponse.addons.has_extended_settings_add_on
				);

				setHasPremiumAddOn(
					searchOptionsResponse.addons.has_premium_add_on
				);

				setIsReactionsEnabled(
					searchOptionsResponse.reactions_enabled
				);

				setIsExperimentalFeaturesEnabled(
					Boolean(
						searchOptionsResponse.experimental_features_enabled
					)
				);

				setHasFailedLoginLimit(
					searchOptionsResponse.has_failed_login_limit
				);

				setFailedLoginLimitThreshold(
					searchOptionsResponse.failed_login_limit_threshold || 0
				);

				setFailedLoginSuppressedCount(
					searchOptionsResponse.failed_login_suppressed_count || 0
				);

				// Only when the response actually carries one. Add-ons can
				// override the URL through the search options filter, but an
				// absent field must not downgrade the value seeded at enqueue
				// time — losing it hides the links built from it.
				if ( searchOptionsResponse.events_admin_page_url ) {
					setEventsAdminPageURL(
						searchOptionsResponse.events_admin_page_url
					);
				}
				setEventsSettingsPageURL(
					searchOptionsResponse.settings_page_url
				);

				// Set alerts page URL if provided by premium add-on.
				if ( searchOptionsResponse.alerts_page_url ) {
					setAlertsPageURL( searchOptionsResponse.alerts_page_url );
				}

				// Set current user ID for "Hide my own events" feature.
				if ( searchOptionsResponse.current_user_id ) {
					setCurrentUserId( searchOptionsResponse.current_user_id );
				}

				// Set whether user can manage options (is administrator).
				if ( searchOptionsResponse.current_user_can_manage_options ) {
					setUserCanManageOptions(
						searchOptionsResponse.current_user_can_manage_options
					);
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error(
					'Simple History: Failed to load search options',
					error
				);
			} finally {
				setSearchOptionsLoaded( true );
			}
		};

		fetchSearchOptions();
	}, [
		defaultDateOptionRef,
		setPagerSize,
		setSearchOptionsLoaded,
		setSelectedDateOption,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setHasPremiumAddOn,
		setHasFailedLoginLimit,
		setFailedLoginLimitThreshold,
		setFailedLoginSuppressedCount,
		setIsReactionsEnabled,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
		setEventsSettingsPageURL,
		setAlertsPageURL,
		setCurrentUserId,
		setUserCanManageOptions,
	] );

	return { searchOptions, dateOptionGroups };
}
