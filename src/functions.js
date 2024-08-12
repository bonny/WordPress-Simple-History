import { LOGLEVELS_OPTIONS } from './constants';

/**
 * Generate api query object based on selected filters.
 *
 * @param {Object} props
 */
export function generateAPIQueryParams( props ) {
	const {
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsers,
		selectedUsersWithId,
		enteredSearchText,
		selectedDateOption,
		selectedCustomDateFrom,
		selectedCustomDateTo,
		page,
		pagerSize,
	} = props;

	// Create query params based on selected filters.
	const eventsQueryParams = {
		page,
		per_page: pagerSize.page,
		_fields: [
			'id',
			'date',
			'date_gmt',
			'message',
			'message_html',
			'details_data',
			'details_html',
			'loglevel',
			'occasions_id',
			'subsequent_occasions_count',
			'initiator',
			'initiator_data',
			'ip_addresses',
			'via',
		],
	};

	if ( enteredSearchText ) {
		eventsQueryParams.search = enteredSearchText;
	}

	if ( selectedDateOption ) {
		if ( selectedDateOption === 'customRange' ) {
			eventsQueryParams.date_from = selectedCustomDateFrom;
			eventsQueryParams.date_to = selectedCustomDateTo;
		} else {
			eventsQueryParams.dates = selectedDateOption;
		}
	}

	if ( selectedLogLevels.length ) {
		// Values in selectedLogLevels are the labels of the log levels, not the values we can use in the API.
		// Use the LOGLEVELS_OPTIONS to find the value for the translated label.
		const selectedLogLevelsValues = [];
		selectedLogLevels.forEach( ( selectedLogLevel ) => {
			const logLevelOption = LOGLEVELS_OPTIONS.find(
				( logLevelOptionLocal ) =>
					logLevelOptionLocal.label === selectedLogLevel
			);

			if ( logLevelOption ) {
				selectedLogLevelsValues.push( logLevelOption.value );
			}
		} );
		if ( selectedLogLevelsValues.length ) {
			eventsQueryParams.loglevels = selectedLogLevelsValues;
		}
	}

	if ( selectedMessageTypes.length ) {
		const selectedMessageTypesValues = [];
		selectedMessageTypes.forEach( ( selectedMessageType ) => {
			const messageTypeOption = messageTypesSuggestions.find(
				( messageTypeOptionLocal ) => {
					return (
						messageTypeOptionLocal.label.trim() ===
						selectedMessageType.trim()
					);
				}
			);

			if ( messageTypeOption ) {
				selectedMessageTypesValues.push( messageTypeOption );
			}
		} );

		if ( selectedMessageTypesValues.length ) {
			let messsagesString = '';
			selectedMessageTypesValues.forEach(
				( selectedMessageTypesValue ) => {
					selectedMessageTypesValue.search_options.forEach(
						( searchOption ) => {
							messsagesString += searchOption + ',';
						}
					);
				}
			);
			eventsQueryParams.messages = messsagesString;
		}
	}

	// Add selected users ids to query params.
	// selectedUsers is an array of strings with the user name and email (not id).
	// ie. ["john doe (john@example.com)", ...]
	// eventsQueryParams.users should be an array of user ids.
	if ( selectedUsersWithId.length ) {
		// Array with user ids.
		const selectedUsersValues = selectedUsersWithId.map(
			( selectedUserWithId ) => {
				return selectedUserWithId.id;
			}
		);

		eventsQueryParams.users = selectedUsersValues;
	}

	return eventsQueryParams;
}
