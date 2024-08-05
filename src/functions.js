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
				( logLevelOption ) => logLevelOption.label === selectedLogLevel
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
				( messageTypeOption ) => {
					return (
						messageTypeOption.label.trim() ===
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

	if ( selectedUsers.length ) {
		const selectedUsersValues = [];
		selectedUsers.forEach( ( selectedUserNameAndEmail ) => {
			let userSuggestion = userSuggestions.find( ( userSuggestion ) => {
				return (
					userSuggestion.label.trim() ===
					selectedUserNameAndEmail.trim()
				);
			} );

			if ( userSuggestion ) {
				selectedUsersValues.push( userSuggestion.id );
			}
		} );

		if ( selectedUsersValues.length ) {
			eventsQueryParams.users = selectedUsersValues;
		}
	}

	return eventsQueryParams;
}
